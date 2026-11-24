@extends('layouts.app')

@section('title', 'Peta Mall Sumatera')

@section('content')
<div class="max-w-7xl mx-auto px-6">
    <div class="mb-8">
        <h1 data-i18n="page_map_title" class="font-display text-5xl font-bold mb-3">Peta Mall</h1>
        <p data-i18n="page_map_description" class="text-gray-400">Lokasi mall dari graph RDF, ditampilkan di OpenStreetMap.</p>
    </div>

    <div class="glass rounded-3xl p-4 mb-8">
        <div id="map" class="rounded-2xl" style="height: 600px;"></div>
        <p id="map-status" class="text-sm text-gray-400 px-3 pt-3" role="status" aria-live="polite"></p>
    </div>

    <div class="glass rounded-3xl p-6">
        <h2 class="font-display text-xl font-bold mb-4"><span data-i18n="map_mall_list">Daftar Mall</span> ({{ count($malls) }})</h2>
        <div class="bubble-filter-row mb-3" role="group" aria-label="Filter map malls by province">
            <button type="button" class="bubble-filter bubble-filter--active" data-map-province="" aria-pressed="true" data-i18n="filter_all">Semua</button>
            @foreach(collect($malls)->pluck('province')->filter()->unique()->sort() as $province)
            <button type="button" class="bubble-filter" data-map-province="{{ $province }}" aria-pressed="false">{{ $province }}</button>
            @endforeach
        </div>
        <div class="bubble-filter-row mb-4" role="group" aria-label="Filter map malls by city">
            <button type="button" class="bubble-filter bubble-filter--active" data-map-city="" aria-pressed="true" data-i18n="all_cities">Semua kota</button>
            @foreach(collect($malls)->pluck('city')->filter()->unique()->sort() as $city)
            <button type="button" class="bubble-filter" data-map-city="{{ $city }}" aria-pressed="false">{{ $city }}</button>
            @endforeach
        </div>
        <p class="text-xs text-gray-500 mb-3"><span data-i18n="showing">Menampilkan</span> <strong id="map-mall-count">{{ count($malls) }}</strong> <span data-i18n="mall_items">mall</span></p>
        <div class="grid md:grid-cols-3 gap-3 max-h-96 overflow-y-auto">
            @foreach($malls as $mall)
            <article class="glass p-3 rounded-xl" data-map-mall data-province="{{ $mall['province'] }}" data-city="{{ $mall['city'] }}">
                <a href="{{ route('malls.show', ['id' => $mall['id']]) }}" class="block">
                    <strong class="block">{{ $mall['name'] }}</strong>
                    <span class="text-sm text-gray-400">{{ $mall['city'] }}, {{ $mall['province'] }}</span>
                </a>
                <button type="button" data-locate-mall class="text-sm text-neon-cyan mt-2 hover:underline"
                        data-name="{{ $mall['name'] }}" data-city="{{ $mall['city'] }}"
                        data-province="{{ $mall['province'] }}" data-address="{{ $mall['address'] ?? '' }}"
                        data-lat="{{ $mall['lat'] ?? '' }}" data-lng="{{ $mall['lng'] ?? '' }}">
                    <span data-i18n="maps_locate">Tampilkan di peta</span>
                </button>
                <a class="block text-xs text-gray-400 mt-1 hover:underline"
                   href="https://www.openstreetmap.org/search?query={{ urlencode(($mall['address'] ?? $mall['name']).', '.$mall['city'].', '.$mall['province'].', Indonesia') }}"
                   target="_blank" rel="noopener noreferrer" data-i18n="maps_open">Buka di OpenStreetMap</a>
            </article>
            @endforeach
        </div>
        <p id="map-mall-empty" class="text-sm text-gray-400 mt-4" hidden data-i18n="mall_filter_empty">Tidak ada mall yang cocok dengan filter ini.</p>
    </div>
</div>

@push('scripts')
<script>
const mallMapElement = document.getElementById('map');
const mapStatus = document.getElementById('map-status');
let mallMap;
let L;
const mallMarkers = new Map();

function markerIcon() {
    const L = window.sumateraLeaflet;
    return L.divIcon({ className: 'mall-map-marker', html: '<span></span>', iconSize: [24, 24], iconAnchor: [12, 12] });
}

function popupContent(text) {
    const element = document.createElement('span');
    element.textContent = text;
    return element;
}

