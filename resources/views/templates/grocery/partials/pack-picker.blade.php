{{-- Pack-size chooser for products with several sizes (bottom sheet) --}}
<div x-data="packPicker()" x-show="$store.ui.packPicker" x-cloak>
    <div class="overlay" x-show="$store.ui.packPicker" x-transition.opacity @click="$store.ui.closeAll()"></div>
    <div class="sheet sheet-bottom mx-auto sm:inset-x-0 sm:bottom-auto sm:top-24 sm:w-[26rem] sm:rounded-2xl" x-show="$store.ui.packPicker" x-transition x-trap.noscroll="!!$store.ui.packPicker">
        <div class="sheet-handle sm:hidden"></div>
        <template x-if="p">
            <div class="p-5">
                <div class="flex items-start gap-3">
                    <img :src="p.images[0]" alt="" class="h-16 w-16 rounded-lg border border-line bg-paper object-cover">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold text-slate" x-text="p.brand"></p>
                        <p class="text-sm font-bold leading-snug" x-text="p.name"></p>
                    </div>
                    <button type="button" @click="$store.ui.closeAll()" class="grid h-9 w-9 place-items-center rounded-lg hover:bg-paper" aria-label="Close"><x-ico name="close" :size="20" /></button>
                </div>
                <p class="mt-4 text-xs font-bold uppercase tracking-wider text-mist">Choose pack size</p>
                <ul class="mt-2 divide-y divide-line rounded-xl border border-line">
                    <template x-for="s in p.sizes" :key="s">
                        <li class="flex items-center justify-between gap-3 px-4 py-3">
                            <div><p class="text-sm font-bold" x-text="s"></p><p class="text-xs text-slate" x-text="p.price_formatted"></p></div>
                            <template x-if="!qtyOf(s)"><button type="button" @click="inc(s)" class="btn btn-outline btn-sm w-20">ADD</button></template>
                            <template x-if="qtyOf(s)"><div class="stepper"><button type="button" @click="dec(s)" aria-label="Decrease"><x-ico name="minus" :size="14" /></button><span x-text="qtyOf(s)"></span><button type="button" @click="inc(s)" aria-label="Increase"><x-ico name="plus" :size="14" /></button></div></template>
                        </li>
                    </template>
                </ul>
                <button type="button" @click="$store.ui.closeAll()" class="btn btn-primary btn-block mt-4">Done</button>
            </div>
        </template>
    </div>
</div>
