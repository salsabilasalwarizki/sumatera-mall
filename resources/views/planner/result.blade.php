@extends('layouts.app')

@section('title', 'Hasil Route Planner')

@section('content')
<div class="max-w-5xl mx-auto px-6">
    <div class="mb-8">
        <h1 data-i18n="route_result_title" class="font-display text-5xl font-bold mb-3">Rute Optimal Anda</h1>
        <p class="text-gray-400"><span data-i18n="route_through">Sumber rute:</span> {{ $source === 'openstreetmap' ? 'OpenStreetMap / OSRM' : 'Straight-line estimate' }}.</p>
    </div>

    @if($notice)
    <div data-i18n="route_estimate_notice" class="glass rounded-xl p-4 mb-6 border border-amber-400/40 text-amber-200">{{ $notice }}</div>
    @endif

    <div class="grid md:grid-cols-3 gap-6 mb-8">
        <div class="glass rounded-2xl p-6 text-center">
            <div class="font-display text-3xl font-bold gradient-text mb-2">
                {{ number_format($totalDistance / 1000, 1) }} km
            </div>
            <div data-i18n="route_total_distance" class="text-sm text-gray-400">Total Jarak</div>
        </div>
        <div class="glass rounded-2xl p-6 text-center">
            <div class="font-display text-3xl font-bold gradient-text mb-2">
                {{ floor($totalDuration / 3600) }} <span data-i18n="hours">jam</span> {{ floor(($totalDuration % 3600) / 60) }} <span data-i18n="minutes">menit</span>
            </div>
            <div data-i18n="route_total_duration" class="text-sm text-gray-400">Total Waktu</div>
        </div>
        <div class="glass rounded-2xl p-6 text-center">
            <div class="font-display text-3xl font-bold gradient-text mb-2">
                {{ count($malls) }}
            </div>
            <div data-i18n="route_malls_visited" class="text-sm text-gray-400">Mall Dikunjungi</div>
        </div>
    </div>

    <div class="glass rounded-3xl p-8 mb-8">
        <h2 data-i18n="route_visit_order" class="font-display text-2xl font-bold mb-6">Urutan Kunjungan</h2>
        <div id="route-map" class="mb-6 rounded-xl" style="height: 380px;"></div>
        <div class="flex flex-wrap items-center gap-3 mb-4">
            <button id="route-navigation-toggle" type="button" aria-pressed="false"
                    class="chat-gold-gradient text-amber-950 px-5 py-3 rounded-xl font-semibold"
                    data-i18n="route_follow_start">Mulai ikuti rute</button>
            <p id="route-navigation-status" class="text-sm text-gray-400" role="status" aria-live="polite"></p>
        </div>
        <p id="route-map-status" class="text-sm text-gray-400 mb-4" role="status" aria-live="polite"></p>
        <div class="space-y-4">
            @foreach($malls as $index => $mall)
            <div class="flex items-center gap-4 glass rounded-xl p-4">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-neon-cyan to-neon-purple flex items-center justify-center font-bold text-black">
                    {{ $index + 1 }}
                </div>
                <div class="flex-1">
                    <h3 class="font-display font-bold">{{ $mall['name'] }}</h3>
                    <p class="text-sm text-gray-400">{{ $mall['city'] }}</p>
                </div>
                @php($legIndex = $startIsMall ? $index - 1 : $index)
                @if($legIndex >= 0 && isset($legs[$legIndex]))
                <div class="text-right">
                    <div class="text-sm text-neon-cyan">{{ $legs[$legIndex]['distance_text'] }}</div>
                    <div class="text-xs text-gray-400">{{ $legs[$legIndex]['duration_text'] }}</div>
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>

    <div class="flex gap-4">
        <a id="osm-directions-link" href="#" target="_blank" rel="noopener noreferrer"
           class="glass px-6 py-3 rounded-xl font-semibold hover:bg-white/10 transition-all border border-white/20"
           data-i18n="osm_directions">Buka rute di OpenStreetMap</a>
        <a href="{{ route('planner.index') }}" class="glass px-6 py-3 rounded-xl font-semibold hover:bg-white/10 transition-all border border-white/20">
            <span data-i18n="route_new">Buat Rute Baru</span>
        </a>
        <a data-i18n="route_export_pdf" href="{{ route('planner.export.pdf') }}" class="glass px-6 py-3 rounded-xl font-semibold hover:bg-white/10 transition-all border border-white/20">Unduh PDF</a>
        <a data-i18n="route_export_ics" href="{{ route('planner.export.ics') }}" class="glass px-6 py-3 rounded-xl font-semibold hover:bg-white/10 transition-all border border-white/20">Unduh Calendar (.ics)</a>
    </div>
</div>

@push('scripts')
<script>
const routePoints = @js($routeCoordinates);
const routeMalls = @js($malls);
const routeMapStatus = document.getElementById('route-map-status');
const routeLocations = @js($startIsMall ? $malls : array_merge([$origin], $malls));
const routeStartsAtMall = @js($startIsMall);
const routeStops = routeStartsAtMall ? routeMalls.slice(1) : routeMalls;
const navigationButton = document.getElementById('route-navigation-toggle');
const navigationStatus = document.getElementById('route-navigation-status');
let routeMap;
let currentLocationMarker;
let navigationWatchId = null;
let nextStopIndex = 0;
const routeParameter = routeLocations
    .filter(location => Number.isFinite(Number(location.lat)) && Number.isFinite(Number(location.lng)))
    .map(location => `${Number(location.lat)},${Number(location.lng)}`)
    .join(';');
