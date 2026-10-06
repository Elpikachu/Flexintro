<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Flex demo') &middot; {{ $envName }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #ECEDEE;
            --surface: #FFFFFF;
            --text: #0C0F10;
            --muted: #646F75;
            --border: #D8DBDC;
            --violet: #6046FF;
            --lime: #D5F800;
            --radius: 10px;
            --sans: "Instrument Sans", Arial, sans-serif;
            --mono: "Space Grotesk", Arial, sans-serif;
        }

        * { box-sizing: border-box; }

        html { -webkit-text-size-adjust: 100%; }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: var(--sans);
            font-size: 18px;
            line-height: 1.55;
        }

        a { color: var(--violet); }

        :focus-visible {
            outline: 3px solid var(--violet);
            outline-offset: 2px;
            border-radius: 4px;
        }

        .shell {
            max-width: 1120px;
            margin: 0 auto;
            padding: 0 24px 72px;
        }

        /* Header */

        .masthead {
            border-bottom: 1px solid var(--border);
            background: var(--surface);
        }

        .masthead-inner {
            max-width: 1120px;
            margin: 0 auto;
            padding: 18px 24px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 16px 24px;
        }

        .env-pill {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 18px;
            border: 2px solid transparent;
        }

        .env-pill--main {
            background: var(--violet);
            color: #FFFFFF;
        }

        .env-pill--preview {
            background: transparent;
            color: var(--text);
            border-color: var(--border);
        }

        .env-pill span {
            font-size: 14px;
            font-weight: 500;
            opacity: 0.8;
            text-transform: none;
        }

        .host {
            font-family: var(--mono);
            font-size: 14px;
            color: var(--muted);
        }

        .masthead nav {
            margin-left: auto;
            display: flex;
            gap: 8px;
        }

        .masthead nav a {
            padding: 8px 16px;
            border-radius: var(--radius);
            text-decoration: none;
            color: var(--text);
            font-weight: 500;
        }

        .masthead nav a:hover { background: var(--bg); }

        .masthead nav a[aria-current="page"] {
            background: var(--text);
            color: #FFFFFF;
        }

        /* Page furniture */

        h1 {
            font-size: 34px;
            line-height: 1.2;
            margin: 40px 0 8px;
            letter-spacing: -0.01em;
        }

        .lede {
            color: var(--muted);
            margin: 0 0 32px;
            max-width: 62ch;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
        }

        .flash {
            background: var(--surface);
            border: 1px solid var(--border);
            border-left: 4px solid var(--violet);
            border-radius: var(--radius);
            padding: 14px 20px;
            margin-bottom: 24px;
        }

        .notice {
            background: var(--surface);
            border: 1px solid var(--border);
            border-left: 4px solid var(--text);
            border-radius: var(--radius);
            padding: 16px 20px;
            margin-bottom: 24px;
        }

        .mono { font-family: var(--mono); }

        .muted { color: var(--muted); }

        button, .button {
            font: inherit;
            font-weight: 600;
            padding: 12px 22px;
            border-radius: var(--radius);
            border: 1px solid var(--violet);
            background: var(--violet);
            color: #FFFFFF;
            cursor: pointer;
        }

        button:hover { filter: brightness(1.08); }

        button.secondary {
            background: transparent;
            color: var(--text);
            border-color: var(--border);
        }

        button.secondary:hover { background: var(--bg); filter: none; }

        @media (max-width: 640px) {
            body { font-size: 18px; }
            h1 { font-size: 28px; }
            .masthead nav { margin-left: 0; width: 100%; }
        }
    </style>
    @stack('styles')
</head>
<body>
    <header class="masthead">
        <div class="masthead-inner">
            <span class="env-pill {{ $isMain ? 'env-pill--main' : 'env-pill--preview' }}">
                {{ $envName }}
                <span>{{ $isMain ? 'production' : 'preview' }}</span>
            </span>
            <span class="host" title="The instance serving this page">{{ $hostname }}</span>
            <nav>
                <a href="{{ route('files.index') }}" @if(request()->routeIs('files.*')) aria-current="page" @endif>Files</a>
                <a href="{{ route('import.index') }}" @if(request()->routeIs('import.*')) aria-current="page" @endif>Import</a>
            </nav>
        </div>
    </header>

    <main class="shell">
        @if (session('status'))
            <div class="flash" role="status">{{ session('status') }}</div>
        @endif

        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
