@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex items-center justify-center gap-1.5">
        @if ($paginator->onFirstPage())
            <span class="icon-btn text-mist" aria-disabled="true"><x-ico name="chevron-left" :size="18" /></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="icon-btn ring-1 ring-line" aria-label="Previous"><x-ico name="chevron-left" :size="18" /></a>
        @endif
        @foreach ($elements as $element)
            @if (is_string($element))<span class="px-1 text-mist">{{ $element }}</span>@endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="grid h-10 w-10 place-items-center rounded-full bg-primary text-sm font-bold text-white" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="grid h-10 w-10 place-items-center rounded-full text-sm font-semibold ring-1 ring-line hover:bg-sky">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="icon-btn ring-1 ring-line" aria-label="Next"><x-ico name="chevron-right" :size="18" /></a>
        @else
            <span class="icon-btn text-mist" aria-disabled="true"><x-ico name="chevron-right" :size="18" /></span>
        @endif
    </nav>
@endif