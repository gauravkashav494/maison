<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $client->name }} is unavailable</title>
    <style>
        :root { color-scheme: light dark; --bg: #f6f3ee; --card: #ffffff; --ink: #1c2433; --muted: #6b7280; --line: #e5e1d8; --accent: #b45309; }
        @media (prefers-color-scheme: dark) { :root { --bg: #111827; --card: #1b2333; --ink: #f3f4f6; --muted: #9ca3af; --line: #2d3748; --accent: #f59e0b; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px; background: var(--bg); color: var(--ink); font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .card { width: 100%; max-width: 440px; padding: 40px 32px; background: var(--card); border: 1px solid var(--line); border-radius: 16px; text-align: center; }
        .icon { width: 48px; height: 48px; margin: 0 auto 20px; color: var(--accent); }
        .store { margin: 0 0 6px; font-size: 13px; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); }
        h1 { margin: 0 0 12px; font-size: 22px; font-weight: 600; }
        p { margin: 0; line-height: 1.6; color: var(--muted); }
    </style>
</head>
<body>
    <main class="card">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
        </svg>
        <p class="store">{{ $client->name }}</p>
        <h1>This store has been deactivated</h1>
        <p>It is not open right now. Please check back later.</p>
    </main>
</body>
</html>
