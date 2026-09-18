{{-- Centred-title product carousel with arrow buttons and a "View All" link. Expects $title, $items, $more. --}}
@if($items->isNotEmpty())
<section class="h-container pt-10 lg:pt-[60px]">
    <h2 class="title-c">{{ $title }}</h2>
    <div class="mt-6 lg:mt-[30px]">
        <x-rail :products="$items" />
    </div>
    @if($more)<p class="text-center"><a href="{{ $more }}" class="view-all">View All</a></p>@endif
</section>
@endif
