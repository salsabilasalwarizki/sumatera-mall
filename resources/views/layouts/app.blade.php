<!DOCTYPE html>
<html lang="id" prefix="schema: <http://schema.org/> mm: <http://sumateramall.org/ontology#>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>try { document.documentElement.dataset.theme = localStorage.getItem('sumateramall-theme') || 'dark'; } catch (error) { document.documentElement.dataset.theme = 'dark'; }</script>
    <title>@yield('title', 'SumatraMall Semantic Explorer')</title>

    <meta property="og:title" content="@yield('og_title', 'SumatraMall Semantic Explorer')" />
    <meta property="og:description" content="@yield('og_description', 'Sistem pencarian mall di Pulau Sumatera berbasis Web Semantik')" />
    <meta property="og:image" content="@yield('og_image', asset('images/og-default.jpg'))" />
    <meta property="og:type" content="@yield('og_type', 'website')" />
    <meta property="og:url" content="{{ url()->current() }}" />

    <script>window.sumateraGoogleMapsKey = {{ Illuminate\Support\Js::from(config('services.google_maps.browser_key')) }};</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="animated-bg min-h-screen text-white font-body">

    <nav class="site-nav fixed top-4 left-1/2 -translate-x-1/2 z-50 w-[95%] max-w-7xl"
         aria-label="Main navigation" data-i18n-aria-label="nav_aria">
        <div class="site-nav__inner glass-strong rounded-2xl px-4 py-3 shadow-2xl">
            <a href="{{ route('home') }}" class="site-nav__brand" aria-label="SumatraMall home">
                <img src="{{ asset('images/logo-sumateramall.png') }}" alt="" width="42" height="42">
                <span>SumatraMall</span>
            </a>
            <a href="{{ route('home') }}" data-i18n="nav_home" class="site-nav__link">Home</a>
            <a href="{{ route('team') }}" data-i18n="nav_team" class="site-nav__link">Team</a>
            <form action="{{ route('search.filter') }}" method="GET" class="site-nav__search" role="search">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="11" cy="11" r="7" stroke-width="2"></circle>
                    <path d="m16 16 4 4" stroke-width="2" stroke-linecap="round"></path>
                </svg>
                <label class="sr-only" for="site-search" data-i18n="nav_search_label">Search stores and malls</label>
                <input id="site-search" type="search" name="search" value="{{ request('search') }}"
                       data-i18n-placeholder="nav_search_placeholder"
                       placeholder="Search stores, categories, or malls…"
                       autocomplete="off" maxlength="100" required>
                <button type="button" class="site-nav__voice" data-voice-search data-voice-input="site-search"
                        aria-label="Search by voice" title="Search by voice">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path d="M12 15a3 3 0 0 0 3-3V6a3 3 0 0 0-6 0v6a3 3 0 0 0 3 3Z" stroke-width="1.8"></path>
                        <path d="M19 11a7 7 0 0 1-14 0m7 7v4m-4 0h8" stroke-width="1.8" stroke-linecap="round"></path>
                    </svg>
                </button>
                <button type="submit" data-i18n="nav_search_button">Search</button>
            </form>
            <span class="sr-only" data-voice-search-status data-voice-status-for="site-search" role="status" aria-live="polite"></span>
            <div class="site-nav__controls" aria-label="Display and language settings" data-i18n-aria-label="preferences_aria">
                <div class="site-nav__theme" role="group" aria-label="Color theme" data-i18n-aria-label="theme_group_aria">
                    <button type="button" class="site-nav__icon-button" data-theme-choice="light"
                            aria-label="Light mode" title="Light mode">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <circle cx="12" cy="12" r="4" stroke-width="1.8"></circle>
                            <path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42" stroke-width="1.8" stroke-linecap="round"></path>
                        </svg>
                        <span class="sr-only" data-i18n="theme_light">Light mode</span>
                    </button>
                    <button type="button" class="site-nav__icon-button" data-theme-choice="dark"
                            aria-label="Dark mode" title="Dark mode">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path d="M20.2 15.1A8.6 8.6 0 0 1 8.9 3.8 8.8 8.8 0 1 0 20.2 15.1Z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                        <span class="sr-only" data-i18n="theme_dark">Dark mode</span>
                    </button>
                </div>
                <label class="sr-only" for="site-language" data-i18n="language_label">Language</label>
                <select id="site-language" class="site-nav__language" aria-label="Select language">
                    <option value="id">Bahasa Indonesia</option>
                    <option value="en">English</option>
                    <option value="es">Español</option>
                    <option value="fr">Français</option>
                    <option value="zh">中文</option>
                    <option value="ja">日本語</option>
                </select>
            </div>
        </div>
    </nav>

    <main class="pt-32 pb-20">
        @yield('content')
    </main>

    <footer class="glass border-t border-white/10 py-8 mt-20">
        <div class="max-w-7xl mx-auto px-6 text-center">
            <p data-i18n="footer_copyright" class="text-sm text-gray-400">&copy; 2026 SumatraMall Semantic Explorer</p>
            <p data-i18n="footer_coverage" class="text-xs text-gray-500 mt-2">Data mall dari 10 provinsi di Pulau Sumatera</p>
        </div>
    </footer>

    @include('components.filter-dock')
    @include('components.chatbox')
    @stack('scripts')
</body>
</html>