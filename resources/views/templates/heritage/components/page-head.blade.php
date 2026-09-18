@props(['title', 'text' => null, 'eyebrow' => null, 'breadcrumbs' => [], 'image' => null])
<div class="relative overflow-hidden border-b border-line bg-warm">
    @if($image)<img src="{{ $image }}" alt="" class="absolute inset-0 hidden h-full w-full object-cover opacity-20 lg:block">@endif
    <div class="h-container relative py-4 lg:py-12">
        <x-breadcrumbs :items="$breadcrumbs" />
        @if($eyebrow)<p class="eyebrow mt-4">{{ $eyebrow }}</p>@endif
        <h1 class="display mt-1 text-2xl text-maroon lg:mt-2 lg:text-[2.75rem]">{{ $title }}</h1>
        @if($text)<p class="lead mt-3 max-w-2xl">{{ $text }}</p>@endif
    </div>
</div>
