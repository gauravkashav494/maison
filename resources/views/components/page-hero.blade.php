@props(['eyebrow' => null, 'title', 'description' => null, 'image' => null, 'breadcrumbs' => [], 'position' => '50% 40%'])
@php
    // Pages without their own header image fall back to the site-wide one (Site settings → Brand).
    $image = $image ?: \App\Support\Media::url(setting('site.page_header_image'));
@endphp
<section class="relative {{ $image ? 'bg-ink text-ivory' : 'border-b border-ink/10 bg-cream' }}">
    @if($image)
        <img src="{{ $image }}" alt="" class="img-cover absolute inset-0" style="object-position: {{ $position }}">
        <div class="absolute inset-0 bg-ink/55"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-ink/85 via-ink/45 to-ink/10"></div>
    @endif
    <div class="container-luxe relative pb-16 pt-32 lg:pb-20 lg:pt-40">
        @if($breadcrumbs)
            <nav aria-label="Breadcrumb" class="mb-5 flex flex-wrap items-center gap-2 text-[0.625rem] uppercase tracking-[0.2em] {{ $image ? 'text-ivory/60' : 'text-taupe' }}">
                <a href="{{ route('home') }}" class="hover:underline">Home</a>
                @foreach($breadcrumbs as $label => $url)
                    <span aria-hidden="true">/</span>
                    @if($url)<a href="{{ $url }}" class="hover:underline">{{ $label }}</a>@else<span class="{{ $image ? 'text-ivory' : 'text-ink' }}">{{ $label }}</span>@endif
                @endforeach
            </nav>
        @endif
        @if($eyebrow)<p class="eyebrow {{ $image ? 'text-ivory/70' : 'text-taupe' }}">{{ $eyebrow }}</p>@endif
        <h1 class="display-md mt-3 max-w-4xl text-balance">{{ $title }}</h1>
        @if($description)<p class="mt-4 max-w-xl text-[0.9375rem] leading-relaxed {{ $image ? 'text-ivory/75' : 'text-smoke' }}">{{ $description }}</p>@endif
        {{ $slot }}
    </div>
</section>