document.getElementById('osm-directions-link').href =
    `https://www.openstreetmap.org/directions?engine=fossgis_osrm_car&route=${encodeURIComponent(routeParameter)}`;

async function renderRouteMap() {
    try {
        const L = window.sumateraLeaflet;
        routeMap = L.map('route-map');
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(routeMap);
        const bounds = L.latLngBounds(routePoints);
        if (routePoints.length) L.polyline(routePoints, { color: '#bd8f2f', opacity: 0.9, weight: 5 }).addTo(routeMap);
        routeMalls.forEach((mall, index) => {
            if (!Number.isFinite(Number(mall.lat)) || !Number.isFinite(Number(mall.lng))) return;
            const position = [Number(mall.lat), Number(mall.lng)];
            bounds.extend(position);
            const popup = document.createElement('span');
            popup.textContent = `${index + 1}. ${mall.name}`;
            L.marker(position, {
                icon: L.divIcon({ className: 'mall-map-marker', html: `<span>${index + 1}</span>`, iconSize: [28, 28], iconAnchor: [14, 14] })
            }).bindPopup(popup).addTo(routeMap);
        });
        if (routePoints.length) routeMap.fitBounds(bounds, { padding: [24, 24] });
    } catch (error) {
        routeMapStatus.textContent = error.message || window.sumateraTranslate('maps_load_failed');
    }
}

document.addEventListener('DOMContentLoaded', renderRouteMap);

function distanceInMeters(from, to) {
    const radians = value => value * Math.PI / 180;
    const latitudeDelta = radians(to.lat - from.lat);
    const longitudeDelta = radians(to.lng - from.lng);
    const a = Math.sin(latitudeDelta / 2) ** 2
        + Math.cos(radians(from.lat)) * Math.cos(radians(to.lat)) * Math.sin(longitudeDelta / 2) ** 2;
    return 6371000 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function updateNavigation(position) {
    const current = {
        lat: position.coords.latitude,
        lng: position.coords.longitude
    };
    const L = window.sumateraLeaflet;
    if (!currentLocationMarker) {
        currentLocationMarker = L.circleMarker([current.lat, current.lng], {
            radius: 8,
            color: '#fff',
            weight: 2,
            fillColor: '#bd8f2f',
            fillOpacity: 1
        }).addTo(routeMap).bindPopup(window.sumateraTranslate('location'));
    } else {
        currentLocationMarker.setLatLng([current.lat, current.lng]);
    }

    routeMap.panTo([current.lat, current.lng]);
    while (nextStopIndex < routeStops.length
        && distanceInMeters(current, {
            lat: Number(routeStops[nextStopIndex].lat),
            lng: Number(routeStops[nextStopIndex].lng)
        }) <= 120) {
        nextStopIndex++;
    }

    if (nextStopIndex >= routeStops.length) {
        navigationStatus.textContent = window.sumateraTranslate('route_follow_arrived');
        return;
    }

    const nextStop = routeStops[nextStopIndex];
    const distance = distanceInMeters(current, {
        lat: Number(nextStop.lat),
        lng: Number(nextStop.lng)
    });
    const distanceText = distance >= 1000
        ? `${(distance / 1000).toFixed(1)} km`
        : `${Math.round(distance)} m`;
    navigationStatus.textContent = window.sumateraTranslate('route_follow_next')
        .replace(':mall', nextStop.name)
        .replace(':distance', distanceText);
}

navigationButton.addEventListener('click', () => {
    if (navigationWatchId !== null) {
        navigator.geolocation.clearWatch(navigationWatchId);
        navigationWatchId = null;
        navigationButton.textContent = window.sumateraTranslate('route_follow_start');
        navigationButton.setAttribute('aria-pressed', 'false');
        return;
    }

    if (!navigator.geolocation) {
        navigationStatus.textContent = window.sumateraTranslate('geolocation_unsupported');
        return;
    }

    navigationStatus.textContent = window.sumateraTranslate('route_follow_waiting');
    navigationWatchId = navigator.geolocation.watchPosition(
        updateNavigation,
        error => {
            navigationStatus.textContent = error.code === error.PERMISSION_DENIED
                ? window.sumateraTranslate('geolocation_failed')
                : error.message || window.sumateraTranslate('geolocation_failed');
            navigator.geolocation.clearWatch(navigationWatchId);
            navigationWatchId = null;
            navigationButton.textContent = window.sumateraTranslate('route_follow_start');
            navigationButton.setAttribute('aria-pressed', 'false');
        },
        { enableHighAccuracy: true, maximumAge: 3000, timeout: 15000 }
    );
    navigationButton.textContent = window.sumateraTranslate('route_follow_stop');
    navigationButton.setAttribute('aria-pressed', 'true');
});

window.addEventListener('pagehide', () => {
    if (navigationWatchId !== null) navigator.geolocation.clearWatch(navigationWatchId);
});
</script>
@endpush
@endsection