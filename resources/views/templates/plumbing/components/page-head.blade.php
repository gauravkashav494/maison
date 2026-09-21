{{-- Page header for CMS, account and utility pages: breadcrumbs + title on a light blue band --}}
@props(['title', 'text' => null, 'breadcrumbs' => [], 'eyebrow' => null])
<div class="border-b border-line bg-sky/60">
    <div class="p-container py-4 lg:py-8">
        <x-breadcrumbs :items="$breadcrumbs" />
        @if($eyebrow)<p class="mt-2 text-xs font-bold uppercase tracking-wider text-primary">{{ $eyebrow }}</p>@endif
        <h1 class="mt-1 font-display text-xl font-bold lg:text-3xl">{{ $title }}</h1>
        @if($text)<p class="mt-1 max-w-2xl text-sm text-slate lg:text-base">{{ $text }}</p>@endif
    </div>
</div>
