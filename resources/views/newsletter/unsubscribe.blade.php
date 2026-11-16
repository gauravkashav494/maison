{{--
    Unsubscribe confirm page. Self-contained on purpose: every template overrides every storefront
    view, and this page must look right on all of them without six copies. It carries the store's
    name, not its theme, and is never indexed.
--}}
@php $storeName = setting('site.name', config('app.name')); @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $done ? 'You’re unsubscribed' : 'Unsubscribe' }} — {{ $storeName }}</title>
    <style>
        :root { color-scheme: light dark; --bg: #f6f5f2; --card: #fff; --ink: #1b1f1c; --muted: #5b625d; --line: #e4e2dc; }
        @media (prefers-color-scheme: dark) { :root { --bg: #141614; --card: #1d201e; --ink: #eef0ed; --muted: #a3aaa5; --line: #2e322f; } }
        * { box-sizing: border-box; }
        html, body { min-height: 100%; margin: 0; }
        body { display: grid; place-items: center; padding: 24px 16px; background: var(--bg); color: var(--ink); font: 16px/1.55 system-ui, -apple-system, 'Segoe UI', sans-serif; }
        main { width: 100%; max-width: 26rem; padding: 32px 28px; border: 1px solid var(--line); border-radius: 16px; background: var(--card); text-align: center; }
        .store { margin: 0 0 20px; font-size: .75rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--muted); }
        h1 { margin: 0 0 8px; font-size: 1.375rem; line-height: 1.3; }
        p { margin: 0; color: var(--muted); }
        strong { color: var(--ink); word-break: break-all; }
        button { width: 100%; margin-top: 24px; height: 48px; border: 0; border-radius: 999px; background: var(--ink); color: var(--card); font: inherit; font-weight: 600; cursor: pointer; }
        button:focus-visible, a:focus-visible { outline: 2px solid var(--ink); outline-offset: 3px; }
        a { display: inline-block; margin-top: 18px; color: var(--muted); font-size: .875rem; }
    </style>
</head>
<body>
    <main>
        <p class="store">{{ $storeName }}</p>
        @if(! $email)
            <h1>This link has expired</h1>
            <p>We couldn’t find a subscription for this link. If you still get our emails, use the unsubscribe link in the most recent one.</p>
        @elseif($done)
            <h1>You’re unsubscribed</h1>
            <p><strong>{{ $email }}</strong> won’t get any more newsletter emails from us. Changed your mind? Sign up again any time from our website.</p>
        @else
            <h1>Unsubscribe from our newsletter?</h1>
            <p><strong>{{ $email }}</strong> will stop getting offers and updates from {{ $storeName }}.</p>
            <form method="POST" action="{{ route('newsletter.unsubscribe.confirm', $token) }}">
                @csrf
                <button type="submit">Unsubscribe</button>
            </form>
        @endif
        <a href="{{ url('/') }}">Back to {{ $storeName }}</a>
    </main>
</body>
</html>
