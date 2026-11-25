<?php

use App\Models\ChatLog;
use App\Services\OsmService;
use App\Services\SparqlService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('the homepage shows open malls and provides a store search scoped to a mall', function () {
    $this->travelTo(Carbon::parse('2026-11-14 15:00:00', 'Asia/Jakarta'));
    Http::fake([
        '*' => Http::response([
            'results' => ['bindings' => [
                [
                    'mall' => ['value' => 'http://sumateramall.org/resource/Mall/open_plaza'],
                    'name' => ['value' => 'Open Plaza'],
                    'city' => ['value' => 'Medan'],
                    'province' => ['value' => 'Sumatera Utara'],
                    'hours' => ['value' => '10.00 - 22.00'],
                    'image' => ['value' => 'http://localhost:8000/images/malls/open-plaza.jpg'],
                    'lat' => ['value' => '3.5952'],
                    'lng' => ['value' => '98.6722'],
                ],
                [
                    'mall' => ['value' => 'http://sumateramall.org/resource/Mall/closed_plaza'],
                    'name' => ['value' => 'Closed Plaza'],
                    'city' => ['value' => 'Medan'],
                    'province' => ['value' => 'Sumatera Utara'],
                    'hours' => ['value' => '09.00 - 14.00'],
                ],
            ]],
        ], 200),
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Open Plaza')
        ->assertSee('src="'.asset('images/malls/open-plaza.jpg').'"', false)
        ->assertSee('data-lat="3.5952"', false)
        ->assertSee('data-lng="98.6722"', false)
        ->assertDontSee('<h3>Closed Plaza</h3>', false)
        ->assertSee('name="search"', false)
        ->assertSee('name="mall"', false)
        ->assertSee('mall-city-filter', false)
        ->assertSee('Asisten SumatraMall')
        ->assertSee('data-theme-choice="light"', false)
        ->assertSee('data-theme-choice="dark"', false)
        ->assertSee('id="site-language"', false)
        ->assertSee('value="es"', false)
        ->assertSee('data-filter-dock', false)
        ->assertSee('data-voice-search', false)
        ->assertSee('images/logo-sumateramall.png');
});

test('the mall directory renders local photos returned by Fuseki', function () {
    Http::fake([
        '*' => Http::response([
            'results' => ['bindings' => [[
                'mall' => ['value' => 'http://sumateramall.org/resource/Mall/sun_plaza'],
                'name' => ['value' => 'Sun Plaza'],
                'city' => ['value' => 'Medan'],
                'province' => ['value' => 'Sumatera Utara'],
                'image' => ['value' => 'http://localhost:8000/images/malls/sun-plaza.png'],
            ]]],
        ], 200),
    ]);

    $this->get('/malls')
        ->assertOk()
        ->assertSee('Sun Plaza')
        ->assertSee('src="'.asset('images/malls/sun-plaza.png').'"', false);
});

test('the shared navigation only includes home, team, and typo-tolerant store search', function () {
    Http::fake([
        '*' => Http::response(['results' => ['bindings' => []]], 200),
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('href="'.route('team').'"', false)
        ->assertSee('id="site-search"', false)
        ->assertSee('name="search"', false)
        ->assertDontSee('href="'.route('malls.index').'"', false)
        ->assertDontSee('href="'.route('planner.index').'"', false);
});

test('the team page shows member profiles and available team photos', function () {
    $response = $this->get('/team')->assertOk();

    expect(substr_count($response->getContent(), 'team-card__photo--empty'))->toBe(4)
        ->and(substr_count($response->getContent(), 'team_member_placeholder'))->toBe(0);

    $response->assertSee('SPARQL')
        ->assertSee('Salsabila Salwa Rizki')
        ->assertSee('251402123')
        ->assertSee('Jesqueen Maria Purba')
        ->assertSee('Fadila Lisma Sari')
        ->assertSee('Syifa Nazira')
        ->assertSee('Jelita Hati Sinurat')
        ->assertSee('images/teams/salsabilasalwa.png');
});

test('advanced search exposes category, hours, rating, price, and voice filters', function () {
    Http::fake([
        '*' => Http::response(['results' => ['bindings' => []]], 200),
    ]);

    $this->get('/search')
        ->assertOk()
        ->assertSee('id="advanced-search-form"', false)
        ->assertSee('name="province"', false)
        ->assertSee('name="category"', false)
        ->assertSee('name="open_now"', false)
        ->assertSee('name="min_rating"', false)
        ->assertSee('name="price_level"', false)
        ->assertSee('name="floor"', false)
        ->assertSee('name="operational"', false)
        ->assertSee('data-voice-input="search-store-input"', false);
});

test('the landing store search applies the selected mall filter', function () {
    Http::fake([
        '*' => Http::response([
            'results' => ['bindings' => [[
                'storeName' => ['value' => 'Coffee House'],
                'mallName' => ['value' => 'Sun Plaza'],
                'city' => ['value' => 'Medan'],
                'category' => ['value' => 'Cafe'],
            ]]],
        ], 200),
    ]);

    $mall = 'http://sumateramall.org/resource/Mall/sun_plaza';

    $this->get('/search/filter?search=Coffee&mall='.urlencode($mall))
        ->assertOk()
        ->assertSee('Coffee House')
        ->assertSee('Sun Plaza');

    Http::assertSent(fn ($request): bool => str_contains(
        rawurldecode($request->url()),
        '?mall = <http://sumateramall.org/resource/Mall/sun_plaza>',
    ));
});

test('store search returns relevant results when the query contains a typo', function () {
    Http::fake([
        '*' => Http::response([
            'results' => ['bindings' => [
                [
                    'store' => ['value' => 'store/coffee'],
                    'storeName' => ['value' => 'Coffee House'],
                    'mallName' => ['value' => 'Sun Plaza'],
                    'city' => ['value' => 'Medan'],
                    'category' => ['value' => 'Cafe'],
                    'rating' => ['value' => '4.8'],
                    'status' => ['value' => 'OPERATIONAL'],
                ],
                [
                    'store' => ['value' => 'store/pizza'],
                    'storeName' => ['value' => 'Pizza Corner'],
                    'mallName' => ['value' => 'Sun Plaza'],
                    'city' => ['value' => 'Medan'],
                    'category' => ['value' => 'Restaurant'],
                    'rating' => ['value' => '4.9'],
                    'status' => ['value' => 'OPERATIONAL'],
                ],
            ]],
        ], 200),
    ]);

    $this->get('/search/filter?search=cofee')
        ->assertOk()
        ->assertSee('Coffee House')
        ->assertDontSee('Pizza Corner')
        ->assertSee('cofee');
});

test('store search normalizes leetspeak typos in mall names', function () {
    Http::fake([
        '*' => Http::response([
            'results' => ['bindings' => [
                [
                    'store' => ['value' => 'store/cafe'],
                    'storeName' => ['value' => 'Coffee House'],
                    'mallName' => ['value' => 'Sun Plaza'],
                    'city' => ['value' => 'Medan'],
                    'category' => ['value' => 'Cafe'],
                ],
                [
                    'store' => ['value' => 'store/other'],
                    'storeName' => ['value' => 'Coffee House'],
                    'mallName' => ['value' => 'Moon Plaza'],
                    'city' => ['value' => 'Medan'],
                    'category' => ['value' => 'Cafe'],
                ],
            ]],
        ], 200),
    ]);

    $this->get('/search/filter?search=sun%20pl4ZA')
        ->assertOk()
        ->assertSee('Sun Plaza')
        ->assertDontSee('Moon Plaza')
        ->assertSee('sun pl4ZA');

    Http::assertSent(fn ($request): bool => str_contains(
        rawurldecode($request->url()),
        'LCASE("pla")',
    ) && str_contains(
        rawurldecode($request->url()),
        'LIMIT 5000',
    ));
});

test('store search ranks a store-name match above a mall-name match', function () {
    Http::fake([
        '*' => Http::response([
            'results' => ['bindings' => [
                [
                    'store' => ['value' => 'store/mall-match'],
                    'storeName' => ['value' => 'Coffee House'],
                    'mallName' => ['value' => 'Sun Plaza'],
                    'city' => ['value' => 'Medan'],
                    'category' => ['value' => 'Cafe'],
                    'rating' => ['value' => '4.9'],
                ],
                [
                    'store' => ['value' => 'store/store-match'],
                    'storeName' => ['value' => 'Sun Plaza Cafe'],
                    'mallName' => ['value' => 'North Point'],
                    'city' => ['value' => 'Medan'],
                    'category' => ['value' => 'Cafe'],
                    'rating' => ['value' => '4.1'],
                ],
            ]],
        ], 200),
    ]);

    $this->get('/search/filter?search=Sun%20Plaza')
        ->assertOk()
        ->assertSeeInOrder(['Sun Plaza Cafe', 'Coffee House']);
});

test('mall and store entities both appear in semantic search results', function () {
    Http::fake([
        '*' => Http::response([
            'results' => ['bindings' => [
                [
                    'resultType' => ['value' => 'store'],
                    'store' => ['value' => 'store/cafe'],
                    'mallId' => ['value' => ''],
                    'storeName' => ['value' => 'Coffee House'],
                    'mallName' => ['value' => 'Sun Plaza'],
                    'city' => ['value' => 'Medan'],
                    'category' => ['value' => 'Cafe'],
                ],
                [
                    'resultType' => ['value' => 'mall'],
                    'mallId' => ['value' => 'sun_plaza'],
                    'storeName' => ['value' => 'Sun Plaza'],
                    'mallName' => ['value' => 'Sun Plaza'],
                    'city' => ['value' => 'Medan'],
                    'province' => ['value' => 'Sumatera Utara'],
                ],
            ]],
        ], 200),
    ]);

    $this->get('/search/filter?search=Sun%20Plaza')
        ->assertOk()
        ->assertSee('Mall yang cocok')
        ->assertSee('Coffee House')
        ->assertSee('href="'.route('malls.show', ['id' => 'sun_plaza']).'"', false);

});

test('fuzzy fallback also returns the matching mall entity', function () {
    Cache::flush();
    Http::fakeSequence()
        ->push(['results' => ['bindings' => []]], 200)
        ->push(['results' => ['bindings' => [[
            'resultType' => ['value' => 'mall'],
            'mallId' => ['value' => 'plaza_medan_fair'],
            'storeName' => ['value' => 'Plaza Medan Fair'],
            'mallName' => ['value' => 'Plaza Medan Fair'],
            'city' => ['value' => 'Medan'],
            'province' => ['value' => 'Sumatera Utara'],
        ]]]], 200);

    $this->get('/search/filter?search=Pluza%20Muden%20Pair')
        ->assertOk()
        ->assertSee('Plaza Medan Fair')
        ->assertSee('href="'.route('malls.show', ['id' => 'plaza_medan_fair']).'"', false);
});

test('store search uses case-insensitive SPARQL regex matching', function () {
    Http::fake([
        '*' => Http::response(['results' => ['bindings' => []]], 200),
    ]);

    $this->get('/search/filter?search=Burger')->assertOk();

    Http::assertSent(fn ($request): bool => str_contains(
        rawurldecode($request->url()),
        'REGEX(STR(?storeName), "burger", "i")',
    ));
});

test('store existence uses a parameterized SPARQL ASK query and returns its boolean result', function () {
    Http::fake([
        '*' => Http::response(['head' => (object) [], 'boolean' => true], 200),
    ]);

    $this->postJson('/search/exists', ['name' => 'UNIQLO'])
        ->assertOk()
        ->assertJsonPath('exists', true);

    Http::assertSent(fn ($request): bool => str_contains(
        rawurldecode($request->url()),
        'ASK {',
    ) && str_contains(
        rawurldecode($request->url()),
        'schema:name "UNIQLO" .',
    ));
});

test('store search returns relevant prefix matches for a single-character query', function () {
    Http::fake([
        '*' => Http::response([
            'results' => ['bindings' => [
                [
                    'store' => ['value' => 'store/cafe'],
                    'storeName' => ['value' => 'Cafe Example'],
                    'mallName' => ['value' => 'Sun Plaza'],
                    'city' => ['value' => 'Medan'],
                    'category' => ['value' => 'Cafe'],
                ],
                [
                    'store' => ['value' => 'store/books'],
                    'storeName' => ['value' => 'Book Corner'],
                    'mallName' => ['value' => 'Moon Plaza'],
                    'city' => ['value' => 'Medan'],
                    'category' => ['value' => 'Books'],
                ],
                [
                    'store' => ['value' => 'store/address-only'],
                    'storeName' => ['value' => 'Corner Books'],
                    'mallName' => ['value' => 'Moon Plaza'],
                    'city' => ['value' => 'Medan'],
                    'category' => ['value' => 'Retail'],
                    'address' => ['value' => 'South Avenue'],
                ],
            ]],
        ], 200),
    ]);

    $this->get('/search/filter?search=s')
        ->assertOk()
        ->assertSee('Cafe Example')
        ->assertDontSee('Book Corner')
        ->assertDontSee('Corner Books');

    Http::assertSent(fn ($request): bool => str_contains(
        rawurldecode($request->url()),
        'STRSTARTS(LCASE(STR(?mallName)), LCASE("s"))',
    ));
});

test('a single-letter search matches names that start with that letter', function () {
    Http::fake([
        '*' => Http::response([
            'results' => ['bindings' => [
                [
                    'store' => ['value' => 'store/alpha'],
                    'storeName' => ['value' => 'Alpha Store'],
                    'mallName' => ['value' => 'Central Plaza'],
                    'city' => ['value' => 'Aceh'],
                    'category' => ['value' => 'Retail'],
                ],
                [
                    'store' => ['value' => 'store/inside-alpha-mall'],
                    'storeName' => ['value' => 'Cafe Example'],
                    'mallName' => ['value' => 'A Mall'],
                    'city' => ['value' => 'Medan'],
                    'category' => ['value' => 'Cafe'],
                ],
                [
                    'store' => ['value' => 'store/ends-in-a'],
                    'storeName' => ['value' => 'Cafe A'],
                    'mallName' => ['value' => 'Central Plaza'],
                    'city' => ['value' => 'Aceh'],
                    'category' => ['value' => 'Cafe'],
                ],
            ]],
        ], 200),
    ]);

    $this->get('/search/filter?search=A')
        ->assertOk()
        ->assertSee('Alpha Store')
        ->assertSee('Cafe Example')
        ->assertDontSee('Cafe A');

    Http::assertSent(fn ($request): bool => str_contains(
        rawurldecode($request->url()),
        'STRSTARTS(LCASE(STR(?storeName)), LCASE("a"))',
    ));
});

test('search ignores common words but preserves a query made only of ignored words', function () {
    Http::fake([
        '*' => Http::response(['results' => ['bindings' => []]], 200),
    ]);

    $this->get('/search/filter?search=Mall%20Plaza')->assertOk();

    Http::assertSent(fn ($request): bool => str_contains(
        rawurldecode($request->url()),
        'LCASE("pla")',
    ) && ! str_contains(
        rawurldecode($request->url()),
        'LCASE("mal")',
    ));
});

test('typoed searches suggest the best matching mall name', function () {
    Http::fake([
        '*' => Http::response([
            'results' => ['bindings' => [[
                'store' => ['value' => 'store/coffee'],
                'storeName' => ['value' => 'Coffee House'],
                'mallName' => ['value' => 'Plaza Medan Fair'],
                'city' => ['value' => 'Medan'],
                'category' => ['value' => 'Cafe'],
                'rating' => ['value' => '4.8'],
            ]]],
        ], 200),
    ]);

    $this->get('/search/filter?search=Pluza%20Muden%20Pair')
        ->assertOk()
        ->assertSee('Mungkin yang Anda maksud adalah')
        ->assertSee('Plaza Medan Fair');
});

test('store search finds numeric identifiers and typoed city names', function () {
    Http::fakeSequence()
        ->push(['results' => ['bindings' => [
            [
                'store' => ['value' => 'store/twenty-four'],
                'storeName' => ['value' => 'Store 24'],
                'mallName' => ['value' => 'Central Plaza'],
                'city' => ['value' => 'Medan'],
                'category' => ['value' => 'Retail'],
            ],
            [
                'store' => ['value' => 'store/forty-five'],
                'storeName' => ['value' => 'Store 45'],
                'mallName' => ['value' => 'Central Plaza'],
                'city' => ['value' => 'Jakarta'],
                'category' => ['value' => 'Retail'],
            ],
        ]]], 200)
        ->push(['results' => ['bindings' => [
            [
                'store' => ['value' => 'store/medan'],
                'storeName' => ['value' => 'Cafe Example'],
                'mallName' => ['value' => 'Sun Plaza'],
                'city' => ['value' => 'Medan'],
                'category' => ['value' => 'Cafe'],
            ],
            [
                'store' => ['value' => 'store/jakarta'],
                'storeName' => ['value' => 'Book Corner'],
                'mallName' => ['value' => 'Moon Plaza'],
                'city' => ['value' => 'Jakarta'],
                'category' => ['value' => 'Books'],
            ],
        ]]], 200);

    $this->get('/search/filter?search=2')
        ->assertOk()
        ->assertSee('Store 24')
        ->assertDontSee('Store 45');

    $this->get('/search/filter?search=medn')
        ->assertOk()
        ->assertSee('Cafe Example')
        ->assertDontSee('Book Corner');
});

test('store search applies opening, rating, price, and operational filters in Fuseki', function () {
    Http::fake([
        '*' => Http::response(['results' => ['bindings' => []]], 200),
    ]);

    $this->get('/search/filter?open_now=1&min_rating=4.5&price_level=2&operational=1')
        ->assertOk();

    Http::assertSent(fn ($request): bool => str_contains(
        rawurldecode($request->url()),
        'schema:openingHoursSpecification',
    ) && str_contains(
        rawurldecode($request->url()),
        '?rating >= 4.5',
    ) && str_contains(
        rawurldecode($request->url()),
        '?priceLevel <= 2',
    ) && str_contains(
        rawurldecode($request->url()),
        '?status = "OPERATIONAL"',
    ));
});

test('store search filters by floor and shows the recorded floor in results', function () {
    Http::fake([
        '*' => Http::response([
            'results' => ['bindings' => [[
                'store' => ['value' => 'http://sumateramall.org/resource/Store/example_cafe'],
                'storeName' => ['value' => 'Example Cafe'],
                'mallName' => ['value' => 'Sun Plaza'],
                'city' => ['value' => 'Medan'],
                'province' => ['value' => 'Sumatera Utara'],
                'category' => ['value' => 'Cafe'],
                'floor' => ['value' => 'LG'],
            ]]],
        ], 200),
    ]);

    $this->get('/search/filter?search=Toko+di+lantai+LG&floor=LG')
        ->assertOk()
        ->assertSee('Example Cafe')
        ->assertSee('data-i18n="store_floor_label"', false)
        ->assertSee('LG')
        ->assertSee('data-floor="LG"', false);

    Http::assertSent(fn ($request): bool => str_contains(
        rawurldecode($request->url()),
        'CONTAINS(LCASE(STR(?floor)), LCASE("LG"))',
    ));
});

test('store and mall search results can be scoped to a province', function () {
    Http::fake([
        '*' => Http::response(['results' => ['bindings' => []]], 200),
    ]);

    $province = 'http://sumateramall.org/resource/Province/sumatera_utara';

    $this->get('/search/filter?province='.urlencode($province))->assertOk();

    Http::assertSent(fn ($request): bool => str_contains(
        rawurldecode($request->url()),
        '?provinceRes = <'.$province.'>',
    ) && str_contains(
        rawurldecode($request->url()),
        'mm:inProvince ?provinceRes',
    ));
});

test('the mall map lists address-based locations without requiring stored coordinates', function () {
    Http::fake([
        '*' => Http::response([
            'results' => ['bindings' => [[
                'id' => ['value' => 'sun_plaza'],
                'name' => ['value' => 'Sun Plaza'],
                'city' => ['value' => 'Medan'],
                'province' => ['value' => 'Sumatera Utara'],
                'address' => ['value' => 'Jl. Zainul Arifin'],
            ]]],
        ], 200),
    ]);

    $this->get('/map')
        ->assertOk()
        ->assertSee('Sun Plaza')
        ->assertSee('id="map-use-location"', false)
        ->assertSee('data-map-directions', false)
        ->assertSee('data-locate-mall', false)
        ->assertSee('openstreetmap.org/search', false);
});

test('mall details reject non-http website schemes from RDF', function () {
    Http::fake([
        '*' => Http::response([
            'results' => ['bindings' => [[
                'name' => ['value' => 'Sun Plaza'],
                'city' => ['value' => 'Medan'],
                'province' => ['value' => 'Sumatera Utara'],
                'address' => ['value' => 'Jl. Zainul Arifin'],
                'website' => ['value' => 'javascript:alert(1)'],
                'image' => ['value' => 'https://cdn.example.test/cafe-logo.png'],
                'storeName' => ['value' => 'Cafe Example'],
                'category' => ['value' => 'Food & Beverage'],
            ]]],
        ], 200),
    ]);

    $this->get('/malls/sun_plaza')
        ->assertOk()
        ->assertSee('mall-map', false)
        ->assertSee('Cafe Example')
        ->assertSee('src="https://cdn.example.test/cafe-logo.png"', false)
        ->assertDontSee('javascript:');
});

test('invalid resource filters are rejected before reaching Fuseki', function () {
    Http::fake();

    $this->postJson('/search/filter', [
        'city' => 'http://sumateramall.org/resource/City/x> } UNION { ?s ?p ?o',
    ])->assertUnprocessable();

    Http::assertNothingSent();
});

test('chat conversations can be stored after the explorer schema migration', function () {
    Http::fake([
        '*' => Http::response(['results' => ['bindings' => []]], 200),
    ]);

    $this->postJson('/chatbot', ['message' => 'cari cafe di Sun Plaza'])
        ->assertOk()
        ->assertJsonPath('results_count', 0);

    expect(ChatLog::query()->count())->toBe(1);
});

test('the chatbot rejects questions outside the SumatraMall context without querying Fuseki', function () {
    Http::fake([
        '*' => Http::response(['results' => ['bindings' => []]], 200),
    ]);

    $response = $this->postJson('/chatbot', ['message' => 'Tell me a joke about outer space'])
        ->assertOk()
        ->assertJsonPath('intent.type', 'unknown');
    expect($response->json('reply'))->toContain('Saya bisa menjawab');

    Http::assertNothingSent();
});

test('the chatbot does not invent answers for ratings and price levels missing from the graph', function () {
    Http::fake([
        '*' => Http::response(['results' => ['bindings' => []]], 200),
    ]);

    foreach (['top rated stores', 'budget mall Sun Plaza'] as $message) {
        $response = $this->postJson('/chatbot', ['message' => $message])
            ->assertOk()
            ->assertJsonPath('intent.type', 'unsupported_data')
            ->assertJsonPath('results_count', 0);

        expect($response->json('reply'))
            ->toContain('Graph saat ini tidak mencatat rating toko atau tingkat harga')
            ->toContain('Saya bisa menjawab');
    }

    Http::assertNothingSent();
});

test('the chatbot explains website features using live semantic graph counts', function () {
    Http::fake([
        '*' => Http::response([
            'results' => ['bindings' => [[
                'mallCount' => ['value' => '40'],
                'storeCount' => ['value' => '120'],
                'provinceCount' => ['value' => '10'],
                'categoryCount' => ['value' => '8'],
            ]]],
        ], 200),
    ]);

    $this->postJson('/chatbot', ['message' => 'Fitur apa yang ada di website ini?'])
        ->assertOk()
        ->assertJsonPath('intent.type', 'site_info')
        ->assertJsonPath('results_count', 1)
        ->assertJsonPath('reply', 'SumatraMall membantu menjelajahi data mall dan toko melalui pencarian semantik. Chat dapat mencari mall dan toko berdasarkan kategori/lokasi, menampilkan detail mall, dan menghitung mall terdekat dari lokasi Anda. Data graph saat ini: 40 mall, 120 toko, 10 provinsi, dan 8 kategori.');

    Http::assertSent(fn ($request): bool => str_contains(
        rawurldecode($request->url()),
        'COUNT(DISTINCT ?mall)',
    ) && str_contains(
        rawurldecode($request->url()),
        'COUNT(DISTINCT ?store)',
    ));
});

test('the chatbot understands English store searches and responds in English', function () {
    Http::fake([
        '*' => Http::response([
            'results' => ['bindings' => [[
                'storeName' => ['value' => 'Cafe Example'],
                'mallName' => ['value' => 'Sun Plaza'],
                'city' => ['value' => 'Medan'],
                'province' => ['value' => 'Sumatera Utara'],
                'storeCategory' => ['value' => 'Cafe'],
                'floor' => ['value' => 'GF'],
                'hours' => ['value' => '10.00 - 22.00'],
                'website' => ['value' => 'https://example.test'],
            ]]],
        ], 200),
    ]);

    $response = $this->postJson('/chatbot', [
        'message' => 'find a cafe in Sun Plaza',
        'locale' => 'en',
    ])->assertOk()
        ->assertJsonPath('intent.type', 'find_store')
        ->assertJsonPath('results_count', 1);
    expect($response->json('reply'))
        ->toContain('Cafe Example')
        ->toContain('Sun Plaza')
        ->toContain('Category: Cafe')
        ->toContain('Floor: GF')
        ->toContain('Opening hours on record: 10.00 - 22.00');

    Http::assertSent(fn ($request): bool => str_contains(
        rawurldecode($request->url()),
        'CONTAINS(LCASE(STR(?mallName)), LCASE("sun plaza"))',
    ) && str_contains(
        rawurldecode($request->url()),
        'CONTAINS(LCASE(CONCAT(STR(?storeName)',
    ));
});

test('the chatbot finds the nearest malls from browser coordinates using TTL data', function () {
    Http::fake([
        '*' => Http::response([
            'results' => ['bindings' => [
                [
                    'mallName' => ['value' => 'Mall B'],
                    'city' => ['value' => 'Medan'],
                    'province' => ['value' => 'Sumatera Utara'],
                    'lat' => ['value' => '3.60'],
                    'lng' => ['value' => '98.69'],
                ],
                [
                    'mallName' => ['value' => 'Mall A'],
                    'city' => ['value' => 'Medan'],
                    'province' => ['value' => 'Sumatera Utara'],
                    'lat' => ['value' => '3.581'],
                    'lng' => ['value' => '98.680'],
                ],
            ]],
        ], 200),
    ]);

    $response = $this->postJson('/chatbot', [
        'message' => 'Mall paling dekat dengan saya',
        'latitude' => 3.58,
        'longitude' => 98.67,
    ])->assertOk()->assertJsonPath('intent.type', 'nearest_mall');

    expect($response->json('reply'))->toStartWith('Mall terdekat berdasarkan jarak garis lurus:')
        ->toContain('Mall A')
        ->toContain('Mall B');
    expect(strpos($response->json('reply'), 'Mall A'))
        ->toBeLessThan(strpos($response->json('reply'), 'Mall B'));

    Http::assertSent(fn ($request): bool => str_contains(
        rawurldecode($request->url()),
        'schema:latitude ?lat ; schema:longitude ?lng',
    ));
});

test('the chatbot asks for location permission before nearest-mall lookup', function () {
    Http::fake();

    $this->postJson('/chatbot', ['message' => 'Mall paling dekat dengan saya'])
        ->assertOk()
        ->assertJsonPath('intent.type', 'nearest_mall')
        ->assertJsonFragment(['reply' => 'Izinkan akses lokasi di browser, lalu tanyakan kembali agar saya bisa menghitung mall terdekat.']);

    Http::assertNothingSent();
});

test('the chatbot only uses JSON results from the RDF endpoint and does not call an AI service', function () {
    Http::fake([
        '*' => Http::response(['results' => ['bindings' => []]], 200),
    ]);

    $this->postJson('/chatbot', ['message' => 'cafe sekitaran Sun Plaza'])->assertOk();

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/chat/completions'));
});

test('the chatbot filters mall listings by a recognized city', function () {
    Http::fake([
        '*' => Http::response(['results' => ['bindings' => []]], 200),
    ]);

    $this->postJson('/chatbot', ['message' => 'malls in Medan', 'locale' => 'en'])
        ->assertOk()
        ->assertJsonPath('intent.city', 'Medan');

    Http::assertSent(fn ($request): bool => str_contains(
        rawurldecode($request->url()),
        'FILTER(LCASE(STR(?city)) = LCASE("Medan"))',
    ));
});

test('the chatbot treats a city in a store question as a city filter rather than a mall name', function () {
    Http::fake([
        '*' => Http::response(['results' => ['bindings' => []]], 200),
    ]);

    $this->postJson('/chatbot', ['message' => 'Toko fashion di Medan'])
        ->assertOk()
        ->assertJsonPath('intent.type', 'find_store')
        ->assertJsonPath('intent.city', 'Medan')
        ->assertJsonMissingPath('intent.place');

    Http::assertSent(fn ($request): bool => str_contains(
        rawurldecode($request->url()),
        'FILTER(LCASE(STR(?city)) = LCASE("Medan"))',
    ) && ! str_contains(
        rawurldecode($request->url()),
        'CONTAINS(LCASE(STR(?mallName))',
    ));
});

test('mall comparison accepts two known resource ids and returns comparison data', function () {
    Http::fake([
        '*' => Http::response([
            'results' => [
                'bindings' => [
                    [
                        'mall' => ['value' => 'http://sumateramall.org/resource/Mall/sun_plaza'],
                        'name' => ['value' => 'Sun Plaza'],
                        'city' => ['value' => 'Medan'],
                        'province' => ['value' => 'Sumatera Utara'],
                        'storeCount' => ['value' => '5'],
                        'categoryCount' => ['value' => '3'],
                    ],
                    [
                        'mall' => ['value' => 'http://sumateramall.org/resource/Mall/deli_park_mall'],
                        'name' => ['value' => 'Deli Park Mall'],
                        'city' => ['value' => 'Medan'],
                        'province' => ['value' => 'Sumatera Utara'],
                        'storeCount' => ['value' => '8'],
                        'categoryCount' => ['value' => '4'],
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->get('/malls/compare?mall_ids[]=sun_plaza&mall_ids[]=deli_park_mall')
        ->assertOk()
        ->assertSee('Sun Plaza')
        ->assertSee('Deli Park Mall');

    Http::assertSent(fn ($request): bool => str_contains(rawurldecode($request->url()), ' UNION '));
});

test('SPARQL literals safely quote user-provided text', function () {
    expect(app(SparqlService::class)->literal('mall "one" \\ two'))
        ->toBe('"mall \\"one\\" \\\\ two"');
});

test('OSRM duration tables optimize the order of mall visits', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), '/table/')) {
            return Http::response([
                'code' => 'Ok',
                'durations' => [
                    [0, 100, 1],
                    [100, 0, 1],
                    [1, 1, 0],
                ],
            ]);
        }

        return Http::response([
            'code' => 'Ok',
            'routes' => [[
                'distance' => 2100,
                'duration' => 1800,
                'legs' => [
                    ['distance' => 1100, 'duration' => 900],
                    ['distance' => 1000, 'duration' => 900],
                ],
                'geometry' => ['coordinates' => [[98.6, 3.5], [98.7, 3.6], [98.8, 3.7]]],
            ]],
        ]);
    });

    $route = app(OsmService::class)->planRoute([
        ['id' => 'a', 'name' => 'Mall A', 'city' => 'Medan', 'lat' => 3.6, 'lng' => 98.7],
        ['id' => 'b', 'name' => 'Mall B', 'city' => 'Medan', 'lat' => 3.7, 'lng' => 98.8],
    ], 3.5, 98.6);

    expect(array_column($route['malls'], 'id'))->toBe(['b', 'a'])
        ->and($route['source'])->toBe('openstreetmap')
        ->and($route['totalDistance'])->toBe(2100)
        ->and(count($route['legs']))->toBe(2);
});

test('unusable OSRM data returns a clearly labeled local route estimate', function () {
    Http::fake(['*' => Http::response('not-json', 200)]);

    $route = app(OsmService::class)->planRoute([
        ['id' => 'a', 'name' => 'Mall A', 'city' => 'Medan', 'lat' => 3.6, 'lng' => 98.7],
        ['id' => 'b', 'name' => 'Mall B', 'city' => 'Medan', 'lat' => 3.7, 'lng' => 98.8],
    ], 3.5, 98.6);

    expect($route['source'])->toBe('estimated')
        ->and($route['notice'])->toContain('straight-line estimates')
        ->and($route['totalDistance'])->toBeGreaterThan(0);
});

test('route planning renders an OpenStreetMap route from RDF coordinates', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), '/table/')) {
            return Http::response([
                'code' => 'Ok',
                'durations' => [
                    [0, 100, 200],
                    [100, 0, 100],
                    [200, 100, 0],
                ],
            ], 200);
        }

        if (str_contains($request->url(), '/route/')) {
            return Http::response([
                'code' => 'Ok',
                'routes' => [[
                    'distance' => 12000,
                    'duration' => 1200,
                    'legs' => [
                        ['distance' => 6000, 'duration' => 600],
                        ['distance' => 6000, 'duration' => 600],
                    ],
                    'geometry' => ['coordinates' => [[98.5, 3.4], [98.6, 3.5], [98.7, 3.6]]],
                ]],
            ], 200);
        }

        return Http::response([
            'results' => ['bindings' => [
                [
                    'id' => ['value' => 'mall_a'],
                    'name' => ['value' => 'Mall A'],
                    'city' => ['value' => 'Medan'],
                    'province' => ['value' => 'Sumatera Utara'],
                    'address' => ['value' => 'One Street'],
                    'lat' => ['value' => '3.5'],
                    'lng' => ['value' => '98.6'],
                ],
                [
                    'id' => ['value' => 'mall_b'],
                    'name' => ['value' => 'Mall B'],
                    'city' => ['value' => 'Medan'],
                    'province' => ['value' => 'Sumatera Utara'],
                    'address' => ['value' => 'Two Street'],
                    'lat' => ['value' => '3.6'],
                    'lng' => ['value' => '98.7'],
                ],
            ]],
        ], 200);
    });

    $this->post('/planner/calculate', [
        'mall_ids' => ['mall_a', 'mall_b'],
        'start_lat' => '3.4',
        'start_lng' => '98.5',
        'locale' => 'en',
    ])->assertOk()
        ->assertSee('OpenStreetMap / OSRM')
        ->assertSee('Mall A')
        ->assertSee('Mall B')
        ->assertSee('osm-directions-link', false)
        ->assertSee('route-navigation-toggle', false)
        ->assertSee('watchPosition', false);

    Http::assertSent(fn ($request): bool => str_contains(
        $request->url(),
        '/route/v1/driving/98.5,3.4;',
    ));
});

