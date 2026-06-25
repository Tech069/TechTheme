<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        @php
            $themeRaw    = '{}';
            $addonsRaw   = '{}';
            $themeMeta   = null;
            $pwaSettings = null;
            $adsSettings = null;
            $themeData   = [];
            $themeVars   = null;
            $themeEnforce = false;
            $addonsDecoded = [];

            $__tScope = request()->getHost();

            $__remember = function (string $key, int $ttlSeconds, callable $callback) {
                return \Illuminate\Support\Facades\Cache::remember(
                    $key,
                    now()->addSeconds($ttlSeconds),
                    $callback
                );
            };

            try {


                [$themeRaw, $themeDecoded] = $__remember('wrap:theme:decoded:' . $__tScope, 600, function () {
                    $repo = app(\Pterodactyl\Repositories\Eloquent\SettingsRepository::class);
                    $raw  = $repo->get('settings::app:theme:hyperv2', '{}');
                    return [$raw, json_decode($raw ?: '{}', true, 512, JSON_THROW_ON_ERROR) ?: []];
                });
                $themeMeta    = $themeDecoded['site']['meta'] ?? null;
                $themeVars    = $themeDecoded['variables'] ?? null;
                $themeEnforce = (bool) ($themeDecoded['enforce'] ?? false);
                $themeData    = $themeDecoded;

                [$addonsRaw, $addonsDecoded] = $__remember('wrap:addons:decoded:' . $__tScope, 600, function () {
                    $repo = app(\Pterodactyl\Repositories\Eloquent\SettingsRepository::class);
                    $raw  = $repo->get('settings::app:addons:hyperv2', '{}');
                    return [$raw, json_decode($raw ?: '{}', true, 512, JSON_THROW_ON_ERROR) ?: []];
                });
                $pwaSettings   = $addonsDecoded['addons']['pwa'] ?? null;
                $adsSettings   = $addonsDecoded['addons']['ads-layout'] ?? null;
            } catch (\Throwable $e) {
            }

            $pwaEnabled = $pwaSettings['enabled'] ?? false;
            $swInlineConfig = [
                'enabled' => (bool) ($pwaSettings['enabled'] ?? false),
                'cacheStrategy' => $pwaSettings['cache_strategy'] ?? 'cache-first',
                'cacheAssets' => (bool) ($pwaSettings['cache_assets'] ?? true),
                'cacheApi' => (bool) ($pwaSettings['cache_api'] ?? false),
                'cacheMaxAge' => ((int) ($pwaSettings['cache_max_age'] ?? 24)) * 60 * 60,
                'offlineEnabled' => (bool) ($pwaSettings['offline_enabled'] ?? true),
                'offlinePageTitle' => $pwaSettings['offline_page_title'] ?? 'You are offline',
                'offlinePageMessage' => $pwaSettings['offline_page_message'] ?? 'Please check your internet connection and try again.',
            ];

            if ($pwaEnabled && !empty($pwaSettings['app_name'])) {
                $appTitle = $pwaSettings['app_name'];
            } elseif ($themeMeta && !empty($themeMeta['title'])) {
                $appTitle = $themeMeta['title'];
            } else {
                $appTitle = config('app.name', 'Pterodactyl');
            }
        @endphp

        <title>{{ $appTitle }}</title>

        @section('meta')
            <meta charset="utf-8">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="csrf-token" content="{{ csrf_token() }}">
            <meta name="robots" content="index, follow">
            <meta name="application-name" content="{{ $appTitle }}">

            @if($themeMeta && isset($themeMeta['faviconUrl']) && !empty($themeMeta['faviconUrl']))
                <link rel="icon" href="{{ $themeMeta['faviconUrl'] }}">
            @else
                <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
                <link rel="icon" type="image/png" href="/favicons/favicon-32x32.png" sizes="32x32">
                <link rel="icon" type="image/png" href="/favicons/favicon-16x16.png" sizes="16x16">
                <link rel="mask-icon" href="/favicons/safari-pinned-tab.svg" color="#bc6e3c">
                <link rel="shortcut icon" href="/favicons/favicon.ico">
                <meta name="msapplication-config" content="/favicons/browserconfig.xml">
            @endif

            @if($pwaEnabled)
                <link rel="manifest" href="/api/public/pwa/manifest.json?v=3">
                <meta name="mobile-web-app-capable" content="yes">
                <meta name="apple-mobile-web-app-capable" content="yes">
                <meta name="apple-mobile-web-app-status-bar-style" content="{{ $pwaSettings['status_bar_style'] ?? 'default' }}">
                <meta name="apple-mobile-web-app-title" content="{{ $pwaSettings['app_short_name'] ?? $appTitle }}">
            @endif

            @if($themeMeta && isset($themeMeta['description']) && !empty($themeMeta['description']))
                <meta name="description" content="{{ Str::limit($themeMeta['description'], 300) }}">
            @endif

            @if($themeMeta && isset($themeMeta['image']) && !empty($themeMeta['image']))
                <meta property="og:image" content="{{ $themeMeta['image'] }}">
                <meta property="og:image:width" content="1200">
                <meta property="og:image:height" content="630">
                <meta name="twitter:card" content="summary_large_image">
                <meta name="twitter:image" content="{{ $themeMeta['image'] }}">
            @endif

            @if($themeMeta && isset($themeMeta['title']) && !empty($themeMeta['title']))
                <meta property="og:title" content="{{ $themeMeta['title'] }}">
                <meta name="twitter:title" content="{{ $themeMeta['title'] }}">
            @else
                <meta property="og:title" content="{{ config('app.name', 'Pterodactyl') }}">
                <meta name="twitter:title" content="{{ config('app.name', 'Pterodactyl') }}">
            @endif

            @if($themeMeta && isset($themeMeta['description']) && !empty($themeMeta['description']))
                <meta property="og:description" content="{{ Str::limit($themeMeta['description'], 300) }}">
                <meta name="twitter:description" content="{{ Str::limit($themeMeta['description'], 300) }}">
            @endif

            @if($themeMeta && isset($themeMeta['color']) && !empty($themeMeta['color']))
                <meta name="theme-color" content="{{ $themeMeta['color'] }}">
            @elseif($pwaEnabled && isset($pwaSettings['theme_color']) && !empty($pwaSettings['theme_color']))
                <meta name="theme-color" content="{{ $pwaSettings['theme_color'] }}">
            @else
                <meta name="theme-color" content="#df3050">
            @endif

            <meta property="og:type" content="website">
            <meta property="og:url" content="{{ url()->current() }}">
        @show
        @section('user-data')
            @php
                $__authUser = Auth::user();
                $__vueUserJson = null;
                if ($__authUser) {

                    $__vueUserJson = $__remember(
                        'wrap:vue-user:' . $__tScope . ':' . $__authUser->id,
                        120,
                        function () use ($__authUser) {
                            $__authUser->loadMissing('permissionRole');
                            return json_encode($__authUser->toVueObject(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
                        }
                    );
                }
                $__siteConfigJson = !empty($siteConfiguration)
                    ? $__remember(
                        'wrap:site-config:' . $__tScope . ':' . (config('app.locale') ?? 'en') . ':' . ($siteConfigurationFingerprint ?? 'none'),
                        300,
                        fn() => json_encode($siteConfiguration, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE)
                    )
                    : null;
            @endphp
            @if($__vueUserJson !== null)
                <script data-cfasync="false">
                    window.PterodactylUser = {!! $__vueUserJson !!};
                </script>
            @endif
            @if($__siteConfigJson !== null)
                <script data-cfasync="false">
                    window.SiteConfiguration = {!! $__siteConfigJson !!};
                </script>
            @endif

            @php
                $themeSettingsAddon = $addonsDecoded['addons']['theme-settings'] ?? [];
                $userPermissions = $themeSettingsAddon['userPermissions'] ?? [];
                $defaults = $themeSettingsAddon['defaults'] ?? [];

                if (!isset($themeData['site'])) {
                    $themeData['site'] = [];
                }

                $themeData['site']['userPermissions'] = [
                    'colors' => isset($userPermissions['colors']) ? (bool) $userPermissions['colors'] : true,
                    'background' => isset($userPermissions['background']) ? (bool) $userPermissions['background'] : true,
                    'notifications' => isset($userPermissions['notifications']) ? (bool) $userPermissions['notifications'] : true,
                    'privacy' => isset($userPermissions['privacy']) ? (bool) $userPermissions['privacy'] : true,
                ];
                $themeData['site']['defaults'] = [
                    'privacy' => [
                        'blur' => isset($defaults['privacy']['blur']) ? (bool) $defaults['privacy']['blur'] : false,
                    ],
                    'performance' => [
                        'blurEnabled' => isset($defaults['performance']['blurEnabled']) ? (bool) $defaults['performance']['blurEnabled'] : false,
                        'blurAmount'  => isset($defaults['performance']['blurAmount']) ? (int) $defaults['performance']['blurAmount'] : 0,
                        'radiusAmount' => isset($defaults['performance']['radiusAmount']) ? (int) $defaults['performance']['radiusAmount'] : -1,
                    ],
                ];
            @endphp
            @php
                $userLang = auth()->user()?->language ?? config('app.locale', 'en');
                $userLang = preg_replace('/[^a-z0-9_-]/i', '', $userLang) ?: 'en';
                $langPath = public_path("DGEN/themes/Hyperv2/lang/{$userLang}.json");
                if (!file_exists($langPath)) {
                    $userLang = 'en';
                    $langPath = public_path("DGEN/themes/Hyperv2/lang/en.json");
                }

                $inlineDictJson = null;
                $inlineLocaleResourcesJson = null;
                $__isAuthRoute = \Illuminate\Support\Str::startsWith(request()->path(), 'auth') || \Illuminate\Support\Facades\Auth::guest();
                if ($__isAuthRoute) {
                    $inlineDictJson = $__remember(
                        "wrap:lang:dict:auth:{$userLang}",
                        3600,
                        function () use ($langPath) {
                            if (!file_exists($langPath)) return null;
                            $data = json_decode(file_get_contents($langPath), true);
                            if (!is_array($data)) return null;
                            $prefixes = ['auth.', 'common.', 'sso.'];
                            $filtered = [];
                            foreach ($data as $key => $value) {
                                foreach ($prefixes as $prefix) {
                                    if (strncmp($key, $prefix, strlen($prefix)) === 0) {
                                        $filtered[$key] = $value;
                                        break;
                                    }
                                }
                            }
                            return json_encode($filtered, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        }
                    );

                    $inlineLocaleResourcesJson = $__remember(
                        "wrap:locale:json:{$userLang}",
                        86400,
                        function () use ($userLang) {
                            $cached = \Illuminate\Support\Facades\Cache::get("locale_json:{$userLang}:translation:json");
                            if ($cached !== null) return $cached;

                            $data = \Illuminate\Support\Facades\Cache::get("locale_json:{$userLang}:translation");

                            if ($data === null) {
                                $loader = app('translator')->getLoader();
                                $loaded = $loader->load($userLang, 'strings');
                                if (empty($loaded)) {
                                    $langPath = resource_path("lang/{$userLang}");
                                    if (is_dir($langPath)) {
                                        foreach (glob($langPath . '/*.php') as $file) {
                                            $group = basename($file, '.php');
                                            $groupData = $loader->load($userLang, $group);
                                            if (!empty($groupData)) {
                                                $loaded[$group] = $groupData;
                                            }
                                        }
                                    }
                                }
                                $i18n = function (array $d) use (&$i18n) {
                                    foreach ($d as $k => $v) {
                                        $d[$k] = is_array($v) ? $i18n($v) : preg_replace('/:([\w.-]+\w)([^\w:]?|$)/m', '{{$1}}$2', $v);
                                    }
                                    return $d;
                                };
                                $data = [$userLang => ['translation' => $i18n($loaded)]];
                                \Illuminate\Support\Facades\Cache::put("locale_json:{$userLang}:translation", $data, 86400);
                            }

                            $encoded = json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
                            \Illuminate\Support\Facades\Cache::put("locale_json:{$userLang}:translation:json", $encoded, 86400);
                            return $encoded;
                        }
                    );
                }

            @endphp
            <script data-cfasync="false">
                window.__I18N_LANG__ = {!! json_encode($userLang) !!};
            </script>

            @if($inlineDictJson !== null)
            <script data-cfasync="false">
                window.__I18N_DICT__ = {!! $inlineDictJson !!};
                window.__I18N_LANG_INLINE__ = {!! json_encode($userLang, JSON_HEX_TAG) !!};
            </script>
            @endif

            @if($inlineLocaleResourcesJson !== null)
            <script data-cfasync="false">
                window.__I18N_RESOURCES__ = {!! $inlineLocaleResourcesJson !!};
            </script>
            @endif

            @if(isset($errors) && $errors->any())
                <script data-cfasync="false">
                    window.__SERVER_ERRORS__ = {!! json_encode($errors->all()) !!};
                </script>
            @endif
        @show

        @php
            // LCP optimization for the auth/login page: its background image is a CSS
            // background on a <div>, so the browser can't discover it until JS renders
            // that div (late on an SPA). When the visitor is on /auth or is a guest
            // (protected routes redirect guests to auth), preload the configured auth
            // background image with fetchpriority=high so it loads immediately and is
            // discoverable from the initial HTML. Only absolute (http/https) URLs are
            // preloaded — those match the un-busted URL the React div uses, so there is
            // no double fetch.
            $__authBgUrl = null;
            $__onAuthView = \Illuminate\Support\Str::startsWith(request()->path(), 'auth')
                || \Illuminate\Support\Facades\Auth::guest();
            if ($__onAuthView) {
                $__authExp = $themeDecoded['site']['auth']['experience'] ?? [];
                if (empty($__authExp['disableBackgroundImage'])) {
                    $__authBg = $themeDecoded['site']['auth']['background']['custom'] ?? null;
                    if (!empty($__authBg['enabled']) && !empty($__authBg['url'])
                        && ($__authBg['type'] ?? 'image') === 'image') {
                        $__authBgUrl = $__authBg['url'];
                    } else {
                        $__siteBg = $themeDecoded['site']['background']['custom'] ?? null;
                        if (!empty($__siteBg['enabled']) && !empty($__siteBg['url'])
                            && ($__siteBg['type'] ?? 'image') === 'image') {
                            $__authBgUrl = $__siteBg['url'];
                        }
                    }
                }
            }
        @endphp
        @if($__authBgUrl && preg_match('#^https?://#i', $__authBgUrl))
            <link rel="preload" as="image" href="{{ $__authBgUrl }}" fetchpriority="high">
        @endif

        @php $__fontsCssV = @filemtime(public_path('assets/css/fonts.css')) ?: 0; @endphp
        <link rel="stylesheet" href="/assets/css/fonts.css?v={{ $__fontsCssV }}" media="print" onload="this.media='all'">

        @php
            $__isAuth   = Str::startsWith(request()->path(), 'auth');
            $__manifestPath = public_path('assets/manifest.json');
            $__manifestVer = ((string) (@filemtime($__manifestPath) ?: 0)) . ':' . ((string) (@filesize($__manifestPath) ?: 0));
            $__plKey    = 'wrap:preloads:' . ($__isAuth ? 'auth' : 'core') . ':' . substr($asset->integrity('main.js') ?: 'x', 7, 12) . ':' . $__manifestVer;
            $__preloadsHtml = $__remember($__plKey, 86400, function() use ($asset, $__isAuth) {
                $list = $__isAuth ? $asset->authPreloads() : $asset->preloads();
                $out  = '';
                foreach ($list as $key => $p) {
                    if (str_ends_with($p['src'], '.css')) {
                        $out .= '<link rel="preload" href="' . $p['src'] . '" as="style" crossorigin="anonymous">' . "\n        ";
                    } else {
                        $out .= '<link rel="modulepreload" href="' . $p['src'] . '" crossorigin="anonymous">' . "\n        ";
                    }
                }
                return $out;
            });
        @endphp
        {!! $__preloadsHtml !!}
        
        <noscript>
            <link rel="stylesheet" href="/assets/css/fonts.css?v={{ $__fontsCssV }}">
        </noscript>

        @php
            $__themeCssHtml = '';
            if ($themeVars && is_array($themeVars) && count($themeVars) > 0) {
                $__themeCssKey  = 'wrap:theme-css:' . $__tScope . ':' . substr(md5($themeRaw), 0, 16);
                $__themeCssHtml = $__remember($__themeCssKey, 3600, function() use ($themeVars, $themeEnforce) {
                    $parseRgb = function(?string $c): ?string {
                        if (!$c) return null; $c = trim($c);
                        if (preg_match('/^#([a-f0-9]{6})(?:[a-f0-9]{2})?$/i', $c, $m)) return hexdec(substr($m[1],0,2)) . ', ' . hexdec(substr($m[1],2,2)) . ', ' . hexdec(substr($m[1],4,2));
                        if (preg_match('/^#([a-f0-9]{3})$/i', $c, $m)) return hexdec(str_repeat($m[1][0],2)) . ', ' . hexdec(str_repeat($m[1][1],2)) . ', ' . hexdec(str_repeat($m[1][2],2));
                        if (preg_match('/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})/i', $c, $m)) return ((int)$m[1]) . ', ' . ((int)$m[2]) . ', ' . ((int)$m[3]);
                        return null;
                    };
                    $imp = $themeEnforce ? ' !important' : '';
                    $css = '<style id="hyper-theme-vars">:root{';
                    foreach ($themeVars as $k => $v) {
                        if (str_starts_with($k, '--hyper-') && !empty($v) && !str_ends_with($k, '-rgb')) {
                            $css .= $k . ':' . $v . $imp . ';';
                        }
                    }
                    $pr = $parseRgb($themeVars['--hyper-primary'] ?? null);
                    $bg = $parseRgb($themeVars['--hyper-background'] ?? null);
                    if ($pr) $css .= '--hyper-primary-rgb:' . $pr . $imp . ';';
                    if ($bg) $css .= '--hyper-background-rgb:' . $bg . $imp . ';';
                    $css .= '}</style>';
                    if (!empty($themeVars['--hyper-font-url'])) {
                        $fu  = htmlspecialchars($themeVars['--hyper-font-url'], ENT_QUOTES);
                        $css .= '<link rel="stylesheet" href="' . $fu . '" media="print" onload="this.media=\'all\'">';
                        $css .= '<noscript><link rel="stylesheet" href="' . $fu . '"></noscript>';
                    }
                    return $css;
                });
            }
        @endphp
        @php
            $__hyperCssV = @filemtime(public_path('assets/css/hyper.css')) ?: 0;
            $__mainCssHtml = $__remember(
                'wrap:maincss-links:' . substr($asset->integrity('main.css') ?: 'x', 7, 12)
                    . ':' . $__hyperCssV . ':' . $__manifestVer,
                86400,
                function () use ($asset, $__hyperCssV) {
                    $out = '';

                    // index.css is the large Tailwind bundle (~84 KB gz). It is loaded
                    // NON-render-blocking (preload + swap media on load, with a <noscript>
                    // fallback) so it stays off the critical render path (Lighthouse).
                    // This is FOUC-safe here: the only thing that paints before JS is the
                    // inline-styled InitialLoader (no Tailwind dependency), and index.css
                    // carries no theme variables, so deferring it never affects colors.
                    $url = $asset->url('main.css');
                    if ($url !== 'main.css') {
                        $int = $asset->integrity('main.css');
                        $out .= '<link rel="preload" href="' . $url . '" as="style" crossorigin="anonymous" integrity="' . $int . '">' . "\n"
                             .  '            <link rel="stylesheet" href="' . $url . '" media="print" onload="this.media=\'all\'" crossorigin="anonymous" integrity="' . $int . '">' . "\n"
                             .  '            <noscript><link rel="stylesheet" href="' . $url . '" crossorigin="anonymous" integrity="' . $int . '"></noscript>';
                    }

                    // hyper.css = theme-variable DEFAULTS + base. Kept as a render-blocking
                    // <link> (tiny, ~3.5 KB gz) so the defaults are present at first paint and
                    // BEFORE the custom theme-vars <style> that follows — i.e. colours set in
                    // Hyper Settings still override these correctly. Intentionally NOT inlined
                    // and NOT deferred to keep colour behaviour identical.
                    if ($__hyperCssV) {
                        $hyperUrl = '/assets/css/hyper.css?t=' . $__hyperCssV;
                        $out .= "\n            " . '<link rel="preload" href="' . $hyperUrl . '" as="style">'
                             .  "\n            " . '<link rel="stylesheet" href="' . $hyperUrl . '">';
                    }

                    return $out;
                }
            );
        @endphp
        {!! $__mainCssHtml !!}
        {!! $__themeCssHtml !!}
        <style>body{background-color:var(--hyper-background,#0c0a09);color:var(--hyper-text-primary,#fff);margin:0}</style>

        @yield('assets')

        @if(!empty($adsSettings['header_script']))
            {!! $adsSettings['header_script'] !!}
        @endif

    </head>
    <body class="{{ $css['body'] ?? 'bg-neutral-50' }}">
        @section('content')
            @yield('above-container')
            @yield('container')
            @yield('below-container')
        @show
        @section('scripts')
            <script type="module" defer src="{!! $asset->url('main.js') !!}" crossorigin="anonymous" integrity="{!! $asset->integrity('main.js') !!}"></script>
        @show
        
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    @if($pwaEnabled)
                    navigator.serviceWorker.register('/service-worker.js', { scope: '/' })
                        .then(function(registration) {
                            var SW_CONFIG_URL = '/api/public/pwa/sw-config.js?v=3';
                            var SW_CONFIG_VERSION = '{{ $addonsDecoded['updated_at'] ?? '0' }}';
                            var SW_CONFIG_CACHE_KEY = 'hyper:pwa:sw-config:v3:' + SW_CONFIG_VERSION;
                            var SW_INLINE_CONFIG = @json($swInlineConfig);

                            var postConfigToServiceWorker = function(config) {
                                if (!config) return;

                                if (registration.active) {
                                    registration.active.postMessage({
                                        type: 'PWA_CONFIG',
                                        config: config,
                                    });
                                    return;
                                }

                                navigator.serviceWorker.ready
                                    .then(function(readyRegistration) {
                                        if (readyRegistration.active) {
                                            readyRegistration.active.postMessage({
                                                type: 'PWA_CONFIG',
                                                config: config,
                                            });
                                        }
                                    })
                                    .catch(function() {
                                    });
                            };

                            var readCachedConfig = function() {
                                try {
                                    var rawConfig = localStorage.getItem(SW_CONFIG_CACHE_KEY);
                                    if (!rawConfig) return null;

                                    return JSON.parse(rawConfig);
                                } catch (_e) {
                                    return null;
                                }
                            };

                            var writeCachedConfig = function(config) {
                                try {
                                    localStorage.setItem(SW_CONFIG_CACHE_KEY, JSON.stringify(config));
                                } catch (_e) {
                                }
                            };

                            var cached = readCachedConfig();
                            if (cached) {
                                postConfigToServiceWorker(cached);
                                return;
                            }

                            if (SW_INLINE_CONFIG && typeof SW_INLINE_CONFIG === 'object') {
                                writeCachedConfig(SW_INLINE_CONFIG);
                                postConfigToServiceWorker(SW_INLINE_CONFIG);
                                return;
                            }

                            fetch(SW_CONFIG_URL + '&cfg=' + encodeURIComponent(SW_CONFIG_VERSION), { cache: 'no-store' })
                                .then(function(response) {
                                    if (!response.ok) {
                                        throw new Error('HTTP ' + response.status);
                                    }
                                    return response.json();
                                })
                                .then(function(config) {
                                    writeCachedConfig(config);
                                    postConfigToServiceWorker(config);
                                })
                                .catch(function() {
                                });
                        })
                        .catch(function() {
                        });
                    @else
                    navigator.serviceWorker.register('/service-worker.js', { scope: '/' });
                    @endif
                });
            }
        </script>

        @if(!empty($adsSettings['body_script']))
            {!! $adsSettings['body_script'] !!}
        @endif
    </body>
</html>
