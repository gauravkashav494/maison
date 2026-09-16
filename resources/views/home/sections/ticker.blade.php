@php $items = $home['ticker_items'] ?? []; @endphp
@if(count($items))
<div class="overflow-hidden border-b border-ink/10 py-4" aria-hidden="true">
    <div class="flex w-max animate-marquee whitespace-nowrap">
        @for($k = 0; $k < 2; $k++)
            <div class="flex">
                @foreach(array_merge($items, $items) as $item)
                    <span class="flex items-center">
                        <span class="px-6 font-serif text-2xl italic text-ink/80 lg:text-3xl">{{ $item }}</span>
                        <span class="h-1 w-1 rounded-full bg-gold"></span>
                    </span>
                @endforeach
            </div>
        @endfor
    </div>
</div>
@endif
