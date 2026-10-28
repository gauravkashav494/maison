{{-- Size / variant chooser for products with several sizes (bottom sheet on phones, dialog on desktop) --}}
<div x-data="sizePicker()" x-show="$store.ui.sizePicker" x-cloak>
    <div class="overlay" x-show="$store.ui.sizePicker" x-transition.opacity @click="$store.ui.closeAll()"></div>
    <div class="sheet inset-x-0 bottom-0 rounded-t-2xl sm:bottom-auto sm:top-24 sm:mx-auto sm:w-[26rem] sm:rounded-2xl" x-show="$store.ui.sizePicker" x-transition x-trap.noscroll="!!$store.ui.sizePicker">
        <div class="mx-auto mt-2 h-1 w-10 rounded-full bg-line sm:hidden"></div>
        <template x-if="p">
            <div class="p-5">
                <div class="flex items-start gap-3">
                    <img :src="p.images[0]" alt="" class="h-16 w-16 rounded-lg border border-line-soft bg-canvas object-cover">
                    <div class="min-w-0 flex-1">
                        <p class="pcard-brand" x-text="p.brand"></p>
                        <p class="text-sm font-bold leading-snug" x-text="p.name"></p>
                    </div>
                    <button type="button" @click="$store.ui.closeAll()" class="icon-btn" aria-label="Close"><x-ico name="close" :size="20" /></button>
                </div>
                <p class="mt-4 text-xs font-bold uppercase tracking-wider text-mist">Choose size</p>
                <ul class="mt-2 divide-y divide-line-soft rounded-xl border border-line">
                    <template x-for="s in p.sizes" :key="s">
                        <li class="flex items-center justify-between gap-3 px-4 py-3">
                            <div><p class="text-sm font-bold" x-text="s"></p><p class="price text-xs" x-text="p.price_formatted"></p></div>
                            <template x-if="!qtyOf(s)"><button type="button" @click="inc(s)" class="btn btn-outline btn-sm w-20">Add</button></template>
                            <template x-if="qtyOf(s)"><div class="stepper"><button type="button" @click="dec(s)" aria-label="Decrease"><x-ico name="minus" :size="14" /></button><span x-text="qtyOf(s)"></span><button type="button" @click="inc(s)" aria-label="Increase"><x-ico name="plus" :size="14" /></button></div></template>
                        </li>
                    </template>
                </ul>
                <button type="button" @click="$store.ui.closeAll()" class="btn btn-primary btn-block mt-4">Done</button>
            </div>
        </template>
    </div>
</div>
