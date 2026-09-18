@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex items-center justify-center gap-1">
        @if ($paginator->onFirstPage())
            <span class="btn btn-ghost btn-sm opacity-50" aria-disabled="true"><x-ico name="chevron-left" :size="16" /> Prev</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn btn-ghost btn-sm"><x-ico name="chevron-left" :size="16" /> Prev</a>
        @endif
        <div class="hidden items-center gap-1 sm:flex">
            @foreach ($elements as $element)
                @if (is_string($element))<span class="px-2 text-sm text-mist">{{ $element }}</span>@endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="grid h-9 w-9 place-items-center rounded-lg bg-leaf text-sm font-bold text-white" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="grid h-9 w-9 place-items-center rounded-lg text-sm font-semibold hover:bg-white">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
        </div>
        <span class="px-3 text-sm text-slate sm:hidden">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn btn-ghost btn-sm">Next <x-ico name="chevron-right" :size="16" /></a>
        @else
            <span class="btn btn-ghost btn-sm opacity-50" aria-disabled="true">Next <x-ico name="chevron-right" :size="16" /></span>
        @endif
    </nav>
@endif
