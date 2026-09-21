{{-- Page header for content, account and utility pages: breadcrumbs + title on a light blue band --}}
@props(['title', 'text' => null, 'breadcrumbs' => [], 'eyebrow' => null])
<div class="border-b border-line bg-sky/60">
    <div class="ps-container py-4 lg:py-10">
        <x-breadcrumbs :items="$breadcrumbs" />
        @if($eyebrow)<p class="eyebrow mt-2">{{ $eyebrow }}</p>@endif
        <h1 class="mt-1 font-display text-2xl font-extrabold lg:text-4xl">{{ $title }}</h1>
        @if($text)<p class="mt-2 max-w-2xl text-sm text-slate lg:text-base">{{ $text }}</p>@endif
        @if(trim($slot))<div class="mt-4">{{ $slot }}</div>@endif
    </div>
</div>