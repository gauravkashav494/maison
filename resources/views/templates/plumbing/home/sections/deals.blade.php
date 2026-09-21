{{-- Today's deals: deep-blue band with a countdown, product rail with orange discount badges --}}
@if($deals->isNotEmpty())
<section class="section">
    <div class="p-container">
        <div class="overflow-hidden rounded-2xl bg-deep text-white">
            <div class="flex flex-col gap-4 px-5 pt-5 sm:flex-row sm:items-center sm:justify-between lg:px-8 lg:pt-7">
                <div>
                    <p class="inline-flex items-center gap-1.5 rounded-md bg-accent px-2 py-0.5 text-[0.6875rem] font-extrabold uppercase tracking-wider text-ink"><x-ico name="bolt" :size="12" :stroke="2.5" /> Limited time</p>
                    <h2 class="mt-2 font-display text-xl font-bold lg:text-2xl">{{ $g['deals_heading'] ?? 'Today’s plumbing deals' }}</h2>
                    @if(!empty($g['deals_text']))<p class="mt-1 text-sm text-white/70">{{ $g['deals_text'] }}</p>@endif
                </div>
                <div class="flex items-center gap-4">
                    <div x-data="countdown()" class="flex items-center gap-1.5 text-center" aria-label="Time left today">
                        <span class="text-xs text-white/70">Ends in</span>
                        @foreach(['h', 'm', 's'] as $u)<span class="grid h-10 w-11 place-items-center rounded-lg bg-white/10 font-display text-lg font-bold tabular" x-text="{{ $u }}"></span>@if(!$loop->last)<span class="text-white/50">:</span>@endif @endforeach
                    </div>
                    <a href="{{ route('shop.category', 'sale') }}" class="btn btn-accent hidden sm:inline-flex">Shop all deals <x-ico name="arrow-right" :size="16" /></a>
                </div>
            </div>
            <div class="px-5 pb-5 pt-4 lg:px-8 lg:pb-7">
                <div x-data="rail()" class="relative">
                    <div x-ref="track" class="rail no-scrollbar">
                        @foreach($deals as $product)
                            <x-product-card :product="$product" :compact="true" />
                        @endforeach
                    </div>
                    <button type="button" @click="scroll(-1)" :disabled="!canPrev" class="rail-btn absolute -left-4 top-[38%] hidden lg:grid" aria-label="Scroll left"><x-ico name="chevron-left" :size="18" /></button>
                    <button type="button" @click="scroll(1)" :disabled="!canNext" class="rail-btn absolute -right-4 top-[38%] hidden lg:grid" aria-label="Scroll right"><x-ico name="chevron-right" :size="18" /></button>
                </div>
                <a href="{{ route('shop.category', 'sale') }}" class="btn btn-accent btn-block mt-4 sm:hidden">Shop all deals</a>
            </div>
        </div>
    </div>
</section>
@endif
