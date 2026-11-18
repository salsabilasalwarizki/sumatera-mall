<?php

namespace App\Http\Controllers;

use App\Services\SparqlService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MallController extends Controller
{
    public function __construct(private readonly SparqlService $sparql) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'province' => ['nullable', 'string', 'regex:/\Ahttp:\/\/sumateramall\.org\/resource\/Province\/[a-z0-9_]+\z/'],
        ]);
        $provinceFilter = isset($validated['province'])
            ? 'FILTER(?provRes = '.$this->sparql->internalResource($validated['province'], 'Province').')'
            : '';

        $malls = $this->sparql->query(<<<SPARQL
PREFIX mm: <http://sumateramall.org/ontology#>
PREFIX schema: <http://schema.org/>
SELECT DISTINCT ?mall ?name ?city ?province ?lat ?lng ?desc
WHERE {
    ?mall a mm:Mall ;
          schema:name ?name ;
          mm:inCity ?cityRes ;
          mm:inProvince ?provRes .
    ?cityRes schema:name ?city .
    ?provRes schema:name ?province .
    {$provinceFilter}
    OPTIONAL { ?mall schema:description ?desc }
    OPTIONAL {
        ?mall schema:geo ?geo .
        ?geo schema:latitude ?lat ; schema:longitude ?lng .
    }
}
ORDER BY ?province ?city ?name
SPARQL, 60);

        $provinces = $this->sparql->query(<<<'SPARQL'
PREFIX mm: <http://sumateramall.org/ontology#>
PREFIX schema: <http://schema.org/>
SELECT DISTINCT ?id ?name WHERE {
    ?id a mm:Province ; schema:name ?name .
} ORDER BY ?name
SPARQL, 3600);

        return view('malls.index', [
            'malls' => $malls,
            'grouped' => collect($malls)->groupBy('province'),
            'provinces' => $provinces,
        ]);
    }

    public function show(string $id): View
    {
        $mallIri = $this->sparql->internalResource('http://sumateramall.org/resource/Mall/'.$id, 'Mall');

        $mallResults = $this->sparql->query(<<<SPARQL
PREFIX mm: <http://sumateramall.org/ontology#>
PREFIX schema: <http://schema.org/>
SELECT ?name ?desc ?address ?website ?hours ?floors ?city ?province ?lat ?lng ?wheelchair ?parking ?elevator
WHERE {
    {$mallIri} schema:name ?name ;
              mm:inCity ?cityRes ;
              mm:inProvince ?provRes .
    ?cityRes schema:name ?city .
    ?provRes schema:name ?province .
    OPTIONAL { {$mallIri} schema:description ?desc }
    OPTIONAL { {$mallIri} schema:address ?address }
    OPTIONAL { {$mallIri} schema:url ?website }
    OPTIONAL { {$mallIri} mm:openingHoursText ?hours }
    OPTIONAL { {$mallIri} mm:numberOfFloors ?floors }
    OPTIONAL { {$mallIri} mm:wheelchairAccessible ?wheelchair }
    OPTIONAL { {$mallIri} mm:hasParking ?parking }
    OPTIONAL { {$mallIri} mm:hasElevator ?elevator }
    OPTIONAL {
        {$mallIri} schema:geo ?geo .
        ?geo schema:latitude ?lat ; schema:longitude ?lng .
    }
}
SPARQL);

        abort_if(empty($mallResults), 404);
        $mall = $mallResults[0];
        if (isset($mall['website'])) {
            $websiteScheme = parse_url($mall['website'], PHP_URL_SCHEME);
            if (! in_array(strtolower((string) $websiteScheme), ['http', 'https'], true)) {
                unset($mall['website']);
            }
        }

        $stores = $this->sparql->query(<<<SPARQL
PREFIX mm: <http://sumateramall.org/ontology#>
PREFIX schema: <http://schema.org/>
SELECT ?store ?storeName ?category ?rating ?status ?priceLevel ?floor ?image ?lat ?lng
WHERE {
    ?store a mm:Store ;
           schema:name ?storeName ;
           mm:locatedIn {$mallIri} ;
           mm:hasCategory ?catRes .
    ?catRes schema:name ?category .
    OPTIONAL { ?store mm:rating ?rating }
    OPTIONAL { ?store mm:businessStatus ?status }
    OPTIONAL { ?store mm:priceLevel ?priceLevel }
    OPTIONAL { ?store mm:floor ?floor }
    OPTIONAL { ?store schema:image ?image }
    OPTIONAL {
        ?store schema:geo ?geo .
        ?geo schema:latitude ?lat ; schema:longitude ?lng .
    }
}
ORDER BY ?category ?storeName
SPARQL);

        return view('malls.show', compact('mall', 'id', 'stores'));
    }

    public function compare(Request $request): View
    {
        $validated = $request->validate([
            'mall_ids' => ['required', 'array', 'min:2', 'max:3'],
            'mall_ids.*' => ['required', 'string', 'regex:/\A[a-z0-9_]+\z/', 'distinct'],
        ]);

        $branches = collect($validated['mall_ids'])
            ->map(fn (string $id): string => '{ BIND('.$this->sparql->internalResource('http://sumateramall.org/resource/Mall/'.$id, 'Mall').' AS ?mall) }')
            ->implode(' UNION ');

        $malls = $this->sparql->query(<<<SPARQL
PREFIX mm: <http://sumateramall.org/ontology#>
PREFIX schema: <http://schema.org/>
SELECT ?mall ?name ?city ?province ?lat ?lng ?storeCount ?categoryCount
WHERE {
    {$branches}
    ?mall schema:name ?name ;
          mm:inCity ?cityRes ;
          mm:inProvince ?provinceRes .
    ?cityRes schema:name ?city .
    ?provinceRes schema:name ?province .
    OPTIONAL {
        SELECT ?mall (COUNT(DISTINCT ?store) AS ?storeCount) WHERE {
            ?store a mm:Store ; mm:locatedIn ?mall .
        }
        GROUP BY ?mall
    }
    OPTIONAL {
        SELECT ?mall (COUNT(DISTINCT ?category) AS ?categoryCount) WHERE {
            ?store a mm:Store ; mm:locatedIn ?mall ; mm:hasCategory ?category .
        }
        GROUP BY ?mall
    }
    OPTIONAL {
        ?mall schema:geo ?geo .
        ?geo schema:latitude ?lat ; schema:longitude ?lng .
    }
}
SPARQL);

        return view('malls.compare', ['malls' => $malls]);
    }
}
