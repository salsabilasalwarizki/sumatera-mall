@extends('layouts.app')

@section('title', $mall['name'])
@section('og_title', $mall['name'])
@section('og_description', $mall['desc'] ?? 'Mall di '.$mall['city'])
@section('og_type', 'place')

@section('content')
<div class="max-w-6xl mx-auto px-6" typeof="schema:ShoppingCenter"
     resource="http://sumateramall.org/resource/Mall/{{ $id }}">
    <div class="glass rounded-3xl p-8 mb-8">
        @if(!empty($mall['image']))
        <img src="{{ $mall['image'] }}" alt="{{ $mall['name'] }}" class="w-full max-h-80 rounded-2xl object-cover mb-6" fetchpriority="high">
        @endif
        <h1 property="schema:name" class="font-display text-5xl font-bold mb-3">{{ $mall['name'] }}</h1>
        <div class="flex gap-3 mb-5">
            <span class="glass px-4 py-1 rounded-full text-sm">{{ $mall['city'] }}</span>
            <span class="glass px-4 py-1 rounded-full text-sm">{{ $mall['province'] }}</span>
        </div>
        @if(!empty($mall['desc']))
        <p class="text-gray-300 text-lg" property="schema:description">{{ $mall['desc'] }}</p>
        @endif
        @if(!empty($mall['address']))
        <p class="text-gray-400 mt-3">{{ $mall['address'] }}</p>
        @endif
    </div>

    @if((!empty($mall['lat']) && !empty($mall['lng'])) || !empty($mall['address']))
    <div class="glass rounded-3xl p-6 mb-8">
        <h2 data-i18n="location" class="font-display text-2xl font-bold mb-4">Lokasi</h2>
        <div id="mall-map" class="rounded-2xl" style="height: 400px;"></div>
        <p id="mall-map-status" class="text-sm text-gray-400 mt-3" role="status" aria-live="polite"></p>
        <a class="inline-block text-sm text-neon-cyan mt-3 hover:underline"
           href="https://www.openstreetmap.org/search?query={{ urlencode(($mall['address'] ?? $mall['name']).', '.$mall['city'].', '.$mall['province'].', Indonesia') }}"
           target="_blank" rel="noopener noreferrer" data-i18n="maps_open">Buka di OpenStreetMap</a>
        <button data-i18n="nearby_button" type="button" id="load-nearby" class="glass mt-4 px-5 py-3 rounded-xl hover:border-neon-cyan/50">
            Cari masjid, ATM, dan toilet di sekitar
        </button>
        <p id="nearby-status" class="text-sm text-gray-400 mt-3" role="status" aria-live="polite"></p>
        <div id="nearby-results" class="grid md:grid-cols-2 gap-3 mt-4"></div>
    </div>
    @endif
    @if(!empty($mall['hours']) || !empty($mall['website']) || !empty($mall['floors']))
    <div class="glass rounded-3xl p-6 mb-8 grid md:grid-cols-3 gap-4">
        @if(!empty($mall['hours']))
        <div><p data-i18n="mall_hours" class="text-sm text-gray-400">Jam operasional</p><p class="mt-1">{{ $mall['hours'] }}</p></div>
        @endif
        @if(!empty($mall['website']))
        <div><p data-i18n="mall_website" class="text-sm text-gray-400">Situs web</p><a class="mt-1 text-neon-cyan hover:underline" href="{{ $mall['website'] }}" target="_blank" rel="noopener noreferrer">{{ $mall['website'] }}</a></div>
        @endif
        @if(!empty($mall['floors']))
        <div><p class="text-sm text-gray-400">Floors</p><p class="mt-1">{{ $mall['floors'] }}</p></div>
        @endif
    </div>
    @endif

    <div class="glass rounded-3xl p-8 mb-8">
        <h2 data-i18n="accessibility" class="font-display text-2xl font-bold mb-4">Aksesibilitas</h2>
        <div class="grid md:grid-cols-3 gap-4">
            @foreach(['wheelchair' => 'Akses kursi roda', 'parking' => 'Parkir', 'elevator' => 'Elevator'] as $key => $label)
            <div class="glass rounded-xl p-4">
                <p data-i18n="accessibility_{{ $key }}" class="text-sm text-gray-400">{{ $label }}</p>
                @if(!isset($mall[$key]))
                    <p data-i18n="not_recorded" class="mt-1">Informasi belum tersedia</p>
                @elseif(in_array(strtolower($mall[$key]), ['true', '1', 'yes'], true))
                    <p data-i18n="available" class="mt-1 text-green-300">Tersedia</p>
                @else
                    <p data-i18n="not_recorded" class="mt-1 text-amber-200">Tidak tercatat</p>
                @endif
            </div>
            @endforeach
        </div>
        <p data-i18n="accessibility_note" class="text-xs text-gray-500 mt-4">Informasi aksesibilitas hanya ditampilkan jika tersedia di RDF; status yang tidak tercatat tidak dianggap tidak tersedia.</p>
    </div>

    <div class="glass rounded-3xl p-8">
        <div class="flex justify-between items-center mb-6">
            <h2 data-i18n="stores_in_mall" class="font-display text-2xl font-bold">Toko di Mall Ini</h2>
            <span class="glass px-4 py-2 rounded-full text-sm">{{ count($stores) }} <span data-i18n="store_count">toko</span></span>
        </div>
        @php($categories = collect($stores)->pluck('category')->filter()->unique()->sort())
        @php($priceLevels = collect($stores)->pluck('priceLevel')->filter()->unique()->sort())
        <div class="glass rounded-2xl p-4 mb-5">
        <p class="text-xs text-gray-400 mb-2" data-i18n="filter_category">Kategori</p>
        <div class="bubble-filter-row mb-3" role="group" aria-label="Filter stores by category">
            <button type="button" data-store-category="" class="bubble-filter bubble-filter--active" aria-pressed="true" data-i18n="filter_all">Semua</button>
            @foreach($categories as $category)
            <button type="button" data-store-category="{{ $category }}" class="bubble-filter" aria-pressed="false">{{ $category }}</button>
            @endforeach
        </div>
        <p class="text-xs text-gray-400 mb-2" data-i18n="filter_rating_price">Rating dan kisaran harga</p>
        <div class="bubble-filter-row" role="group" aria-label="Filter stores by rating and price">
            <button type="button" data-store-rating="0" class="bubble-filter bubble-filter--active" aria-pressed="true" data-i18n="filter_any_rating">Semua rating</button>
            <button type="button" data-store-rating="4" class="bubble-filter" aria-pressed="false">★ 4.0+</button>
            <button type="button" data-store-rating="4.5" class="bubble-filter" aria-pressed="false">★ 4.5+</button>
            <button type="button" data-store-price="" class="bubble-filter bubble-filter--active" aria-pressed="true" data-i18n="filter_any_price">Semua harga</button>
            @foreach($priceLevels as $priceLevel)
            <button type="button" data-store-price="{{ $priceLevel }}" class="bubble-filter" aria-pressed="false">Rp {{ $priceLevel }} / 4</button>
            @endforeach
        </div>
        <p class="text-xs text-gray-500 mt-3"><span data-i18n="showing">Menampilkan</span> <strong id="store-filter-count">{{ count($stores) }}</strong> <span data-i18n="store_count">toko</span></p>
        </div>

        <div class="grid md:grid-cols-2 gap-4" id="stores-container">
            @forelse($stores as $store)
            <article class="glass rounded-xl p-5 hover:border-neon-cyan/50 transition-all store-item"
                     data-category="{{ $store['category'] }}" data-rating="{{ $store['rating'] ?? '' }}"
                     data-price-level="{{ $store['priceLevel'] ?? '' }}" typeof="schema:Store">
                <div class="flex justify-between items-start mb-3">
                    <div class="flex items-start gap-3">
                        @if(!empty($store['image']))
                        <img src="{{ $store['image'] }}" alt="{{ $store['storeName'] }} logo" class="w-14 h-14 rounded-lg bg-white object-contain p-1" loading="lazy" decoding="async">
                        @endif
                        <div>
                        <h3 class="font-display font-bold text-lg" property="schema:name">{{ $store['storeName'] }}</h3>
                        <p class="text-sm text-gray-400">{{ $store['category'] }}</p>
                        </div>
                    </div>
                    @if(!empty($store['rating']))
                    <span class="text-sm font-semibold">★ {{ $store['rating'] }}</span>
                    @endif
                </div>
                @if(!empty($store['status']))
                <p class="mt-3 text-xs {{ $store['status'] === 'OPERATIONAL' ? 'text-green-400' : 'text-red-400' }}">
                    @if($store['status'] === 'OPERATIONAL')
                    <span data-i18n="operational">Operasional</span>
                    @else
                    {{ $store['status'] }}
                    @endif
                </p>
                @endif
                @if(!empty($store['floor']))
                <p class="text-xs text-gray-400 mt-2"><span data-i18n="store_floor">Lantai</span>: {{ $store['floor'] }}</p>
                @endif
                @if(!empty($store['priceLevel']))
                <p class="text-xs text-gray-400 mt-2">Price level {{ $store['priceLevel'] }} / 4</p>
                @endif
            </article>
            @empty
            <p data-i18n="stores_empty" class="text-gray-400">Belum ada data toko untuk mall ini.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    let category = '';
    let minimumRating = 0;
    let priceLevel = '';
    const buttons = document.querySelectorAll('[data-store-category], [data-store-rating], [data-store-price]');
    const stores = Array.from(document.querySelectorAll('.store-item'));

    function updateStores() {
        let visible = 0;
        stores.forEach(store => {
            const matches = (!category || store.dataset.category === category)
                && (!minimumRating || Number(store.dataset.rating) >= minimumRating)
                && (!priceLevel || store.dataset.priceLevel === priceLevel);
            store.hidden = !matches;
            if (matches) visible++;
        });
        document.getElementById('store-filter-count').textContent = String(visible);
    }

    buttons.forEach(button => button.addEventListener('click', () => {
        if (button.hasAttribute('data-store-category')) category = button.dataset.storeCategory;
        if (button.hasAttribute('data-store-rating')) {
            const selectedRating = Number(button.dataset.storeRating);
            minimumRating = minimumRating === selectedRating ? 0 : selectedRating;
        }
        if (button.hasAttribute('data-store-price')) {
            priceLevel = priceLevel === button.dataset.storePrice ? '' : button.dataset.storePrice;
        }

        buttons.forEach(item => {
            const active = (item.hasAttribute('data-store-category') && item.dataset.storeCategory === category)
                || (item.hasAttribute('data-store-rating') && Number(item.dataset.storeRating) === minimumRating)
                || (item.hasAttribute('data-store-price') && item.dataset.storePrice === priceLevel);
            item.classList.toggle('bubble-filter--active', active);
            item.setAttribute('aria-pressed', String(active));
        });
        updateStores();
    }));

    updateStores();
});