async function resolveMallPosition(button) {
    const latitude = Number(button.dataset.lat);
    const longitude = Number(button.dataset.lng);
    if (Number.isFinite(latitude) && Number.isFinite(longitude) && button.dataset.lat !== '' && button.dataset.lng !== '') {
        return [latitude, longitude];
    }

    const query = `${button.dataset.name}, ${button.dataset.city}, ${button.dataset.province}, Indonesia`;
    const params = new URLSearchParams({ query });
    if (button.dataset.address) params.set('fallback', `${button.dataset.address}, ${button.dataset.city}, ${button.dataset.province}, Indonesia`);
    const response = await fetch(`{{ route('map.geocode') }}?${params}`);
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || window.sumateraTranslate('maps_location_failed'));
    return [Number(data.lat), Number(data.lng)];
}

document.addEventListener('DOMContentLoaded', () => {
    L = window.sumateraLeaflet;
    mallMap = L.map(mallMapElement).setView([0.5, 101], 5);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(mallMap);
    document.querySelectorAll('[data-locate-mall]').forEach(button => {
        if (button.dataset.lat === '' || button.dataset.lng === '') return;
        const key = `${button.dataset.name}|${button.dataset.city}`;
        const marker = L.marker([Number(button.dataset.lat), Number(button.dataset.lng)], { icon: markerIcon() })
            .bindPopup(popupContent(`${button.dataset.name} — ${button.dataset.city}`))
            .addTo(mallMap);
        mallMarkers.set(key, marker);
    });

    let province = '';
    let city = '';
    const normalize = value => value.trim().toLocaleLowerCase('id');
    const cards = Array.from(document.querySelectorAll('[data-map-mall]'));
    const filters = document.querySelectorAll('[data-map-province], [data-map-city]');
    const emptyState = document.getElementById('map-mall-empty');

    function updateMapList() {
        let visible = 0;
        cards.forEach(card => {
            const matches = (!province || normalize(card.dataset.province) === normalize(province))
                && (!city || normalize(card.dataset.city) === normalize(city));
            card.hidden = !matches;
            if (matches) visible++;
        });
        document.getElementById('map-mall-count').textContent = String(visible);
        emptyState.hidden = visible > 0;
    }

    filters.forEach(button => button.addEventListener('click', () => {
        if (button.hasAttribute('data-map-province')) province = button.dataset.mapProvince;
        if (button.hasAttribute('data-map-city')) {
            city = button.dataset.mapCity;
            if (city) province = '';
        }
        filters.forEach(item => {
            const active = (item.hasAttribute('data-map-province') && item.dataset.mapProvince === province)
                || (item.hasAttribute('data-map-city') && item.dataset.mapCity === city);
            item.classList.toggle('bubble-filter--active', active);
            item.setAttribute('aria-pressed', String(active));
        });
        updateMapList();
    }));
});

const showMapError = error => {
    mapStatus.textContent = error.message || window.sumateraTranslate('maps_load_failed');
};

document.querySelectorAll('[data-locate-mall]').forEach(button => {
    button.addEventListener('click', async () => {
        const label = button.querySelector('[data-i18n]');
        const originalLabel = label.textContent;
        label.textContent = window.sumateraTranslate('maps_locating');
        button.disabled = true;
        mapStatus.textContent = '';
        try {
            const position = await resolveMallPosition(button);
            const key = `${button.dataset.name}|${button.dataset.city}`;
            if (!mallMarkers.has(key)) {
                mallMarkers.set(key, L.marker(position, { icon: markerIcon() })
                    .bindPopup(popupContent(`${button.dataset.name} — ${button.dataset.city}`))
                    .addTo(mallMap));
            } else {
                mallMarkers.get(key).setLatLng(position);
            }
            mallMap.setView(position, 15);
            mallMarkers.get(key).openPopup();
        } catch (error) {
            showMapError(error);
        } finally {
            label.textContent = originalLabel;
            button.disabled = false;
        }
    });
});

document.addEventListener('DOMContentLoaded', () => {
    if (!@js(count($malls) > 0)) {
        mapStatus.textContent = window.sumateraTranslate('map_empty');
    } else if (mallMarkers.size === 0) {
        mapStatus.textContent = window.sumateraTranslate('map_coordinates_unavailable');
    }
});
</script>
@endpush
@endsection
