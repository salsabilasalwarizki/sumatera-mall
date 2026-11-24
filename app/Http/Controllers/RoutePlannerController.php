<?php

namespace App\Http\Controllers;

use App\Services\OsmService;
use App\Services\SparqlService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class RoutePlannerController extends Controller
{
    public function __construct(
        private readonly SparqlService $sparql,
        private readonly OsmService $osm,
    ) {}

    public function index(): View
    {
        $malls = $this->sparql->query(<<<'SPARQL'
PREFIX mm: <http://sumateramall.org/ontology#>
PREFIX schema: <http://schema.org/>
SELECT ?id ?name ?city ?province ?address ?lat ?lng WHERE {
    ?mall a mm:Mall ; schema:name ?name ; mm:inCity ?cityRes ; mm:inProvince ?provinceRes .
    ?cityRes schema:name ?city .
    ?provinceRes schema:name ?province .
    OPTIONAL { ?mall schema:address ?address }
    OPTIONAL { ?mall schema:geo ?geo . ?geo schema:latitude ?lat ; schema:longitude ?lng }
    BIND(STRAFTER(STR(?mall), 'resource/Mall/') AS ?id)
}
ORDER BY ?city ?name
SPARQL, 3600);

        return view('planner.index', compact('malls'));
    }

    public function calculate(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'mall_ids' => ['required', 'array', 'min:2', 'max:8'],
            'mall_ids.*' => ['required', 'string', 'regex:/\A[a-z0-9_]+\z/', 'distinct'],
            'start_lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:start_lng'],
            'start_lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:start_lat'],
            'locale' => ['nullable', 'in:id,en'],
        ]);
        $selectedIris = array_map(
            fn (string $id): string => $this->sparql->internalResource('http://sumateramall.org/resource/Mall/'.$id, 'Mall'),
            $validated['mall_ids'],
        );
        $malls = $this->sparql->query(<<<SPARQL
PREFIX mm: <http://sumateramall.org/ontology#>
PREFIX schema: <http://schema.org/>
SELECT ?id ?name ?city ?province ?address ?lat ?lng WHERE {
    VALUES ?mall { {$this->joinIris($selectedIris)} }
    ?mall schema:name ?name ;
          mm:inCity ?cityRes ;
          mm:inProvince ?provinceRes .
    ?cityRes schema:name ?city .
    ?provinceRes schema:name ?province .
    OPTIONAL { ?mall schema:address ?address }
    OPTIONAL { ?mall schema:geo ?geo . ?geo schema:latitude ?lat ; schema:longitude ?lng }
    BIND(STRAFTER(STR(?mall), 'resource/Mall/') AS ?id)
}
SPARQL);

        if (count($malls) !== count($validated['mall_ids'])) {
            throw ValidationException::withMessages([
                'mall_ids' => 'Some selected malls are missing from the RDF graph. Refresh the imported mall data and try again.',
            ]);
        }
        $mallsById = array_column($malls, null, 'id');
        $malls = array_map(fn (string $id): array => $mallsById[$id], $validated['mall_ids']);
        foreach ($malls as $mall) {
            if (! is_numeric($mall['lat'] ?? null) || ! is_numeric($mall['lng'] ?? null)) {
                throw ValidationException::withMessages([
                    'mall_ids' => ($validated['locale'] ?? 'id') === 'en'
                        ? 'Some selected malls are missing coordinates. Add latitude and longitude to the RDF data, then try again.'
                        : 'Beberapa mall yang dipilih belum memiliki koordinat. Tambahkan latitude dan longitude ke data RDF, lalu coba lagi.',
                ]);
            }
        }

        try {
            $route = $this->osm->planRoute(
                $malls,
                isset($validated['start_lat']) ? (float) $validated['start_lat'] : null,
                isset($validated['start_lng']) ? (float) $validated['start_lng'] : null,
            );
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            Log::warning('OpenStreetMap route calculation failed.', [
                'message' => $exception->getMessage(),
            ]);

            return redirect()->route('planner.index')
                ->withInput()
                ->with('error', ($validated['locale'] ?? 'id') === 'en'
                    ? 'Road routing is unavailable right now. Please try again later.'
                    : 'Layanan rute jalan sedang tidak tersedia. Silakan coba lagi nanti.');
        }
        $itinerary = [
            ...$route,
            'plannedAt' => now('Asia/Jakarta')->toIso8601String(),
            'startIsMall' => ! isset($validated['start_lat']),
            'locale' => $validated['locale'] ?? 'id',
        ];
        $request->session()->put('planner.itinerary', $itinerary);

        return view('planner.result', $itinerary);
    }

    public function exportPdf(Request $request): Response|RedirectResponse
    {
        $itinerary = $request->session()->get('planner.itinerary');
        if (! is_array($itinerary) || empty($itinerary['malls'])) {
            return redirect()->route('planner.index')->with('error', 'Plan a route before exporting an itinerary.');
        }

        return Pdf::loadView('planner.pdf', ['itinerary' => $itinerary])
            ->setPaper('a4')
            ->download('sumateramall-itinerary.pdf');
    }

    public function exportIcs(Request $request): Response|RedirectResponse
    {
        $itinerary = $request->session()->get('planner.itinerary');
        if (! is_array($itinerary) || empty($itinerary['malls'])) {
            return redirect()->route('planner.index')->with('error', 'Plan a route before exporting an itinerary.');
        }

        $start = Carbon::parse($itinerary['plannedAt'] ?? now('Asia/Jakarta'), 'Asia/Jakarta')
            ->setTime(10, 0)
            ->utc();
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//SumateraMall//Mall Hopping//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($itinerary['malls'] as $index => $mall) {
            $eventStart = $start->copy()->addHours($index * 2);
            $eventEnd = $eventStart->copy()->addHours(2);
            $lines = array_merge($lines, [
                'BEGIN:VEVENT',
                'UID:'.sha1(($mall['id'] ?? $mall['name']).$eventStart->format('c')).'@sumateramall.org',
                'DTSTAMP:'.now('UTC')->format('Ymd\THis\Z'),
                'DTSTART:'.$eventStart->format('Ymd\THis\Z'),
                'DTEND:'.$eventEnd->format('Ymd\THis\Z'),
                'SUMMARY:'.$this->escapeCalendarText('Visit '.$mall['name']),
                'LOCATION:'.$this->escapeCalendarText(($mall['city'] ?? '').', Sumatra, Indonesia'),
                'END:VEVENT',
            ]);
        }

        $lines[] = 'END:VCALENDAR';

        return response(implode("\r\n", $lines)."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="sumateramall-itinerary.ics"',
        ]);
    }

    /**
     * @param  array<int, string>  $iris
     */
    private function joinIris(array $iris): string
    {
        return implode(' ', $iris);
    }

    private function escapeCalendarText(string $value): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $value);
    }
}
