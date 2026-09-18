{{-- All categories — bottom sheet on mobile, side sheet on larger screens --}}
@php $cats = $navCategories ?? collect(); @endphp
<div x-data x-show="$store.ui.categoriesOpen" x-cloak>
    <div class="overlay" x-show="$store.ui.categoriesOpen" x-transition.opacity @click="$store.ui.closeAll()"></div>
    <div class="sheet sheet-bottom sm:sheet-right sm:rounded-none" x-show="$store.ui.categoriesOpen" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-full sm:translate-y-0 sm:translate-x-full" x-transition:enter-end="translate-y-0 sm:translate-x-0" x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-y-0 sm:translate-x-0" x-transition:leave-end="translate-y-full sm:translate-y-0 sm:translate-x-full" x-trap.noscroll="$store.ui.categoriesOpen" x-data="accordion()">
        <div class="sheet-handle sm:hidden"></div>
        <div class="flex items-center justify-between px-5 py-4">
            <h2 class="text-lg font-extrabold">All categories</h2>
            <button type="button" @click="$store.ui.closeAll()" class="grid h-9 w-9 place-items-center rounded-lg hover:bg-paper" aria-label="Close"><x-ico name="close" :size="20" /></button>
        </div>
        <div class="flex-1 overflow-y-auto px-3 pb-6">
            <ul class="divide-y divide-line">
                @foreach($cats as $c)
                    <li>
                        <div class="flex items-center">
                            <a href="{{ $c->url }}" class="flex flex-1 items-center gap-3 py-3 pl-2">
                                <span class="h-11 w-11 shrink-0 overflow-hidden rounded-xl bg-leaf-light">@if($c->image_url)<img src="{{ $c->image_url }}" alt="" class="img-cover" loading="lazy">@endif</span>
                                <span class="min-w-0"><span class="block truncate text-sm font-bold">{{ $c->name }}</span><span class="block truncate text-xs text-slate">{{ $c->tagline }}</span></span>
                            </a>
                            @if($c->children->isNotEmpty())
                                <button type="button" @click="toggle({{ $c->id }})" class="grid h-11 w-11 place-items-center text-slate" :aria-expanded="open === {{ $c->id }}" aria-label="Show sub-categories"><x-ico name="chevron-down" :size="18" ::class="open === {{ $c->id }} && 'rotate-180'" class="transition-transform" /></button>
                            @endif
                        </div>
                        @if($c->children->isNotEmpty())
                            <ul x-show="open === {{ $c->id }}" x-collapse x-cloak class="mb-2 ml-14 space-y-1 pb-2">
                                @foreach($c->children as $sub)<li><a href="{{ $sub->url }}" class="block rounded-lg px-2 py-1.5 text-sm text-slate hover:bg-paper hover:text-leaf">{{ $sub->name }}</a></li>@endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