@if((!empty($mall['lat']) && !empty($mall['lng'])) || !empty($mall['address']))
const mapElement = document.getElementById('mall-map');
const mapStatus = document.getElementById('mall-map-status');
const initialLat = {{ Illuminate\Support\Js::from($mall['lat'] ?? '') }};
const initialLng = {{ Illuminate\Support\Js::from($mall['lng'] ?? '') }};
const mallSearchQuery = {{ Illuminate\Support\Js::from($mall['name'].', '.$mall['city'].', '.$mall['province'].', Indonesia') }};
const mallAddressFallback = {{ Illuminate\Support\Js::from(!empty($mall['address']) ? $mall['address'].', '.$mall['city'].', '.$mall['province'].', Indonesia' : '') }};
const mallName = {{ Illuminate\Support\Js::from($mall['name']) }};
let mallMap;
let mallPosition = initialLat !== '' && initialLng !== ''
    ? [Number(initialLat), Number(initialLng)]
    : null;

async function getMallPosition() {
    if (mallPosition) return mallPosition;
    mapStatus.textContent = window.sumateraTranslate('map_missing_coordinates');
    const params = new URLSearchParams({ query: mallSearchQuery });
    if (mallAddressFallback.trim()) params.set('fallback', mallAddressFallback);
    const response = await fetch(`{{ route('map.geocode') }}?${params}`);
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || window.sumateraTranslate('maps_location_failed'));
    mallPosition = [Number(data.lat), Number(data.lng)];
    return mallPosition;
}

