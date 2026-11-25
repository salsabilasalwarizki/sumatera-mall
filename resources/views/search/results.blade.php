@extends('layouts.app')

@section('title', 'Hasil Pencarian')

@section('content')
<div class="max-w-7xl mx-auto px-6">
    <div class="mb-8">
        <h1 data-i18n="search_results_title" class="font-display text-4xl font-bold mb-2">Hasil Pencarian</h1>
        <p class="text-gray-400"><span data-i18n="search_results_found">Ditemukan</span> <strong class="text-neon-cyan">{{ $count }}</strong> <span data-i18n="search_results_count">hasil</span></p>
        @if($searchTerm !== '')
        <p class="text-sm text-gray-400 mt-2"><span data-i18n="search_results_for">Hasil untuk</span> <strong class="text-neon-gold">“{{ $searchTerm }}”</strong> <span data-i18n="fuzzy_search_note">— typo-friendly matching</span></p>
        @endif
        @if($suggestedSearch)
        <p class="text-sm text-gray-400 mt-2">
            <span data-i18n="search_did_you_mean">Mungkin yang Anda maksud adalah</span>
            <a class="text-neon-gold hover:underline" href="{{ route('search.filter', ['search' => $suggestedSearch]) }}">{{ $suggestedSearch }}</a>
        </p>
        @endif
    </div>

    @if(count($results) > 0)
    <section class="search-refinements mb-8" aria-label="Filter hasil pencarian">
        <div class="bubble-filter-row mb-3" role="group" aria-label="Category filters">
            <button type="button" class="bubble-filter bubble-filter--active" data-result-category="" aria-pressed="true" data-i18n="filter_all">All categories</button>
            @foreach(collect($results)->pluck('category')->filter()->unique()->sort() as $category)
            <button type="button" class="bubble-filter" data-result-category="{{ $category }}" aria-pressed="false">{{ $category }}</button>
            @endforeach
            <button type="button" class="bubble-filter" data-result-open aria-pressed="{{ request('open_now') ? 'true' : 'false' }}"
                    @class(['bubble-filter--active' => request('open_now')]) data-i18n="field_open_now">Open now</button>
        </div>
        <div class="bubble-filter-row" role="group" aria-label="Search filters">
            <button type="button" class="bubble-filter bubble-filter--active" data-result-city="" aria-pressed="true" data-i18n="all_cities">All cities</button>
            @foreach(collect($results)->pluck('city')->filter()->unique()->sort() as $city)
            <button type="button" class="bubble-filter" data-result-city="{{ $city }}" aria-pressed="false">{{ $city }}</button>
            @endforeach
            <button type="button" class="bubble-filter" data-result-rated="4" aria-pressed="false" data-i18n="filter_rated">★ 4.0+</button>
            <button type="button" class="bubble-filter {{ request('operational') ? 'bubble-filter--active' : '' }}"
                    data-result-operational aria-pressed="{{ request('operational') ? 'true' : 'false' }}"
                    data-i18n="filter_operational">Operational</button>
            @foreach(collect($results)->pluck('priceLevel')->filter()->unique()->sort() as $priceLevel)
            <button type="button" class="bubble-filter" data-result-price="{{ $priceLevel }}" aria-pressed="false">
                <span data-i18n="field_price_level">Maximum price level</span>
                <span aria-hidden="true">≤ {{ $priceLevel }} / 4</span>
            </button>
            @endforeach
            @foreach(collect($results)->pluck('floor')->filter()->unique()->sort() as $floor)
            <button type="button" class="bubble-filter" data-result-floor="{{ $floor }}" aria-pressed="false">
                <span data-i18n="field_floor">Lantai</span>
                <span>{{ $floor }}</span>
            </button>
            @endforeach
            <a href="{{ route('search.filter', ['search' => $searchTerm]) }}"
               class="bubble-filter" data-i18n="reset_button">Reset filters</a>
        </div>
    </section>
    <form id="results-open-filter" action="{{ route('search.filter') }}" method="GET" class="sr-only" aria-hidden="true">
        <input type="hidden" name="search" value="{{ $searchTerm }}">
        @foreach(['city', 'mall', 'category', 'min_rating', 'price_level', 'operational'] as $filter)
            @if(request()->filled($filter))
            <input type="hidden" name="{{ $filter }}" value="{{ request($filter) }}">
            @endif
        @endforeach
        <input type="hidden" name="open_now" value="1">
    </form>
    @endif
    <p class="text-xs text-gray-500 mb-4"><span data-i18n="showing">Showing</span> <strong id="filtered-result-count">{{ $count }}</strong> <span data-i18n="search_results_count">results</span></p>

    @if(count($mallResults) > 0)
    <section class="mb-8" aria-labelledby="matching-malls-title">
        <h2 id="matching-malls-title" class="font-display text-2xl font-bold mb-4" data-i18n="search_matching_malls">Mall yang cocok</h2>
        <div class="grid md:grid-cols-3 gap-6">
            @foreach($mallResults as $mall)
            <article class="glass rounded-2xl p-5 hover:border-neon-cyan/50 transition-all">
                <a href="{{ route('malls.show', ['id' => $mall['mallId']]) }}" class="block">
                    @if(!empty($mall['image']))
                    <img src="{{ $mall['image'] }}" alt="{{ $mall['mallName'] }}" class="w-full h-36 rounded-xl bg-white object-cover mb-4" loading="lazy" decoding="async">
                    @endif
                    <span class="bubble-filter bubble-filter--active mb-3" data-i18n="search_mall_label">Mall</span>
                    <h3 class="font-display font-bold text-lg mt-3">{{ $mall['mallName'] }}</h3>
                    <p class="text-sm text-gray-400">{{ $mall['city'] }}@if(!empty($mall['province'])), {{ $mall['province'] }}@endif</p>
                    @if(!empty($mall['address']))
                    <p class="text-xs text-gray-500 mt-2">{{ $mall['address'] }}</p>
                    @endif
                    <span class="inline-block text-sm text-neon-gold mt-4" data-i18n="search_mall_open">Lihat detail mall</span>
                </a>
            </article>
            @endforeach
        </div>
    </section>
    @endif

    <div class="grid md:grid-cols-3 gap-6" id="search-results-grid">
        @forelse($results as $r)
        <div class="glass rounded-2xl p-6 hover:border-neon-cyan/50 transition-all"
             data-result-card data-category="{{ $r['category'] }}" data-city="{{ $r['city'] }}"
             data-floor="{{ $r['floor'] ?? '' }}" data-rating="{{ $r['rating'] ?? '' }}" data-status="{{ $r['status'] ?? '' }}"
             data-price-level="{{ $r['priceLevel'] ?? '' }}">
            <div class="flex justify-between items-start mb-4">
                <div class="flex items-start gap-3">
                    @if(!empty($r['image']))
                    <img src="{{ $r['image'] }}" alt="{{ $r['storeName'] }} logo" class="w-14 h-14 rounded-lg bg-white object-contain p-1" loading="lazy" decoding="async">
                    @endif
                    <div>
                    <h3 class="font-display font-bold text-lg mb-1">{{ $r['storeName'] }}</h3>
                    <p class="text-sm text-gray-400">{{ $r['mallName'] }}</p>
                    </div>
                </div>
                @if(!empty($r['rating']))
                <div class="flex items-center gap-1">
                    <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                    <span class="text-sm font-semibold">{{ $r['rating'] }}</span>
                </div>
                @endif
            </div>

            <div class="flex gap-2 flex-wrap mb-3">
                <span class="glass px-3 py-1 rounded-full text-xs">{{ $r['category'] }}</span>
                <span class="glass px-3 py-1 rounded-full text-xs">{{ $r['city'] }}</span>
                @if(!empty($r['floor']))
                <span class="result-floor" aria-label="Lantai {{ $r['floor'] }}">
                    <span aria-hidden="true">↟</span>
                    <span data-i18n="store_floor_label">Lantai</span> {{ $r['floor'] }}
                </span>
                @endif
            </div>

            @if(!empty($r['address']))
            <p class="mb-3 text-xs text-gray-400">{{ $r['address'] }}</p>
            @endif

            @if(!empty($r['status']) && $r['status'] === 'OPERATIONAL')
            <div class="flex items-center gap-2 text-xs text-green-400">
                <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></span>
                <span data-i18n="operational">Operasional</span>
            </div>
            @endif
        </div>
        @empty
        @if(count($mallResults) === 0)
        <div class="col-span-3 glass rounded-2xl p-12 text-center">
            <p data-i18n="search_no_results" class="text-gray-400">Tidak ada hasil. Coba filter lain.</p>
        </div>
        @endif
        @endforelse
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    let category = '';
    let city = '';
    let minimumRating = 0;
    let maximumPriceLevel = '';
    let floor = new URLSearchParams(window.location.search).get('floor') || '';
    const cards = Array.from(document.querySelectorAll('[data-result-card]'));
    const mallResultCount = document.querySelectorAll('#matching-malls-title + div article').length;
    const chips = document.querySelectorAll('[data-result-category], [data-result-city], [data-result-rated], [data-result-price], [data-result-floor]');
    const normalize = value => value.trim().toLocaleLowerCase('id');

    chips.forEach(chip => {
        if (chip.hasAttribute('data-result-floor') && normalize(chip.dataset.resultFloor) === normalize(floor)) {
            chip.classList.add('bubble-filter--active');
            chip.setAttribute('aria-pressed', 'true');
        }
    });

    function updateResults() {
        let visible = 0;
        cards.forEach(card => {
            const matches = (!category || normalize(card.dataset.category) === normalize(category))
                && (!city || normalize(card.dataset.city) === normalize(city))
                && (!minimumRating || Number(card.dataset.rating) >= minimumRating)
                && (!maximumPriceLevel || Number(card.dataset.priceLevel) <= Number(maximumPriceLevel))
                && (!floor || normalize(card.dataset.floor) === normalize(floor));
            card.hidden = !matches;
            if (matches) visible++;
        });
        document.getElementById('filtered-result-count').textContent = String(visible + mallResultCount);
    }

    chips.forEach(chip => chip.addEventListener('click', () => {
        if (chip.hasAttribute('data-result-category')) category = chip.dataset.resultCategory;
        if (chip.hasAttribute('data-result-city')) city = chip.dataset.resultCity;
        if (chip.hasAttribute('data-result-rated')) {
            minimumRating = minimumRating === Number(chip.dataset.resultRated) ? 0 : Number(chip.dataset.resultRated);
        }
        if (chip.hasAttribute('data-result-price')) {
            maximumPriceLevel = maximumPriceLevel === chip.dataset.resultPrice ? '' : chip.dataset.resultPrice;
        }
        if (chip.hasAttribute('data-result-floor')) {
            floor = floor === chip.dataset.resultFloor ? '' : chip.dataset.resultFloor;
        }

        chips.forEach(item => {
            const active = (item.hasAttribute('data-result-category') && item.dataset.resultCategory === category)
                || (item.hasAttribute('data-result-city') && item.dataset.resultCity === city)
                || (item.hasAttribute('data-result-rated') && Number(item.dataset.resultRated) === minimumRating && minimumRating > 0)
                || (item.hasAttribute('data-result-price') && item.dataset.resultPrice === maximumPriceLevel)
                || (item.hasAttribute('data-result-floor') && item.dataset.resultFloor === floor);
            item.classList.toggle('bubble-filter--active', active);
            item.setAttribute('aria-pressed', String(active));
        });
        updateResults();
    }));

    const submitServerFilter = (name, enabled) => {
        const form = document.getElementById('results-open-filter');
        if (!form) return;
        form.querySelectorAll(`[name="${name}"]`).forEach(input => input.remove());
        if (enabled) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = '1';
            form.append(input);
        }
        form.requestSubmit();
    };

    document.querySelector('[data-result-open]')?.addEventListener('click', () =>
        submitServerFilter('open_now', !new URLSearchParams(window.location.search).has('open_now'))
    );
    document.querySelector('[data-result-operational]')?.addEventListener('click', () =>
        submitServerFilter('operational', !new URLSearchParams(window.location.search).has('operational'))
    );

    updateResults();
});
</script>
@endpush