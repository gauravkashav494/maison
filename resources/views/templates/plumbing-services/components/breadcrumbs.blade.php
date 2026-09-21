@props(['items' => []])
<nav aria-label="Breadcrumb" {{ $attributes->class('no-scrollbar hidden items-center gap-1.5 overflow-x-auto whitespace-nowrap text-xs text-slate lg:flex') }}>
    <a href="{{ route('home') }}" class="hover:text-primary">Home</a>
    @foreach($items as $label => $url)
        <x-ico name="chevron-right" :size="12" class="text-mist" />
        @if($url)<a href="{{ $url }}" class="hover:text-primary">{{ $label }}</a>@else<span class="font-semibold text-ink">{{ $label }}</span>@endif
    @endforeach
</nav>