async function initializeMallMap() {
    try {
        const L = window.sumateraLeaflet;
        mallMap = L.map(mapElement).setView([0.5, 101], 5);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(mallMap);
        const position = await getMallPosition();
        mallMap.setView(position, 16);
        nearbyMarkerLayer = window.sumateraLeaflet.layerGroup().addTo(mallMap);
        const popup = document.createElement('span');
        popup.textContent = mallName;
        L.marker(position, { icon: L.divIcon({ className: 'mall-map-marker', html: '<span></span>', iconSize: [24, 24], iconAnchor: [12, 12] }) })
            .bindPopup(popup)
            .addTo(mallMap);
        mapStatus.textContent = '';
    } catch (error) {
        mapStatus.textContent = error.message || window.sumateraTranslate('maps_load_failed');
    }
}

document.addEventListener('DOMContentLoaded', initializeMallMap);

let nearbyMarkerLayer;

function getCurrentPosition() {
    if (!navigator.geolocation) return Promise.resolve(null);

    return new Promise(resolve => {
        navigator.geolocation.getCurrentPosition(
            position => resolve([position.coords.latitude, position.coords.longitude]),
            () => resolve(null),
            { enableHighAccuracy: false, maximumAge: 60000, timeout: 8000 }
        );
    });
}

function distanceInMeters(from, to) {
    const radians = degrees => degrees * Math.PI / 180;
    const latitudeDelta = radians(to[0] - from[0]);
    const longitudeDelta = radians(to[1] - from[1]);
    const a = Math.sin(latitudeDelta / 2) ** 2
        + Math.cos(radians(from[0])) * Math.cos(radians(to[0])) * Math.sin(longitudeDelta / 2) ** 2;

    return 6371000 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function formatStraightLineDistance(meters) {
    return meters >= 1000 ? `${(meters / 1000).toFixed(1)} km` : `${Math.round(meters)} m`;
}

document.getElementById('load-nearby').addEventListener('click', async event => {
    const button = event.currentTarget;
    button.disabled = true;
    const status = document.getElementById('nearby-status');
    status.textContent = window.sumateraTranslate('nearby_loading');
    try {
        const currentPosition = await getCurrentPosition();
        const position = currentPosition || await getMallPosition();
        const locationMessage = window.sumateraTranslate(currentPosition ? 'nearby_origin_current' : 'nearby_origin_mall');
        const query = new URLSearchParams({ lat: position[0], lng: position[1] });
        const response = await fetch(`{{ route('map.nearby') }}?${query}`);
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || window.sumateraTranslate('nearby_unavailable'));
        const results = document.getElementById('nearby-results');
        results.replaceChildren();
        const amenities = data.amenities
            .map(amenity => ({
                ...amenity,
                distance: distanceInMeters(position, [Number(amenity.lat), Number(amenity.lng)])
            }))
            .sort((left, right) => left.distance - right.distance);

        if (nearbyMarkerLayer) nearbyMarkerLayer.clearLayers();
        if (mallMap) {
            mallMap.setView(position, 16);
            nearbyMarkerLayer = nearbyMarkerLayer || window.sumateraLeaflet.layerGroup().addTo(mallMap);
            const originMarker = window.sumateraLeaflet.circleMarker(position, {
                radius: 8,
                color: '#ffffff',
                weight: 2,
                fillColor: '#1686ff',
                fillOpacity: 1
            });
            originMarker.bindPopup(document.createTextNode(window.sumateraTranslate(currentPosition ? 'nearby_origin_current' : 'nearby_origin_mall')));
            nearbyMarkerLayer.addLayer(originMarker);
        }

        amenities.forEach(amenity => {
            const item = document.createElement('div');
            item.className = 'glass rounded-xl p-4';
            const name = document.createElement('strong');
            name.textContent = amenity.name;
            const type = document.createElement('p');
            type.className = 'text-sm text-gray-400';
            type.textContent = window.sumateraTranslate(amenity.type === 'ATM'
                ? 'amenity_atm'
                : amenity.type === 'Restroom' ? 'amenity_restroom' : 'amenity_mosque');
            const distance = document.createElement('p');
            distance.className = 'text-xs text-gray-400 mt-2';
            distance.textContent = window.sumateraTranslate('nearby_distance')
                .replace(':distance', formatStraightLineDistance(amenity.distance));
            item.append(name, type, distance);
            results.append(item);
            if (nearbyMarkerLayer) {
                const L = window.sumateraLeaflet;
                const popup = document.createElement('span');
                popup.textContent = `${amenity.name} (${amenity.type})`;
                nearbyMarkerLayer.addLayer(L.marker([Number(amenity.lat), Number(amenity.lng)], {
                    icon: L.divIcon({ className: 'amenity-map-marker', html: '<span></span>', iconSize: [18, 18], iconAnchor: [9, 9] })
                }).bindPopup(popup));
            }
        });
        const resultMessage = amenities.length
            ? window.sumateraTranslate('nearby_found').replace(':count', amenities.length)
            : window.sumateraTranslate('nearby_none');
        status.textContent = `${locationMessage} ${resultMessage}`;
    } catch (error) {
        status.textContent = error.message || window.sumateraTranslate('nearby_failed');
    } finally {
        button.disabled = false;
    }
});
@endif
</script>
@endpush
