<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#0B1020">
    <meta name="robots" content="noindex">
    <title>@yield('title') · RIVALO</title>
    <style>
        :root {
            --bg: #0B1020; --surface: #182238; --text: #F5F7FA; --text-2: #A6B0C3;
            --border: #2A3650; --lime: #C7FF3D; --on-lime: #0B1020;
            --success: #35D07F; --error: #FF626D;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: grid; place-items: center;
            padding: max(24px, env(safe-area-inset-top)) 16px max(24px, env(safe-area-inset-bottom));
            background: var(--bg); color: var(--text);
            font: 17px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        main { width: 100%; max-width: 440px; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 18px; padding: 28px 24px; margin-top: 24px; }
        h1 { font-size: 28px; line-height: 1.2; margin: 0 0 8px; letter-spacing: .01em; }
        p { color: var(--text-2); margin: 0 0 12px; }
        label { display: block; font-size: 15px; font-weight: 600; margin: 16px 0 6px; }
        input {
            width: 100%; min-height: 48px; padding: 12px 14px; font: inherit; color: var(--text);
            background: #111A2E; border: 1px solid var(--border); border-radius: 12px;
        }
        input:focus-visible { outline: 2px solid var(--lime); outline-offset: 2px; }
        .hint { font-size: 13px; color: var(--text-2); margin-top: 6px; }
        .error { color: var(--error); font-size: 14px; margin-top: 6px; }
        button {
            width: 100%; min-height: 52px; margin-top: 24px; border: 0; border-radius: 14px; cursor: pointer;
            background: var(--lime); color: var(--on-lime); font: 700 17px/1 inherit;
        }
        button:focus-visible { outline: 2px solid var(--text); outline-offset: 3px; }
        .status { display: inline-flex; align-items: center; gap: 8px; font-weight: 700; font-size: 15px; margin-bottom: 12px; }
        .status.success { color: var(--success); }
        .status.error { color: var(--error); }
        a { color: var(--lime); }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; animation: none !important; } }
    </style>
</head>
<body>
<main>
    <x-wordmark :height="30" :tagline="true" />
    @yield('content')
</main>
</body>
</html>
