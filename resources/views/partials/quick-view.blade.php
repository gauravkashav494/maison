<div x-data="quickView" x-cloak x-show="$store.ui.quickView" class="fixed inset-0 z-[80] flex items-end justify-center sm:items-center sm:p-6"
     x-transition:enter="transition duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     role="dialog" aria-modal="true" aria-label="Quick view">
    <button type="button" aria-label="Close" class="absolute inset-0 bg-ink/50 backdrop-blur-[2px]" @click="$store.ui.quickView = null"></button>
    <template x-if="$store.ui.quickView">
        <div x-trap.noscroll="$store.ui.quickView" class="relative flex max-h-[92dvh] w-full max-w-5xl flex-col overflow-hidden bg-ivory text-ink sm:flex-row"
             x-transition:enter="transition duration-500 ease-[var(--ease-luxe)]" x-transition:enter-start="translate-y-10 opacity-0" x-transition:enter-end="translate-y-0 opacity-100">
            <button type="button" @click="$store.ui.quickView = null" aria-label="Close quick view" class="absolute right-4 top-4 z-10 grid h-10 w-10 place-items-center bg-ivory/80 backdrop-blur transition-colors hover:bg-ink hover:text-ivory"><x-ico name="close" :size="18" :stroke="1.5" /></button>

            {{-- Gallery --}}
            <div class="relative aspect-[4/5] w-full shrink-0 bg-sand sm:aspect-auto sm:w-1/2">
                <img :src="$store.ui.quickView.images[image]" :alt="$store.ui.quickView.name" class="img-cover absolute inset-0">
                <div class="absolute bottom-4 left-4 flex gap-2" x-show="$store.ui.quickView.images.length > 1">
                    <template x-for="(src, i) in $store.ui.quickView.images" :key="src">
                        <button type="button" @click="image = i" :aria-label="`Image ${i + 1}`" class="relative h-14 w-11 overflow-hidden border transition-colors" :class="i === image ? 'border-ink' : 'border-transparent opacity-70'"><img :src="src" alt="" class="img-cover"></button>
                    </template>
                </div>
            </div>

            {{-- Details --}}
            <div class="flex min-h-0 flex-1 flex-col overflow-y-auto p-6 sm:p-10">
                <p class="eyebrow text-taupe" x-text="$store.ui.quickView.category"></p>
                <h2 class="mt-2 font-serif text-3xl leading-tight" x-text="$store.ui.quickView.name"></h2>
                <div class="mt-4 flex items-baseline gap-3 text-lg tabular-nums">
                    <span :class="$store.ui.quickView.compare_at_price && 'text-rouge'" x-text="$store.ui.quickView.price_formatted"></span>
                    <span x-show="$store.ui.quickView.compare_at_price" class="text-sm text-smoke line-through" x-text="$store.ui.quickView.compare_at_price_formatted"></span>
                </div>
                <p class="mt-5 text-[0.9375rem] leading-relaxed text-smoke" x-text="$store.ui.quickView.description"></p>

                <div class="mt-7" x-show="$store.ui.quickView.colors.length">
                    <p class="mb-3 text-[0.6875rem] uppercase tracking-[0.2em]">Colour <span class="ml-2 text-smoke normal-case tracking-normal" x-text="color"></span></p>
                    <div class="flex gap-3">
                        <template x-for="c in $store.ui.quickView.colors" :key="c.name">
                            <button type="button" @click="color = c.name" :aria-label="c.name" :aria-pressed="color === c.name"
                                    class="h-7 w-7 rounded-full ring-1 ring-offset-2 ring-offset-ivory transition-all" :class="color === c.name ? 'ring-ink' : 'ring-ink/15 hover:ring-ink/40'" :style="`background-color:${c.hex}`"></button>
                        </template>
                    </div>
                </div>

                <div class="mt-7">
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-[0.6875rem] uppercase tracking-[0.2em]">Select size</p>
                        <a href="/size-guide" class="link-underline text-[0.6875rem] text-smoke">Size guide</a>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="s in $store.ui.quickView.sizes" :key="s">
                            <button type="button" @click="size = s" :aria-pressed="size === s" class="min-w-12 border px-3 py-2.5 text-[0.75rem] uppercase tracking-wider transition-colors" :class="size === s ? 'border-ink bg-ink text-ivory' : 'border-ink/20 hover:border-ink'" x-text="s"></button>
                        </template>
                    </div>
                </div>

                <div class="mt-8 flex gap-3">
                    <div class="flex h-12 items-center border border-ink/20">
                        <button type="button" @click="qty = Math.max(1, qty - 1)" aria-label="Decrease quantity" class="grid h-full w-11 place-items-center hover:bg-sand"><x-ico name="minus" :size="14" :stroke="1.5" /></button>
                        <span class="w-8 text-center text-sm tabular-nums" x-text="qty"></span>
                        <button type="button" @click="qty++" aria-label="Increase quantity" class="grid h-full w-11 place-items-center hover:bg-sand"><x-ico name="plus" :size="14" :stroke="1.5" /></button>
                    </div>
                    <button type="button" @click="add()" :disabled="!size || $store.cart.busy" class="btn btn-primary flex-1" x-text="size ? 'Add to Bag' : 'Select a size'"></button>
                    <button type="button" @click="$store.wishlist.toggle($store.ui.quickView.id)" aria-label="Add to wishlist" :aria-pressed="$store.wishlist.has($store.ui.quickView.id)" class="grid h-12 w-12 shrink-0 place-items-center border border-ink/20 transition-colors hover:border-ink">
                        <x-ico name="heart" :size="16" :stroke="1.5" ::class="$store.wishlist.has($store.ui.quickView.id) && 'fill-ink'" />
                    </button>
                </div>

                <a :href="$store.ui.quickView.url" class="link-underline eyebrow mt-6 self-start text-ink">View full details →</a>
            </div>
        </div>
    </template>
</div>