test('route planning refuses selected malls without RDF coordinates', function () {
    Http::fake([
        '*' => Http::response([
            'results' => ['bindings' => [
                [
                    'id' => ['value' => 'mall_a'],
                    'name' => ['value' => 'Mall A'],
                    'city' => ['value' => 'Medan'],
                    'province' => ['value' => 'Sumatera Utara'],
                ],
                [
                    'id' => ['value' => 'mall_b'],
                    'name' => ['value' => 'Mall B'],
                    'city' => ['value' => 'Medan'],
                    'province' => ['value' => 'Sumatera Utara'],
                ],
            ]],
        ]),
    ]);

    $this->from('/planner')->post('/planner/calculate', [
        'mall_ids' => ['mall_a', 'mall_b'],
    ])->assertRedirect('/planner')->assertSessionHasErrors('mall_ids');

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/route/'));
});

test('mall geocoding uses Nominatim and caches coordinates', function () {
    $cacheKey = 'osm_geocode_'.md5('sun plaza, medan|');
    Cache::forget($cacheKey);
    Http::fake([
        '*' => Http::response([['lat' => '3.5952', 'lon' => '98.6722']], 200),
    ]);

    $this->getJson('/map/geocode?query='.urlencode('Sun Plaza, Medan'))
        ->assertOk()
        ->assertExactJson(['lat' => 3.5952, 'lng' => 98.6722]);
    $this->getJson('/map/geocode?query='.urlencode('Sun Plaza, Medan'))->assertOk();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'nominatim.openstreetmap.org/search')
        && $request->hasHeader('User-Agent')
        && data_get($request->data(), 'countrycodes') === 'id');
});

