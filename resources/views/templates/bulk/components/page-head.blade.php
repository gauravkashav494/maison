{{--
    A page's breadcrumb, title and intro line, in the store's plain style. $crumbs maps label →
    URL; the last one (URL null) is the current page.
--}}
@props(['title', 'text' => null, 'crumbs' => []])

<div {{ $attributes }}>
    @if($crumbs)
        <nav class="bk-crumbs mb-2" aria-label="Breadcrumb">
            <a href="{{ url('/') }}">Home</a>
            @foreach($crumbs as $label => $url)
                <span>/</span>
                @if($url)<a href="{{ $url }}">{{ $label }}</a>@else<span class="text-[var(--bk-ink)]">{{ $label }}</span>@endif
            @endforeach
        </nav>
    @endif
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $title }}</h1>
            @if($text)<p class="bk-muted mt-1 max-w-2xl">{{ $text }}</p>@endif
        </div>
        {{ $slot }}
    </div>
</div>
