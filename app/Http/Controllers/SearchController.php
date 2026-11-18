<?php

namespace App\Http\Controllers;

use App\Services\SparqlService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __construct(private readonly SparqlService $sparql) {}

    public function index(): View
    {
        $cities = $this->sparql->query(<<<'SPARQL'
PREFIX mm: <http://sumateramall.org/ontology#>
PREFIX schema: <http://schema.org/>
SELECT DISTINCT ?id ?name WHERE {
    ?id a mm:City ; schema:name ?name .
} ORDER BY ?name
SPARQL);

        $categories = $this->sparql->query(<<<'SPARQL'
PREFIX mm: <http://sumateramall.org/ontology#>
PREFIX schema: <http://schema.org/>
SELECT DISTINCT ?id ?name WHERE {
    ?id a mm:Category ; schema:name ?name .
} ORDER BY ?name
SPARQL);

        $malls = $this->sparql->query(<<<'SPARQL'
PREFIX mm: <http://sumateramall.org/ontology#>
PREFIX schema: <http://schema.org/>
SELECT ?id ?name WHERE {
    ?id a mm:Mall ; schema:name ?name .
} ORDER BY ?name
SPARQL);

        return view('search.index', compact('cities', 'categories', 'malls'));
    }

    public function filter(Request $request): View
    {
        $validated = $request->validate([
            'city' => ['nullable', 'string', 'regex:/\Ahttp:\/\/sumateramall\.org\/resource\/City\/[a-z0-9_]+\z/'],
            'mall' => ['nullable', 'string', 'regex:/\Ahttp:\/\/sumateramall\.org\/resource\/Mall\/[a-z0-9_]+\z/'],
            'category' => ['nullable', 'string', 'regex:/\Ahttp:\/\/sumateramall\.org\/resource\/Category\/[a-z0-9_]+\z/'],
            'open_now' => ['sometimes', 'boolean'],
            'operational' => ['sometimes', 'boolean'],
            'min_rating' => ['nullable', 'numeric', 'between:0,5'],
            'price_level' => ['nullable', 'integer', 'between:1,4'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $filters = [];

        if (! empty($validated['city'])) {
            $filters[] = '?cityRes = '.$this->sparql->internalResource($validated['city'], 'City');
        }

        if (! empty($validated['mall'])) {
            $filters[] = '?mall = '.$this->sparql->internalResource($validated['mall'], 'Mall');
        }

        if (! empty($validated['category'])) {
            $filters[] = '?catRes = '.$this->sparql->internalResource($validated['category'], 'Category');
        }

        if (isset($validated['min_rating']) && $validated['min_rating'] !== '') {
            $filters[] = '?rating >= '.(float) $validated['min_rating'];
        }

        if (isset($validated['price_level']) && $validated['price_level'] !== '') {
            $filters[] = '?priceLevel <= '.(int) $validated['price_level'];
        }

        if ($request->boolean('operational')) {
            $filters[] = '?status = "OPERATIONAL"';
        }

        $extraPatterns = '';
        if ($request->boolean('open_now')) {
            $now = now('Asia/Jakarta');
            $day = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$now->dayOfWeek];
            $extraPatterns = <<<SPARQL
    ?store schema:openingHoursSpecification ?spec .
    ?spec schema:dayOfWeek <http://schema.org/{$day}> ;
          schema:opens ?opens ;
          schema:closes ?closes .
    FILTER(
        ?opens != ?closes &&
        (
            ("{$now->format('H:i:s')}"^^xsd:time >= ?opens && "{$now->format('H:i:s')}"^^xsd:time < ?closes) ||
            (?opens > ?closes && ("{$now->format('H:i:s')}"^^xsd:time >= ?opens || "{$now->format('H:i:s')}"^^xsd:time < ?closes))
        )
    )
SPARQL;
        }

        $searchTerm = trim((string) ($validated['search'] ?? ''));
        $searchTokens = $this->searchTokens($searchTerm);
        if ($searchTokens === []) {
            $searchTerm = '';
        }
        $queryLimit = $searchTerm === '' ? 200 : 2000;
        $filterClause = $filters === [] ? '' : 'FILTER('.implode(' && ', $filters).')';
        $searchCandidateFilters = $searchTerm === '' ? '' : $this->searchCandidateFilters($searchTerm);
        $buildQuery = fn (string $candidateFilters): string => <<<SPARQL
PREFIX mm: <http://sumateramall.org/ontology#>
PREFIX schema: <http://schema.org/>
PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>
SELECT DISTINCT ?store ?storeName ?mallName ?city ?category ?rating ?status ?floor ?address ?image ?lat ?lng ?priceLevel
WHERE {
    ?store a mm:Store ;
           schema:name ?storeName ;
           mm:locatedIn ?mall ;
           mm:hasCategory ?catRes .
    ?mall schema:name ?mallName ;
          mm:inCity ?cityRes .
    ?cityRes schema:name ?city .
    ?catRes schema:name ?category .
    OPTIONAL { ?store mm:rating ?rating }
    OPTIONAL { ?store mm:businessStatus ?status }
    OPTIONAL { ?store mm:floor ?floor }
    OPTIONAL { ?store mm:priceLevel ?priceLevel }
    OPTIONAL { ?store schema:image ?image }
    OPTIONAL { ?mall schema:address ?address }
    OPTIONAL {
        ?store schema:geo ?geo .
        ?geo schema:latitude ?lat ; schema:longitude ?lng .
    }
    {$extraPatterns}
    {$filterClause}
    {$candidateFilters}
}
ORDER BY DESC(?rating) ?storeName
LIMIT {$queryLimit}
SPARQL;
        $results = $this->sparql->query($buildQuery($searchCandidateFilters), 5, 15);

        if ($searchTerm !== '') {
            $results = $this->rankResults($results, $searchTokens);
            if ($results === [] && $searchCandidateFilters !== '') {
                $results = $this->rankResults($this->sparql->query($buildQuery(''), 5, 15), $searchTokens);
            }
        }

        return view('search.results', [
            'results' => $results,
            'count' => count($results),
            'searchTerm' => $searchTerm,
        ]);
    }

    /**
     * @return list<string>
     */
    private function searchTokens(string $search): array
    {
        $normalized = mb_strtolower($search);
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized) ?? '';

        return array_values(array_map(
            fn (string $token): string => $this->normalizeSearchToken($token),
            array_filter(explode(' ', trim($normalized))),
        ));
    }

    private function normalizeSearchToken(string $token): string
    {
        if (preg_match('/\p{L}/u', $token) && preg_match('/\d/u', $token)) {
            return strtr($token, ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't']);
        }

        return $token;
    }

    private function searchCandidateFilters(string $search): string
    {
        $rawTokens = mb_strtolower($search);
        $rawTokens = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $rawTokens) ?? '';
        $fields = ['?storeName', '?mallName', '?city', '?category', '?floor', '?address'];
        $filters = [];

        foreach (array_filter(explode(' ', trim($rawTokens))) as $rawToken) {
            $normalizedToken = $this->normalizeSearchToken($rawToken);
            $prefixes = array_unique([
                mb_substr($rawToken, 0, 3),
                mb_substr($normalizedToken, 0, 3),
            ]);
            $alternatives = [];

            foreach ($prefixes as $prefix) {
                if (mb_strlen($prefix) < 2) {
                    continue;
                }

                foreach ($fields as $field) {
                    $alternatives[] = 'CONTAINS(LCASE(STR('.$field.')), LCASE('.$this->sparql->literal($prefix).'))';
                }
            }

            if ($alternatives !== []) {
                $filters[] = 'FILTER('.implode(' || ', $alternatives).')';
            }
        }

        return implode("\n    ", $filters);
    }

    /**
     * @param  array<int, array<string, string>>  $results
     * @param  list<string>  $queryTokens
     * @return array<int, array<string, string>>
     */
    private function rankResults(array $results, array $queryTokens): array
    {
        return collect($results)
            ->map(function (array $result) use ($queryTokens): array {
                $result['_search_score'] = $this->fuzzyMatchScore($queryTokens, $result);

                return $result;
            })
            ->filter(fn (array $result): bool => $result['_search_score'] >= 0.55)
            ->sort(function (array $left, array $right): int {
                $scoreOrder = $right['_search_score'] <=> $left['_search_score'];

                return $scoreOrder !== 0
                    ? $scoreOrder
                    : (float) ($right['rating'] ?? 0) <=> (float) ($left['rating'] ?? 0);
            })
            ->take(200)
            ->values()
            ->map(function (array $result): array {
                unset($result['_search_score']);

                return $result;
            })
            ->all();
    }

    /**
     * @param  list<string>  $queryTokens
     * @param  array<string, string>  $result
     */
    private function fuzzyMatchScore(array $queryTokens, array $result): float
    {
        if ($queryTokens === []) {
            return 0;
        }

        $fields = ['storeName', 'mallName', 'city', 'category', 'floor', 'address'];
        $text = mb_strtolower(implode(' ', array_map(
            fn (string $field): string => $result[$field] ?? '',
            $fields,
        )));
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? '';
        $words = array_map(
            fn (string $word): string => $this->normalizeSearchToken($word),
            array_filter(explode(' ', trim($text))),
        );
        $scores = [];

        foreach ($queryTokens as $queryToken) {
            $bestScore = 0.0;
            foreach ($words as $word) {
                if ($queryToken === $word) {
                    $bestScore = 1.0;
                    break;
                }

                $length = max(strlen($queryToken), strlen($word));
                if ($length === 0 || $length > 255) {
                    continue;
                }

                $bestScore = max($bestScore, 1 - levenshtein($queryToken, $word) / $length);
            }

            if ($bestScore < 0.55) {
                return 0;
            }

            $scores[] = $bestScore;
        }

        return array_sum($scores) / count($scores);
    }
}
