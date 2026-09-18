{{-- Simple page header for CMS, account and utility pages --}}
@props(['title', 'text' => null, 'breadcrumbs' => []])
<div class="bg-white border-b border-line">
    <div class="g-container py-3 lg:py-7">
        <x-breadcrumbs :items="$breadcrumbs" />
        <h1 class="text-xl font-extrabold lg:mt-2 lg:text-3xl">{{ $title }}</h1>
        @if($text)<p class="mt-1 max-w-2xl text-sm text-slate lg:text-base">{{ $text }}</p>@endif
    </div>
</div>
