@props(['eyebrow' => null, 'title', 'description' => null, 'image' => null, 'breadcrumbs' => [], 'position' => '50% 40%'])
@php
    // Pages without their own header image fall back to the site-wide one (Site settings → Brand).
    $image = $image ?: \App\Support\Media::url(setting('site.page_header_image'));
@endphp
{{-- On phones the hero collapses to a compact app-style title (no image, no breadcrumbs); the desktop banner is unchanged. --}}
<section class="relative {{ $image ? 'border-b border-ink/10 bg-cream text-ink lg:border-0 lg:bg-ink lg:text-ivory' : 'border-b border-ink/10 bg-cream' }}">
    @if($image)
        <div class="absolute inset-0 hidden lg:block">
            <img src="{{ $image }}" alt="" class="img-cover absolute inset-0" style="object-position: {{ $position }}">
            <div class="absolute inset-0 bg-ink/55"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-ink/85 via-ink/45 to-ink/10"></div>
        </div>
    @endif
    <div class="container-luxe relative pb-6 pt-[4.5rem] lg:pb-20 lg:pt-40">
        @if($breadcrumbs)
            <nav aria-label="Breadcrumb" class="mb-5 hidden flex-wrap items-center gap-2 lg:flex text-[0.625rem] uppercase tracking-[0.2em] {{ $image ? 'text-ivory/60' : 'text-taupe' }}">
                <a href="{{ route('home') }}" class="hover:underline">Home</a>
                @foreach($breadcrumbs as $label => $url)
                    <span aria-hidden="true">/</span>
                    @if($url)<a href="{{ $url }}" class="hover:underline">{{ $label }}</a>@else<span class="{{ $image ? 'text-ivory' : 'text-ink' }}">{{ $label }}</span>@endif
                @endforeach
            </nav>
        @endif
        @if($eyebrow)<p class="eyebrow {{ $image ? 'text-taupe lg:text-ivory/70' : 'text-taupe' }}">{{ $eyebrow }}</p>@endif
        <h1 class="display-md mt-2 max-w-4xl text-balance max-lg:text-[1.75rem] lg:mt-3">{{ $title }}</h1>
        @if($description)<p class="mt-2 max-w-xl text-sm leading-relaxed lg:mt-4 lg:text-[0.9375rem] {{ $image ? 'text-smoke lg:text-ivory/75' : 'text-smoke' }}">{{ $description }}</p>@endif
        {{ $slot }}
    </div>
</section>
