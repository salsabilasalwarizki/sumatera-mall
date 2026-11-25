@extends('layouts.app')

@section('title', 'Pencarian Mall & Toko')

@section('content')
<div class="max-w-5xl mx-auto px-6 search-page">
    <div class="mb-10">
        <p class="search-page__eyebrow" data-i18n="search_eyebrow">PENCARIAN BERBASIS DATA MALL</p>
        <h1 data-i18n="page_search_title" class="font-display text-5xl font-bold mb-3">Cari toko dengan cara yang lebih mudah</h1>
        <p data-i18n="page_search_description" class="text-gray-400">Cari berdasarkan nama toko, kategori, lokasi, atau lantai. Hasilnya dicocokkan dengan data mall yang saling terhubung.</p>
    </div>

    <form id="advanced-search-form" action="{{ route('search.filter') }}" method="POST" class="glass rounded-3xl p-8 mb-8">
        @csrf
        <div class="mb-6">
            <p class="text-sm font-medium text-gray-300 mb-3" data-i18n="quick_filters">Quick filters</p>
            <div class="bubble-filter-row" role="group" aria-label="Quick store filters">
                <button type="button" class="bubble-filter bubble-filter--active" data-search-category="" data-i18n="filter_all">All</button>
                @foreach($categories as $category)
                <button type="button" class="bubble-filter" data-search-category="{{ $category['id'] }}">{{ $category['name'] }}</button>
                @endforeach
                <button type="button" class="bubble-filter" data-search-open data-i18n="field_open_now">Buka sekarang</button>
                <button type="button" class="bubble-filter" data-search-rating="4.5">★ 4.5+</button>
                <button type="button" class="bubble-filter" data-search-operational data-i18n="filter_operational">Operasional</button>
            </div>
        </div>
        <section class="search-filter-group mb-6" aria-labelledby="search-location-heading">
            <h2 id="search-location-heading" class="search-filter-group__title" data-i18n="search_location_heading">Lokasi</h2>
            <div class="grid md:grid-cols-3 gap-6">
            <div>
                <label for="search-province" data-i18n="field_province" class="block text-sm font-medium mb-2 text-gray-300">Provinsi</label>
                <select id="search-province" name="province" class="w-full glass bg-transparent p-3 rounded-xl border border-white/10 focus:border-neon-cyan/50 focus:outline-none">
                    <option value="" data-i18n="all_provinces">Semua provinsi</option>
                    @foreach($provinces as $province)
                    <option value="{{ $province['id'] }}" {{ request('province') == $province['id'] ? 'selected' : '' }}>
                        {{ $province['name'] }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="search-city" data-i18n="field_city" class="block text-sm font-medium mb-2 text-gray-300">Kota</label>
                <select id="search-city" name="city" class="w-full glass bg-transparent p-3 rounded-xl border border-white/10 focus:border-neon-cyan/50 focus:outline-none">
                    <option value="" data-i18n="all_cities">Semua Kota</option>
                    @foreach($cities as $c)
                    <option value="{{ $c['id'] }}" data-province="{{ $c['provinceId'] ?? '' }}" {{ request('city') == $c['id'] ? 'selected' : '' }}>
                        {{ $c['name'] }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="search-mall" data-i18n="field_mall" class="block text-sm font-medium mb-2 text-gray-300">Mall</label>
                <select id="search-mall" name="mall" class="w-full glass bg-transparent p-3 rounded-xl border border-white/10 focus:border-neon-cyan/50 focus:outline-none">
                    <option value="" data-i18n="all_malls">Semua Mall</option>
                    @foreach($malls as $mall)
                    <option value="{{ $mall['id'] }}" {{ request('mall') == $mall['id'] ? 'selected' : '' }}>
                        {{ $mall['name'] }}
                    </option>
                    @endforeach
                </select>
            </div>
            </div>
        </section>

        <section class="search-filter-group mb-6" aria-labelledby="search-preferences-heading">
            <h2 id="search-preferences-heading" class="search-filter-group__title" data-i18n="search_preferences_heading">Preferensi toko</h2>
            <div class="grid md:grid-cols-2 gap-6">
            <div>
                <label for="search-category" data-i18n="field_category" class="block text-sm font-medium mb-2 text-gray-300">Kategori</label>
                <select id="search-category" name="category" class="w-full glass bg-transparent p-3 rounded-xl border border-white/10 focus:border-neon-cyan/50 focus:outline-none">
                    <option value="" data-i18n="all_categories">Semua Kategori</option>
                    @foreach($categories as $c)
                    <option value="{{ $c['id'] }}" {{ request('category') == $c['id'] ? 'selected' : '' }}>
                        {{ $c['name'] }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="search-rating" data-i18n="field_rating" class="block text-sm font-medium mb-2 text-gray-300">Rating Minimum</label>
                <select id="search-rating" name="min_rating" class="w-full glass bg-transparent p-3 rounded-xl border border-white/10 focus:border-neon-cyan/50 focus:outline-none">
                    <option value="" data-i18n="all_ratings">Semua Rating</option>
                    <option value="4" {{ request('min_rating') === '4' ? 'selected' : '' }}>4.0+</option>
                    <option value="4.5" {{ request('min_rating') === '4.5' ? 'selected' : '' }}>4.5+</option>
                </select>
            </div>

            <div>
                <label for="search-floor" data-i18n="field_floor" class="block text-sm font-medium mb-2 text-gray-300">Lantai toko</label>
                <input id="search-floor" name="floor" type="search" list="search-floor-options"
                       value="{{ request('floor') }}" maxlength="100" autocomplete="off"
                       data-i18n-placeholder="floor_search_placeholder" placeholder="Contoh: GF, LG, atau 1st Floor"
                       class="w-full glass bg-transparent p-3 rounded-xl border border-white/10 focus:border-neon-cyan/50 focus:outline-none">
                <datalist id="search-floor-options">
                    @foreach($floors as $floor)
                    <option value="{{ $floor['floor'] }}"></option>
                    @endforeach
                </datalist>
                <p class="mt-2 text-xs text-gray-500" data-i18n="floor_filter_note">Pilihan mengikuti lantai yang tercatat pada data toko.</p>
            </div>

            <div>
                <label data-i18n="field_price_level" for="search-price-level" class="block text-sm font-medium mb-2 text-gray-300">Maximum price level</label>
                <select id="search-price-level" name="price_level" class="w-full glass bg-transparent p-3 rounded-xl border border-white/10 focus:border-neon-cyan/50 focus:outline-none">
                    <option value="" data-i18n="filter_any_price">Any price</option>
                    <option value="1" {{ request('price_level') === '1' ? 'selected' : '' }}>1 — Rp</option>
                    <option value="2" {{ request('price_level') === '2' ? 'selected' : '' }}>2 — Rp Rp</option>
                    <option value="3" {{ request('price_level') === '3' ? 'selected' : '' }}>3 — Rp Rp Rp</option>
                    <option value="4" {{ request('price_level') === '4' ? 'selected' : '' }}>4 — Rp Rp Rp Rp</option>
                </select>
            </div>

            <div class="flex items-end">
                <label class="flex items-center gap-3 cursor-pointer glass p-3 rounded-xl w-full">
                    <input type="checkbox" name="open_now" value="1"
                           {{ request('open_now') ? 'checked' : '' }}
                           class="w-5 h-5 rounded">
                    <span data-i18n="field_open_now" class="font-medium">Buka Sekarang</span>
                </label>
            </div>

            <div class="flex items-end">
                <label class="flex items-center gap-3 cursor-pointer glass p-3 rounded-xl w-full">
                    <input type="checkbox" name="operational" value="1"
                           {{ request('operational') ? 'checked' : '' }}
                           class="w-5 h-5 rounded">
                    <span data-i18n="filter_operational" class="font-medium">Operational</span>
                </label>
            </div>
        </div>
        </section>

        <div class="mb-6">
            <label for="search-store-input" data-i18n="field_store_search" class="block text-sm font-medium mb-2 text-gray-300">Cari nama toko</label>
            <div class="flex gap-2">
            <input type="search" name="search" value="{{ request('search') }}" maxlength="100"
                   id="search-store-input"
                   data-i18n-placeholder="field_store_search" placeholder="Cari nama toko"
                   class="flex-1 glass bg-transparent p-3 rounded-xl border border-white/10 focus:border-neon-cyan/50 focus:outline-none">
            <button type="button" data-voice-search data-voice-input="search-store-input" class="glass px-4 rounded-xl border border-white/10 hover:border-neon-cyan/50" aria-label="Search by voice" title="Search by voice">
                <span aria-hidden="true">🎙</span>
            </button>
            </div>
            <p data-voice-search-status data-voice-status-for="search-store-input" class="text-xs text-gray-500 mt-2" role="status" aria-live="polite"></p>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="bg-gradient-to-r from-neon-cyan to-neon-purple text-black px-8 py-3 rounded-full font-semibold hover:shadow-2xl hover:shadow-neon-cyan/50 transition-all">
                <span data-i18n="search_button">Cari</span>
            </button>
            <a href="{{ route('search.index') }}" class="glass px-8 py-3 rounded-full font-semibold hover:bg-white/10 transition-all border border-white/20">
                <span data-i18n="reset_button">Reset</span>
            </a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('advanced-search-form');
    const provinceSelect = form?.querySelector('[name="province"]');
    const citySelect = form?.querySelector('[name="city"]');
    const categorySelect = form?.querySelector('[name="category"]');
    const openNow = form?.querySelector('[name="open_now"]');
    const minRating = form?.querySelector('[name="min_rating"]');
    const operational = form?.querySelector('[name="operational"]');
    const chips = document.querySelectorAll('[data-search-category], [data-search-open], [data-search-rating], [data-search-operational]');

    const filterCities = () => {
        if (!provinceSelect || !citySelect) return;
        const selectedProvince = provinceSelect.value;
        const selectedCity = citySelect.selectedOptions[0];
        [...citySelect.options].forEach(option => {
            option.hidden = option.value !== '' && selectedProvince !== ''
                && option.dataset.province !== selectedProvince;
        });
        if (selectedCity?.value && selectedCity.dataset.province !== selectedProvince && selectedProvince !== '') {
            citySelect.value = '';
        }
    };
    provinceSelect?.addEventListener('change', filterCities);
    filterCities();

    chips.forEach(chip => chip.addEventListener('click', () => {
        if (chip.hasAttribute('data-search-category')) categorySelect.value = chip.dataset.searchCategory;
        if (chip.hasAttribute('data-search-open')) openNow.checked = !openNow.checked;
        if (chip.hasAttribute('data-search-rating')) minRating.value = chip.dataset.searchRating;
        if (chip.hasAttribute('data-search-operational')) operational.checked = !operational.checked;

        chips.forEach(item => {
            const active = item === chip || (item.hasAttribute('data-search-open') && openNow.checked)
                || (item.hasAttribute('data-search-rating') && minRating.value === item.dataset.searchRating)
                || (item.hasAttribute('data-search-operational') && operational.checked)
                || (item.hasAttribute('data-search-category') && categorySelect.value === item.dataset.searchCategory);
            item.classList.toggle('bubble-filter--active', active);
            item.setAttribute('aria-pressed', String(active));
        });

        form.requestSubmit();
    }));
});
</script>
@endpush