test('mall geocoding retries with the clean mall name when the full address has no match', function () {
    $cacheKey = 'osm_geocode_'.md5('sun plaza, medan|Jl. Teuku Umar, Medan');
    Cache::forget($cacheKey);
    Http::fakeSequence()
        ->push([], 200)
        ->push([['lat' => '3.5822609', 'lon' => '98.6719101']], 200);

    $this->getJson('/map/geocode?'.http_build_query([
        'query' => 'Sun Plaza, Medan',
        'fallback' => 'Jl. Teuku Umar, Medan',
    ]))->assertOk()
        ->assertExactJson(['lat' => 3.5822609, 'lng' => 98.6719101]);

    Http::assertSentCount(2);
});

test('geocoding explains how to fix Nominatim access denied errors', function () {
    Cache::forget('osm_geocode_'.md5('test mall|'));
    Http::fake(['*' => Http::response('Forbidden', 403)]);

    $response = $this->getJson('/map/geocode?query=test%20mall')->assertStatus(503);

    expect($response->json('message'))
        ->toContain('OSM_USER_AGENT')
        ->toContain('valid contact email');
});

test('nearby amenities are returned from OpenStreetMap with mapped labels', function () {
    $cacheKey = 'osm_nearby_'.md5(implode('|', [3.5, 98.6, 1500]));
    Cache::forget($cacheKey);
    Http::fake([
        '*' => Http::response([
            'elements' => [
                [
                    'type' => 'node',
                    'id' => 42,
                    'lat' => 3.501,
                    'lon' => 98.601,
                    'tags' => ['amenity' => 'atm', 'name' => 'ATM Example'],
                ],
            ],
        ], 200),
    ]);

    $this->getJson('/map/nearby?lat=3.5&lng=98.6')
        ->assertOk()
        ->assertJsonPath('amenities.0.name', 'ATM Example')
        ->assertJsonPath('amenities.0.type', 'ATM');
});

