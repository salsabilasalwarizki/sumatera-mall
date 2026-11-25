<?php

namespace App\Http\Controllers;

use App\Models\ChatLog;
use App\Services\SparqlService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ChatbotController extends Controller
{
    public function __construct(
        private readonly SparqlService $sparql,
    ) {}

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'session_id' => ['nullable', 'string', 'max:80', 'regex:/\A[a-zA-Z0-9_-]+\z/'],
            'locale' => ['nullable', 'in:id,en'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);
        $message = trim($validated['message']);
        if ($message === '') {
            throw ValidationException::withMessages(['message' => 'Enter a question.']);
        }

        $sessionId = $validated['session_id'] ?? Str::random(24);
        $intent = $this->parseIntent($message);
        if (in_array($intent['type'], ['unknown', 'unsupported_data'], true)) {
            return response()->json([
                'reply' => $this->unsupportedQuestionReply(
                    ($validated['locale'] ?? 'id') === 'en',
                    $intent['type'] === 'unsupported_data',
                ),
                'intent' => $intent,
                'results_count' => 0,
                'session_id' => $sessionId,
            ]);
        }

        $locale = $validated['locale'] ?? 'id';
        if ($intent['type'] === 'nearest_mall'
            && (! isset($validated['latitude']) || ! isset($validated['longitude']))) {
            return response()->json([
                'reply' => $locale === 'en'
                    ? 'Please allow location access in your browser, then ask again so I can calculate the nearest mall.'
                    : 'Izinkan akses lokasi di browser, lalu tanyakan kembali agar saya bisa menghitung mall terdekat.',
                'intent' => $intent,
                'results_count' => 0,
                'session_id' => $sessionId,
            ]);
        }

        $sparql = $this->generateSparql($intent);

        try {
            $results = $this->sparql->query($sparql);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            Log::error('SumateraMall chatbot query failed.', [
                'exception' => $exception->getMessage(),
                'intent' => $intent['type'],
            ]);

            return response()->json([
                'message' => 'Mall data is temporarily unavailable. Please try again later.',
            ], 503);
        }

        if ($intent['type'] === 'nearest_mall') {
            $results = $this->orderMallsByDistance(
                $results,
                (float) $validated['latitude'],
                (float) $validated['longitude'],
            );
        }

        $reply = $this->formatReply($intent, $results, $locale);
        ChatLog::create([
            'session_id' => $sessionId,
            'user_message' => $message,
            'bot_reply' => $reply,
            'intent_detected' => $intent['type'],
            'sparql_query_used' => $sparql,
        ]);

        return response()->json([
            'reply' => $reply,
            'intent' => $intent,
            'results_count' => count($results),
            'session_id' => $sessionId,
        ]);
    }

    protected function unsupportedQuestionReply(bool $english, bool $missingGraphFields = false): string
    {
        $unavailableData = $missingGraphFields
            ? ($english
                ? 'The current graph has no recorded store ratings or price-level values. '
                : 'Graph saat ini tidak mencatat rating toko atau tingkat harga. ')
            : '';

        return $unavailableData.($english
            ? "I can answer questions using the SumatraMall data, such as:\n- Cafes or stores in a mall (e.g. \"cafes around Sun Plaza\")\n- Malls in a city or province\n- Mall address, opening hours, facilities, and website\n- The nearest mall to your current location"
            : "Saya bisa menjawab pertanyaan berdasarkan data SumatraMall, misalnya:\n- Kafe atau toko di mall (contoh: \"kafe sekitar Sun Plaza\")\n- Daftar mall di kota atau provinsi\n- Alamat, jam operasional, fasilitas, dan situs mall\n- Mall terdekat dari lokasi Anda");
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseIntent(string $message): array
    {
        $text = Str::ascii(mb_strtolower($message));
        $intent = ['type' => 'unknown'];

        if (preg_match('/\b(rating|rated|terbaik|top|popular|harga|price|budget|biaya|cost|murah|mahal)\b/u', $text)) {
            $intent['type'] = 'unsupported_data';

            return $intent;
        }

        if (preg_match('/(fitur|website|situs|aplikasi|sumatramall|tentang kami|cara menggunakan|about this website|about sumatramall|website features|what can this site)/u', $text)) {
            $intent['type'] = 'site_info';
        } elseif (preg_match('/(mall terdekat|terdekat.*mall|mall.*dekat.*saya|nearest mall|mall.*near me|closest mall)/u', $text)) {
            $intent['type'] = 'nearest_mall';
        } elseif (preg_match('/(kota|daftar mall|provinsi|mall di|mall.*mana|city|list malls|province|malls in)/u', $text)) {
            $intent['type'] = 'list_by_region';
        }

        $provinces = [
            'sumatera utara' => 'Sumatera Utara',
            'sumatera barat' => 'Sumatera Barat',
            'sumatera selatan' => 'Sumatera Selatan',
            'kepulauan riau' => 'Kepulauan Riau',
            'bangka belitung' => 'Bangka Belitung',
            'aceh' => 'Aceh',
            'sumut' => 'Sumatera Utara',
            'sumbar' => 'Sumatera Barat',
            'batam' => 'Kepulauan Riau',
            'kepri' => 'Kepulauan Riau',
            'jambi' => 'Jambi',
            'sumsel' => 'Sumatera Selatan',
            'babel' => 'Bangka Belitung',
            'bengkulu' => 'Bengkulu',
            'lampung' => 'Lampung',
            'north sumatra' => 'Sumatera Utara',
            'west sumatra' => 'Sumatera Barat',
            'south sumatra' => 'Sumatera Selatan',
            'riau islands' => 'Kepulauan Riau',
            'riau' => 'Riau',
        ];
        foreach ($provinces as $keyword => $province) {
            if (str_contains($text, $keyword)) {
                $intent['province'] = $province;
                break;
            }
        }

        $cities = [
            'banda aceh' => 'Banda Aceh',
            'medan' => 'Medan',
            'padang' => 'Padang',
            'pekanbaru' => 'Pekanbaru',
            'tanjung pinang' => 'Tanjung Pinang',
            'batam' => 'Batam',
            'jambi' => 'Jambi',
            'palembang' => 'Palembang',
            'pangkal pinang' => 'Pangkalpinang',
            'bengkulu' => 'Bengkulu',
            'bandar lampung' => 'Bandar Lampung',
        ];
        foreach ($cities as $keyword => $city) {
            if (str_contains($text, $keyword)) {
                $intent['city'] = $city;
                break;
            }
        }

        $categories = [
            'cafe' => ['cafe', 'coffee'],
            'café' => ['cafe', 'coffee'],
            'kafe' => ['cafe', 'coffee'],
            'kopi' => ['coffee', 'cafe'],
            'restaurant' => ['restaurant', 'food', 'fnb'],
            'restoran' => ['restaurant', 'food', 'fnb'],
            'makanan' => ['food', 'makanan', 'fnb'],
            'minuman' => ['beverage', 'minuman', 'fnb'],
            'fashion' => ['fashion'],
            'baju' => ['fashion'],
            'sepatu' => ['fashion'],
            'clothes' => ['fashion'],
            'shoes' => ['fashion'],
            'electronics' => ['electronics'],
            'elektronik' => ['electronics'],
            'handphone' => ['electronics'],
            'bioskop' => ['entertainment'],
            'cinema' => ['entertainment'],
            'movie' => ['entertainment'],
            'supermarket' => ['supermarket'],
        ];
        foreach ($categories as $keyword => $terms) {
            if (str_contains($text, $keyword)) {
                $intent['category_terms'] = $terms;
                break;
            }
        }

        if (preg_match('/\b(?:di|dalam|sekitar(?:an)?|dekat|at|in|around|near)\s+(.+?)\s*$/u', $text, $placeMatch)) {
            $place = trim(preg_replace('/[?.!,;]+$/u', '', $placeMatch[1]) ?? '');
            if (isset($intent['city'])) {
                $normalizedPlace = Str::ascii(mb_strtolower($place));
                $normalizedCity = Str::ascii(mb_strtolower($intent['city']));
                if ($normalizedPlace === $normalizedCity) {
                    $place = '';
                } elseif (str_ends_with($normalizedPlace, ' '.$normalizedCity)) {
                    $place = trim(substr($place, 0, -strlen($normalizedCity)));
                }
            }
            $place = trim(preg_replace('/[?.!,;]+$/u', '', $place) ?? $place);
            if ($place !== '' && ! preg_match('/^(saya|aku|me|my location)$/u', $place)) {
                $intent['place'] = $place;
            }
        }

        if ($intent['type'] === 'list_by_region'
            && ! isset($intent['city'])
            && ! isset($intent['province'])
            && isset($intent['place'])) {
            $intent['type'] = 'mall_info';
        }

        if ($intent['type'] === 'unknown' && isset($intent['category_terms'])) {
            $intent['type'] = 'find_store';
        } elseif ($intent['type'] === 'unknown' && preg_match('/(cari|dimana|ada|toko|store|rekomendasi|find|search|where|recommend)/u', $text)) {
            $intent['type'] = 'find_store';
        } elseif ($intent['type'] === 'unknown' && preg_match('/(jam buka|jam operasional|opening hours|alamat|address|website|situs|fasilitas|facility|lantai|floor|mall|shopping center|shopping centre)/u', $text)) {
            $intent['type'] = 'mall_info';
        } elseif ($intent['type'] === 'unknown'
            && preg_match('/(about|profile|info|informasi|tentang)/u', $text)
            && preg_match('/(mall|plaza|square|center|centre|park)/u', $text)) {
            $intent['type'] = 'mall_info';
        }

        if ($intent['type'] === 'mall_info' && ! isset($intent['place'])) {
            $place = trim(preg_replace(
                '/\b(?:mall|shopping center|shopping centre|info|informasi|tentang|profil|about|profile|alamat|address|jam buka|jam operasional|opening hours|website|situs|fasilitas|facility|apa|what|is|the|of|di|in)\b/u',
                ' ',
                $text,
            ) ?? '');
            $place = trim(preg_replace('/\b(?:dan|and|for|to|me)\b/u', ' ', $place) ?? '');
            $place = trim(preg_replace('/\s+/u', ' ', $place) ?? '');
            if ($place !== '') {
                $intent['place'] = $place;
            }
        }

        return $intent;
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    protected function generateSparql(array $intent): string
    {
        $prefix = "PREFIX mm: <http://sumateramall.org/ontology#>\n"
            ."PREFIX schema: <http://schema.org/>\n";
        if ($intent['type'] === 'site_info') {
            return $prefix.<<<'SPARQL'
SELECT ?mallCount ?storeCount ?provinceCount ?categoryCount WHERE {
    { SELECT (COUNT(DISTINCT ?mall) AS ?mallCount) WHERE { ?mall a mm:Mall } }
    { SELECT (COUNT(DISTINCT ?store) AS ?storeCount) WHERE { ?store a mm:Store } }
    { SELECT (COUNT(DISTINCT ?province) AS ?provinceCount) WHERE { ?province a mm:Province } }
    { SELECT (COUNT(DISTINCT ?category) AS ?categoryCount) WHERE { ?category a mm:Category } }
}
SPARQL;
        }
        $provinceFilter = isset($intent['province'])
            ? 'FILTER(LCASE(STR(?province)) = LCASE('.$this->sparql->literal($intent['province']).'))'
            : '';
        $cityFilter = isset($intent['city'])
            ? 'FILTER(LCASE(STR(?city)) = LCASE('.$this->sparql->literal($intent['city']).'))'
            : '';
        $categoryTerms = $intent['category_terms'] ?? [];
        $categoryFilter = $categoryTerms !== []
            ? 'FILTER('.implode(' || ', array_map(
                fn (string $term): string => 'CONTAINS(LCASE(CONCAT(STR(?storeName), " ", ?categoryText, " ", ?storeCategoryText, " ", STR(?categoryResource))), LCASE('.$this->sparql->literal($term).'))',
                $categoryTerms,
            )).')'
            : '';
        $mallFilter = isset($intent['place'])
            ? 'FILTER(CONTAINS(LCASE(STR(?mallName)), LCASE('.$this->sparql->literal((string) $intent['place']).')))'
            : '';

        if ($intent['type'] === 'list_by_region') {
            return $prefix.<<<SPARQL
SELECT ?mallName ?city ?province WHERE {
    ?mall a mm:Mall ; schema:name ?mallName ; mm:inCity ?cityResource ; mm:inProvince ?provinceResource .
    ?cityResource schema:name ?city .
    ?provinceResource schema:name ?province .
    {$provinceFilter}
    {$cityFilter}
} ORDER BY ?province ?city ?mallName LIMIT 30
SPARQL;
        }

        if ($intent['type'] === 'nearest_mall') {
            return $prefix.<<<'SPARQL'
SELECT DISTINCT ?mallName ?city ?province ?address ?hours ?lat ?lng WHERE {
    ?mall a mm:Mall ; schema:name ?mallName ; mm:inCity ?cityResource ; mm:inProvince ?provinceResource ;
          schema:geo ?geo .
    ?cityResource schema:name ?city .
    ?provinceResource schema:name ?province .
    ?geo schema:latitude ?lat ; schema:longitude ?lng .
    OPTIONAL { ?mall schema:address ?address }
    OPTIONAL { ?mall mm:openingHoursText ?hours }
}
LIMIT 1000
SPARQL;
        }

        if ($intent['type'] === 'mall_info') {
            return $prefix.<<<SPARQL
SELECT DISTINCT ?mallName ?city ?province ?address ?description ?hours ?website ?wheelchair ?parking ?elevator ?floors WHERE {
    ?mall a mm:Mall ; schema:name ?mallName ; mm:inCity ?cityResource ; mm:inProvince ?provinceResource .
    ?cityResource schema:name ?city .
    ?provinceResource schema:name ?province .
    OPTIONAL { ?mall schema:address ?address }
    OPTIONAL { ?mall schema:description ?description }
    OPTIONAL { ?mall mm:openingHoursText ?hours }
    OPTIONAL { ?mall schema:url ?website }
    OPTIONAL { ?mall mm:wheelchairAccessible ?wheelchair }
    OPTIONAL { ?mall mm:hasParking ?parking }
    OPTIONAL { ?mall mm:hasElevator ?elevator }
    OPTIONAL { ?mall mm:numberOfFloors ?floors }
    {$provinceFilter}
    {$cityFilter}
    {$mallFilter}
}
ORDER BY ?mallName
LIMIT 10
SPARQL;
        }

        if ($intent['type'] !== 'find_store') {
            return $prefix.'SELECT ?storeName WHERE { FILTER(false) }';
        }

        return $prefix.<<<SPARQL
SELECT DISTINCT ?storeName ?mallName ?city ?province ?category ?storeCategory ?floor ?address ?hours ?website ?phone WHERE {
    ?store a mm:Store ; schema:name ?storeName ; mm:locatedIn ?mall ; mm:hasCategory ?categoryResource .
    ?mall schema:name ?mallName ; mm:inCity ?cityResource ; mm:inProvince ?provinceResource .
    ?cityResource schema:name ?city .
    ?provinceResource schema:name ?province .
    OPTIONAL { ?categoryResource schema:name ?category }
    OPTIONAL { ?store schema:category ?storeCategory }
    BIND(COALESCE(STR(?category), "") AS ?categoryText)
    BIND(COALESCE(STR(?storeCategory), "") AS ?storeCategoryText)
    OPTIONAL { ?store mm:floor ?floor }
    OPTIONAL { ?store schema:address ?address }
    OPTIONAL { ?store mm:openingHoursText ?hours }
    OPTIONAL { ?store schema:url ?website }
    OPTIONAL { ?store mm:nomorTelepon ?phone }
    {$provinceFilter}
    {$cityFilter}
    {$categoryFilter}
    {$mallFilter}
}
ORDER BY ?storeName
LIMIT 50
SPARQL;
    }

    /**
     * @param  array<string, mixed>  $intent
     * @param  array<int, array<string, string>>  $results
     */
    protected function formatReply(array $intent, array $results, string $locale = 'id'): string
    {
        $english = $locale === 'en';
        if ($results === []) {
            if ($intent['type'] === 'nearest_mall') {
                return $english
                    ? 'I could not find a mall with recorded coordinates near your location.'
                    : 'Saya tidak menemukan mall dengan koordinat tercatat untuk menghitung jarak dari lokasi Anda.';
            }

            return $english
                ? 'Sorry, I could not find matching data. Try different keywords.'
                : 'Maaf, saya tidak menemukan data yang sesuai. Coba kata kunci lain.';
        }

        return match ($intent['type']) {
            'site_info' => $english
                ? 'SumatraMall explores mall and store data with semantic search. It includes mall and store directories, category search, mall details, and nearest-mall lookup from your location. Current graph totals: '
                    .($results[0]['mallCount'] ?? '0').' malls, '.($results[0]['storeCount'] ?? '0').' stores, '
                    .($results[0]['provinceCount'] ?? '0').' provinces, and '.($results[0]['categoryCount'] ?? '0').' categories.'
                : 'SumatraMall membantu menjelajahi data mall dan toko melalui pencarian semantik. Chat dapat mencari mall dan toko berdasarkan kategori/lokasi, menampilkan detail mall, dan menghitung mall terdekat dari lokasi Anda. Data graph saat ini: '
                    .($results[0]['mallCount'] ?? '0').' mall, '.($results[0]['storeCount'] ?? '0').' toko, '
                    .($results[0]['provinceCount'] ?? '0').' provinsi, dan '.($results[0]['categoryCount'] ?? '0').' kategori.',
            'find_store' => ($english ? 'I found ' : 'Saya menemukan ').count($results)
                .($english ? (count($results) === 1 ? " matching store:\n\n" : " matching stores:\n\n") : " toko yang sesuai:\n\n")
                .implode("\n\n", array_map(
                    fn (array $row): string => $this->formatStoreRow($row, $english),
                    array_slice($results, 0, 15),
                ))
                .(count($results) > 15
                    ? ($english
                        ? "\n\nShowing 15 of ".count($results).' matches.'
                        : "\n\nMenampilkan 15 dari ".count($results).' hasil.')
                    : ''),
            'list_by_region' => ($english ? "Mall directory:\n\n" : "Daftar mall:\n\n").implode("\n", array_map(
                fn (array $row): string => '- '.$row['mallName'].' ('.$row['city'].', '.$row['province'].')',
                array_slice($results, 0, 20),
            )),
            'mall_info' => implode("\n\n", array_map(
                fn (array $row): string => $this->formatMallRow($row, $english),
                array_slice($results, 0, 5),
            )),
            'nearest_mall' => ($english ? 'Nearest malls by straight-line distance:\n\n' : 'Mall terdekat berdasarkan jarak garis lurus:\n\n')
                .implode("\n\n", array_map(
                    fn (array $row): string => '- '.$row['mallName'].' ('.$row['city'].', '.$row['province'].') — '
                        .number_format((float) $row['distanceKm'], 2, ',', '.').' km'
                        .(isset($row['address']) ? "\n  ".($english ? 'Address: ' : 'Alamat: ').$row['address'] : '')
                        .(isset($row['hours']) ? "\n  ".($english ? 'Hours on record: ' : 'Jam tercatat: ').$row['hours'] : ''),
                    array_slice($results, 0, 5),
                ))
                .($english ? "\n\nDistances are calculated from the coordinates recorded in the mall data." : "\n\nJarak dihitung dari koordinat yang tercatat pada data mall."),
            default => $english
                ? 'I found '.count($results).' results. Try asking a more specific question.'
                : 'Saya menemukan '.count($results).' hasil. Coba pertanyaannya lebih spesifik.',
        };
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function formatStoreRow(array $row, bool $english): string
    {
        $details = [
            $row['storeName'].(isset($row['mallName']) ? ($english ? ' — ' : ' — ').$row['mallName'] : ''),
        ];
        foreach ([
            $english ? 'Location' : 'Lokasi' => isset($row['city'], $row['province'])
                ? $row['city'].', '.$row['province']
                : ($row['city'] ?? null),
            $english ? 'Category' : 'Kategori' => $row['storeCategory'] ?? $row['category'] ?? null,
            $english ? 'Floor' : 'Lantai' => $row['floor'] ?? null,
            $english ? 'Address' : 'Alamat' => $row['address'] ?? null,
            $english ? 'Opening hours on record' : 'Jam buka tercatat' => $row['hours'] ?? null,
            $english ? 'Phone' : 'Telepon' => $row['phone'] ?? null,
            $english ? 'Website' : 'Situs' => $row['website'] ?? null,
        ] as $label => $value) {
            if ($value !== null && $value !== '') {
                $details[] = '  '.$label.': '.$value;
            }
        }

        return '- '.implode("\n", $details);
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function formatMallRow(array $row, bool $english): string
    {
        $details = [
            $row['mallName'].' — '.($english ? 'in ' : 'di ').$row['city'].', '.$row['province'],
        ];
        foreach ([
            $english ? 'Address' : 'Alamat' => $row['address'] ?? null,
            $english ? 'Opening hours on record' : 'Jam operasional tercatat' => $row['hours'] ?? null,
            $english ? 'Number of floors' : 'Jumlah lantai' => $row['floors'] ?? null,
            $english ? 'Wheelchair access' : 'Akses kursi roda' => $row['wheelchair'] ?? null,
            $english ? 'Parking' : 'Parkir' => $row['parking'] ?? null,
            $english ? 'Elevator' : 'Lift' => $row['elevator'] ?? null,
            $english ? 'Website' : 'Situs' => $row['website'] ?? null,
            $english ? 'Description' : 'Deskripsi' => $row['description'] ?? null,
        ] as $label => $value) {
            if ($value !== null && $value !== '') {
                $details[] = '  '.$label.': '.$value;
            }
        }

        return implode("\n", $details);
    }

    /**
     * @param  array<int, array<string, string>>  $malls
     * @return array<int, array<string, string>>
     */
    protected function orderMallsByDistance(array $malls, float $latitude, float $longitude): array
    {
        $malls = array_values(array_filter($malls, fn (array $mall): bool => isset($mall['lat'], $mall['lng'])
            && is_numeric($mall['lat'])
            && is_numeric($mall['lng'])
            && (float) $mall['lat'] >= -90
            && (float) $mall['lat'] <= 90
            && (float) $mall['lng'] >= -180
            && (float) $mall['lng'] <= 180));

        foreach ($malls as &$mall) {
            $mall['distanceKm'] = (string) $this->distanceInKilometers(
                $latitude,
                $longitude,
                (float) $mall['lat'],
                (float) $mall['lng'],
            );
        }
        unset($mall);

        usort($malls, fn (array $first, array $second): int => (float) $first['distanceKm'] <=> (float) $second['distanceKm']);

        return $malls;
    }

    protected function distanceInKilometers(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $earthRadiusKm = 6371;
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($longitudeDelta / 2) ** 2;

        return $earthRadiusKm * 2 * asin(min(1, sqrt($a)));
    }
}
