@props(['items' => []])
<nav aria-label="Breadcrumb" {{ $attributes->class('no-scrollbar hidden items-center lg:flex gap-1.5 overflow-x-auto whitespace-nowrap text-xs text-muted') }}>
    <a href="{{ route('home') }}" class="hover:text-red">Home</a>
    @foreach($items as $label => $url)<span class="text-gold">/</span>@if($url)<a href="{{ $url }}" class="hover:text-red">{{ $label }}</a>@else<span class="font-medium text-ink">{{ $label }}</span>@endif @endforeach
</nav>