test('the budget estimator interpolates available RDF price levels', function () {
    Http::fake(function ($request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $queryParameters);
        $sparql = $queryParameters['query'] ?? '';

        if (str_contains($sparql, 'AVG(')) {
            return Http::response([
                'results' => ['bindings' => [[
                    'averagePriceLevel' => ['value' => '2.5'],
                    'pricedStores' => ['value' => '4'],
                ]]],
            ], 200);
        }

        return Http::response([
            'results' => ['bindings' => [[
                'id' => ['value' => 'http://sumateramall.org/resource/Mall/sun_plaza'],
                'name' => ['value' => 'Sun Plaza'],
                'city' => ['value' => 'Medan'],
            ]]],
        ], 200);
    });

    $this->get('/budget/estimate?mall=http%3A%2F%2Fsumateramall.org%2Fresource%2FMall%2Fsun_plaza&people=2')
        ->assertOk()
        ->assertSee('Rp 225.000')
        ->assertSeeText('4 toko');
});

test('the calendar export uses the route stored in the session', function () {
    $this->withSession([
        'planner.itinerary' => [
            'plannedAt' => '2026-11-14T09:00:00+07:00',
            'source' => 'openstreetmap',
            'totalDistance' => 2100,
            'totalDuration' => 1800,
            'notice' => null,
            'malls' => [
                ['id' => 'sun_plaza', 'name' => 'Sun Plaza', 'city' => 'Medan'],
                ['id' => 'deli_park_mall', 'name' => 'Deli Park Mall', 'city' => 'Medan'],
            ],
        ],
    ])->get('/planner/export/ics')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
        ->assertSee('SUMMARY:Visit Sun Plaza', false)
        ->assertSee('SUMMARY:Visit Deli Park Mall', false);
});

test('the PDF export downloads the stored itinerary', function () {
    $this->withSession([
        'planner.itinerary' => [
            'plannedAt' => '2026-11-14T09:00:00+07:00',
            'source' => 'openstreetmap',
            'totalDistance' => 2100,
            'totalDuration' => 1800,
            'notice' => null,
            'malls' => [
                ['id' => 'sun_plaza', 'name' => 'Sun Plaza', 'city' => 'Medan'],
                ['id' => 'deli_park_mall', 'name' => 'Deli Park Mall', 'city' => 'Medan'],
            ],
        ],
    ])->get('/planner/export/pdf')
        ->assertOk()
        ->assertDownload('sumateramall-itinerary.pdf')
        ->assertHeader('content-type', 'application/pdf');
});
