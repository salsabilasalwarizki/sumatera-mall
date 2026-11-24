<?php

namespace App\Http\Controllers;

use App\Services\SparqlService;
use App\Support\RdfImageUrl;
use Illuminate\Http\JsonResponse;
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
        $mallFilters = [];

        if (! empty($validated['city'])) {
            $cityFilter = '?cityRes = '.$this->sparql->internalResource($validated['city'], 'City');
            $filters[] = $cityFilter;
            $mallFilters[] = $cityFilter;
        }

        if (! empty($validated['mall'])) {
            $mallFilter = '?mall = '.$this->sparql->internalResource($validated['mall'], 'Mall');
            $filters[] = $mallFilter;
            $mallFilters[] = $mallFilter;
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
        $queryLimit = $searchTerm === '' ? 200 : 5000;
        $filterClause = $filters === [] ? '' : 'FILTER('.implode(' && ', $filters).')';
        $mallFilterClause = $mallFilters === [] ? '' : 'FILTER('.implode(' && ', $mallFilters).')';
        $searchCandidateFilters = $searchTerm === '' ? '' : $this->searchCandidateFilters($searchTokens);
        $mallCandidateFilters = $searchTerm === '' ? '' : $this->searchCandidateFilters($searchTokens, ['?mallName', '?city', '?address']);
        $buildQuery = fn (string $candidateFilters, string $mallCandidateFilters): string => <<<SPARQL
PREFIX mm: <http://sumateramall.org/ontology#>
PREFIX schema: <http://schema.org/>
PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>
SELECT DISTINCT ?resultType ?mallId ?store ?storeName ?mallName ?city ?category ?rating ?status ?floor ?address ?image ?lat ?lng ?priceLevel
WHERE {
    {
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
        BIND("store" AS ?resultType)
        BIND("" AS ?mallId)
    }
    UNION
    {
        ?mall a mm:Mall ;
              schema:name ?mallName ;
              mm:inCity ?cityRes .
        ?cityRes schema:name ?city .
        BIND(?mallName AS ?storeName)
        OPTIONAL { ?mall schema:address ?address }
        OPTIONAL { ?mall schema:image ?image }
        OPTIONAL {
            ?mall schema:geo ?geo .
            ?geo schema:latitude ?lat ; schema:longitude ?lng .
        }
        BIND(STRAFTER(STR(?mall), "resource/Mall/") AS ?mallId)
        BIND("mall" AS ?resultType)
        {$mallFilterClause}
        {$mallCandidateFilters}
    }
}
ORDER BY DESC(?rating) ?resultType ?storeName
LIMIT {$queryLimit}
SPARQL;
        $results = $this->sparql->query($buildQuery($searchCandidateFilters, $mallCandidateFilters), 5, 15);
        $results = array_map(static function (array $result): array {
            $result['image'] = RdfImageUrl::fromBinding($result['image'] ?? null);

            return $result;
        }, $results);

        if ($searchTerm !== '') {
            $results = $this->rankResults($results, $searchTokens);
            if ($results === [] && $searchCandidateFilters !== '') {
                $results = $this->rankResults($this->sparql->query($buildQuery('', ''), 5, 15), $searchTokens);
            }
        }
        $mallResults = array_values(array_filter($results, static fn (array $result): bool => ($result['resultType'] ?? 'store') === 'mall'));
        $storeResults = array_values(array_filter($results, static fn (array $result): bool => ($result['resultType'] ?? 'store') !== 'mall'));
        $suggestedSearch = $searchTerm !== '' && ! $this->isSingleLetterSearch($searchTokens)
            ? $this->suggestedSearch($results, $searchTokens)
            : null;

        return view('search.results', [
            'results' => $storeResults,
            'mallResults' => $mallResults,
            'count' => count($storeResults) + count($mallResults),
            'searchTerm' => $searchTerm,
            'suggestedSearch' => $suggestedSearch,
        ]);
    }

    public function checkStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);
        $name = $this->sparql->literal(trim($validated['name']));
        $exists = $this->sparql->ask(<<<SPARQL
PREFIX mm: <http://sumateramall.org/ontology#>
PREFIX schema: <http://schema.org/>
ASK {
    ?store a mm:Store ;
           schema:name {$name} .
}
SPARQL);

        return response()->json(['exists' => $exists]);
    }

    /**
     * @return list<string>
     */
    private function searchTokens(string $search): array
    {
        $tokens = array_values(array_filter(explode(' ', $this->normalizeSearchText($search))));
        $tokensWithoutStopWords = array_values(array_filter(
            $tokens,
            fn (string $token): bool => preg_match(
                '/\A(?:mall|malls|shopping|centre|center|di|ke|dari|dan|yang|the|of|and|in|at)\z/u',
                $token,
            ) !== 1,
        ));

        return $tokensWithoutStopWords !== [] ? $tokensWithoutStopWords : $tokens;
    }

    private function normalizeSearchToken(string $token): string
    {
        if (preg_match('/\p{L}/u', $token) && preg_match('/\d/u', $token)) {
            return strtr($token, ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't']);
        }

        return $token;
    }

    private function searchCandidateFilters(array $searchTokens, array $fields = ['?storeName', '?mallName', '?city', '?category', '?floor', '?address']): string
    {
        $filters = [];

        foreach ($searchTokens as $rawToken) {
            $normalizedToken = $this->normalizeSearchToken($rawToken);
            $singleLetterQuery = mb_strlen($rawToken) === 1 && preg_match('/\p{L}/u', $rawToken) === 1;
            $candidateFields = $singleLetterQuery
                ? array_values(array_filter($fields, static fn (string $field): bool => in_array($field, ['?storeName', '?mallName'], true)))
                : $fields;
            $prefixes = array_unique([
                mb_substr($rawToken, 0, 3),
                mb_substr($normalizedToken, 0, 3),
            ]);
            $alternatives = [];

            foreach ($prefixes as $prefix) {
                if ($prefix === '') {
                    continue;
                }

                foreach ($candidateFields as $field) {
                    $match = $singleLetterQuery ? 'STRSTARTS' : 'CONTAINS';
                    $alternatives[] = $match.'(LCASE(STR('.$field.')), LCASE('.$this->sparql->literal($prefix).'))';
                }
            }

            if (! $singleLetterQuery) {
                foreach ($candidateFields as $field) {
                    $alternatives[] = 'REGEX(STR('.$field.'), '.$this->sparql->literal($normalizedToken).', "i")';
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

        if ($this->isSingleLetterSearch($queryTokens)) {
            $initial = $queryTokens[0];
            $storeName = $this->normalizeSearchText($result['storeName'] ?? '');
            $mallName = $this->normalizeSearchText($result['mallName'] ?? '');

            if (str_starts_with($storeName, $initial)) {
                return 1.0;
            }

            return str_starts_with($mallName, $initial) ? 0.9 : 0.0;
        }

        $fieldWeights = [
            'storeName' => 1.0,
            'mallName' => 0.9,
            'city' => 0.85,
            'category' => 0.75,
            'address' => 0.65,
            'floor' => 0.55,
        ];
        $queryPhrase = implode(' ', $queryTokens);
        $bestScoreByToken = array_fill(0, count($queryTokens), 0.0);
        $bestFieldScore = 0.0;

        foreach ($fieldWeights as $field => $weight) {
            $fieldText = $this->normalizeSearchText($result[$field] ?? '');
            if ($fieldText === '') {
                continue;
            }

            $words = explode(' ', $fieldText);
            $fieldScores = [];

            foreach ($queryTokens as $index => $queryToken) {
                $bestWordScore = 0.0;
                foreach ($words as $word) {
                    $bestWordScore = max($bestWordScore, $this->wordMatchScore($queryToken, $word));
                }

                $weightedScore = $bestWordScore * $weight;
                $bestScoreByToken[$index] = max($bestScoreByToken[$index], $weightedScore);
                $fieldScores[] = $bestWordScore;
            }

            if (min($fieldScores) < 0.55) {
                continue;
            }

            $fieldScore = array_sum($fieldScores) / count($fieldScores);
            if ($fieldText === $queryPhrase) {
                $fieldScore = 1.0;
            } elseif (str_contains(' '.$fieldText.' ', ' '.$queryPhrase.' ')) {
                $fieldScore = max($fieldScore, 0.95);
            }

            $bestFieldScore = max($bestFieldScore, $fieldScore * $weight);
        }

        if (min($bestScoreByToken) < 0.5) {
            return 0;
        }

        return max($bestFieldScore, array_sum($bestScoreByToken) / count($bestScoreByToken));
    }

    /**
     * @param  array<int, array<string, string>>  $results
     * @param  list<string>  $queryTokens
     */
    private function suggestedSearch(array $results, array $queryTokens): ?string
    {
        $fieldWeights = [
            'storeName' => 1.0,
            'mallName' => 0.9,
            'city' => 0.85,
            'category' => 0.75,
        ];
        $bestSuggestion = null;
        $bestScore = 0.0;
        $normalizedQuery = implode(' ', $queryTokens);

        foreach ($results as $result) {
            foreach ($fieldWeights as $field => $weight) {
                $candidate = trim($result[$field] ?? '');
                $normalizedCandidate = $this->normalizeSearchText($candidate);
                if ($candidate === '' || $normalizedCandidate === '') {
                    continue;
                }

                $words = explode(' ', $normalizedCandidate);
                $scores = [];
                $hasTypo = false;
                foreach ($queryTokens as $queryToken) {
                    $bestWordScore = 0.0;
                    $bestWord = '';
                    foreach ($words as $word) {
                        $wordScore = $this->wordMatchScore($queryToken, $word);
                        if ($wordScore > $bestWordScore) {
                            $bestWordScore = $wordScore;
                            $bestWord = $word;
                        }
                    }

                    $scores[] = $bestWordScore;
                    $hasTypo = $hasTypo || (
                        $bestWordScore > 0
                        && $queryToken !== $bestWord
                        && ! str_starts_with($bestWord, $queryToken)
                        && ! (ctype_digit($queryToken) && str_contains($bestWord, $queryToken))
                    );
                }

                if (min($scores) < 0.55 || ! $hasTypo) {
                    continue;
                }

                $score = (array_sum($scores) / count($scores)) * $weight;
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestSuggestion = $candidate;
                }
            }
        }

        if ($bestSuggestion === null || $this->normalizeSearchText($bestSuggestion) === $normalizedQuery) {
            return null;
        }

        return $bestSuggestion;
    }

    /**
     * @param  list<string>  $queryTokens
     */
    private function isSingleLetterSearch(array $queryTokens): bool
    {
        return count($queryTokens) === 1
            && mb_strlen($queryTokens[0]) === 1
            && preg_match('/\p{L}/u', $queryTokens[0]) === 1;
    }

    private function normalizeSearchText(string $text): string
    {
        $normalized = mb_strtolower($text);
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized) ?? '';
        $words = array_filter(explode(' ', trim($normalized)));

        return implode(' ', array_map(
            fn (string $word): string => $this->normalizeSearchToken($word),
            $words,
        ));
    }

    private function wordMatchScore(string $queryToken, string $word): float
    {
        if ($queryToken === $word) {
            return 1.0;
        }

        if (str_starts_with($word, $queryToken)) {
            return mb_strlen($queryToken) === 1 ? 0.75 : 0.9;
        }

        if (ctype_digit($queryToken) && str_contains($word, $queryToken)) {
            return 0.8;
        }

        if (mb_strlen($queryToken) < 3) {
            return 0.0;
        }

        $length = max(strlen($queryToken), strlen($word));
        if ($length === 0 || $length > 255) {
            return 0.0;
        }

        $similarity = 1 - levenshtein($queryToken, $word) / $length;

        $minimumSimilarity = max(mb_strlen($queryToken), mb_strlen($word)) >= 5 ? 0.6 : 0.65;

        return $similarity >= $minimumSimilarity ? $similarity : 0.0;
    }
}
