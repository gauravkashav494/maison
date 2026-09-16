@php
    $groups = $item->children->groupBy(fn ($c) => $c->group ?: 'Links');
    $tiles = $groups->pull('Tiles', collect());
    $columns = $groups; // remaining named columns
    $tileCount = $tiles->count();
    $colSpan = $tileCount > 0 ? max(2, (int) floor((12 - $tileCount * 3) / max(1, $columns->count()))) : (int) floor(12 / max(1, $columns->count()));
    $tileSpan = $tileCount > 0 ? (int) floor((12 - $colSpan * $columns->count()) / $tileCount) : 0;
@endphp
<div class="container-luxe py-10">
    <div class="grid grid-cols-12 gap-10">
        @foreach($columns as $title => $links)
            <div class="col-span-{{ $colSpan }}">
                <p class="eyebrow mb-5 text-taupe">{{ $title }}</p>
                <ul class="space-y-3">
                    @foreach($links as $link)
                        <li>
                            <a href="{{ $link->href }}" @if($link->opens_in_new_tab) target="_blank" rel="noreferrer" @endif
                               class="group/item flex items-baseline gap-2 text-[0.9375rem] transition-colors {{ $link->is_accent ? 'text-rouge' : 'text-ink hover:text-smoke' }}">
                                <span class="link-underline">{{ $link->label }}</span>
                                @if($link->badge)<span class="text-[0.625rem] tabular-nums text-taupe">{{ $link->badge }}</span>@endif
                            </a>
                        </li>
                    @endforeach
                </ul>
                @if($loop->last)
                    <a href="{{ $item->href }}" class="mt-8 inline-block border-b border-ink pb-0.5 text-[0.6875rem] uppercase tracking-[0.2em]">All {{ strtolower($item->label) }}</a>
                @endif
            </div>
        @endforeach

        @foreach($tiles as $tile)
            <div class="col-span-{{ $tileSpan }}">
                <a href="{{ $tile->href }}" class="group relative block overflow-hidden bg-sand {{ $tileCount >= 3 ? 'aspect-[4/3]' : 'aspect-[3/4]' }}">
                    @if($tile->image_url)<img src="{{ $tile->image_url }}" alt="{{ $tile->label }}" loading="lazy" class="img-cover img-zoom">@endif
                    <div class="absolute inset-0 bg-gradient-to-t from-ink/60 via-ink/5 to-transparent"></div>
                    <div class="absolute bottom-4 left-4 right-4 text-ivory">
                        @if($tile->eyebrow)<p class="eyebrow text-[0.5625rem] text-ivory/70">{{ $tile->eyebrow }}</p>@endif
                        <p class="mt-1 font-serif text-xl leading-tight">{{ $tile->label }}</p>
                        <span class="link-underline mt-2 inline-block text-[0.625rem] uppercase tracking-[0.2em]">Discover</span>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
</div>
