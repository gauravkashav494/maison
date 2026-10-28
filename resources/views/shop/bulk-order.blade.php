@extends('layouts.app', ['transparentHeader' => filled(setting('site.page_header_image'))])

{{--
    Bulk order: add many products to the cart in one pass instead of one at a time.
    Behaviour lives in partials/bulk-order-script (shared by every template); lines go
    to the existing cart, so bag, checkout and confirmation are unchanged. Prices are
    never editable here — the shopper always pays the product's current price.
--}}

@section('content')
    <x-page-hero eyebrow="Order in one go" title="Bulk Order" :breadcrumbs="['Bulk Order' => null]" />

    <section class="container-luxe py-8 lg:py-16" x-data="bulkOrder(@js($suggestions))">
        <div class="overflow-hidden border border-ink/10 bg-cream">
            <table class="block w-full border-collapse text-sm sm:table">
                <thead class="hidden sm:table-header-group">
                    <tr class="border-b border-ink/10 text-left text-[0.625rem] uppercase tracking-[0.2em] text-taupe">
                        <th class="px-5 py-4 font-normal">Item</th>
                        <th class="px-5 py-4 w-36 font-normal">Qty</th>
                        <th class="px-5 py-4 w-28 text-right font-normal">Price</th>
                        <th class="px-5 py-4 w-28 text-right font-normal">Amount</th>
                        <th class="w-12 px-2 py-4"></th>
                    </tr>
                </thead>
                <tbody class="block sm:table-row-group">
                    <template x-for="row in rows" :key="row.key">
                        <tr class="border-b border-ink/10 last:border-0 align-top grid grid-cols-[auto_1fr_auto] items-start sm:table-row">
                            <td class="relative col-span-3 px-5 pb-1 pt-4 sm:py-4">
                                <template x-if="!row.product">
                                    <div>
                                        <input type="text" class="input-luxe !py-2" placeholder="Search item or code…"
                                               x-model="row.query" @input="search(row)"
                                               @focus="row.open = true; row.anchorEl = $el.parentElement; row.pos = anchorPos($el)"
                                               autocomplete="off">
                                        <template x-teleport="body">
                                            <div x-show="row.open && listFor(row).length" x-cloak
                                                 @click.outside="if (!(row.anchorEl && row.anchorEl.contains($event.target))) row.open = false"
                                                 @scroll.window="row.open = false" @resize.window="row.open = false"
                                                 :style="`top:${row.pos.top}px; left:${row.pos.left}px; width:${row.pos.width}px;`"
                                                 class="absolute z-50 max-h-[28rem] overflow-y-auto border border-ink/10 bg-ivory shadow-xl">
                                                <p x-show="!row.query.trim()" class="px-4 pt-3 text-[0.5625rem] uppercase tracking-[0.2em] text-taupe">Popular pieces</p>
                                                <template x-for="p in listFor(row)" :key="p.id">
                                                    <button type="button" @click="pick(row, p)"
                                                            class="flex w-full items-center justify-between gap-3 px-4 py-2.5 text-left hover:bg-sand">
                                                        <span>
                                                            <span class="block font-serif text-base leading-tight" x-text="p.name"></span>
                                                            <span class="block text-[0.75rem] text-smoke" x-text="meta(p)"></span>
                                                        </span>
                                                        <span class="whitespace-nowrap tabular-nums" x-text="p.price_formatted"></span>
                                                    </button>
                                                </template>
                                            </div>
                                        </template>
                                        <p x-show="row.searching" x-cloak class="mt-1 text-[0.75rem] text-smoke">Searching…</p>
                                    </div>
                                </template>
                                <template x-if="row.product">
                                    <div class="flex items-start justify-between gap-2">
                                        <span class="min-w-0">
                                            <span class="block font-serif text-lg leading-tight" x-text="row.product.name"></span>
                                            <span class="block text-[0.75rem] text-smoke" x-text="[row.product.sku, row.product.sizes.length === 1 ? row.product.sizes[0] : ''].filter(Boolean).join(' · ')"></span>
                                            @include('partials.bulk-order-variants', ['selectClass' => 'border border-ink/15 bg-transparent px-2 py-1 text-[0.75rem]'])
                                        </span>
                                        <button type="button" class="text-[0.625rem] uppercase tracking-widest text-smoke hover:text-ink" @click="clear(row)">Change</button>
                                    </div>
                                </template>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex h-9 w-max items-center border border-ink/15" :class="{ 'opacity-40 pointer-events-none': !row.product }">
                                    <button type="button" @click="setQty(row, row.qty - 1)" aria-label="Decrease quantity" class="grid h-full w-9 place-items-center hover:bg-sand"><x-ico name="minus" :size="12" :stroke="1.5" /></button>
                                    <input type="number" min="1" :max="maxQty(row)" x-model.number="row.qty" @change="setQty(row, row.qty)"
                                           class="w-8 border-0 bg-transparent p-0 text-center text-xs tabular-nums [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                                    <button type="button" @click="setQty(row, row.qty + 1)" aria-label="Increase quantity" class="grid h-full w-9 place-items-center hover:bg-sand"><x-ico name="plus" :size="12" :stroke="1.5" /></button>
                                </div>
                                <p class="mt-1 text-[0.6875rem] text-smoke" :class="{ invisible: !row.product?.max_qty }" x-text="row.product?.max_qty ? 'Max ' + row.product.max_qty + ' per order' : ' '"></p>
                            </td>
                            <td class="hidden px-5 py-4 text-right tabular-nums sm:table-cell leading-[2.25rem]" x-text="row.product ? row.product.price_formatted : '—'"></td>
                            <td class="px-5 py-4 text-right tabular-nums leading-[2.25rem]" x-text="row.product ? fmt(row.product.price * row.qty) : '—'"></td>
                            <td class="px-2 py-4 text-center">
                                <button type="button" class="text-taupe hover:text-ink mx-auto grid h-[2.25rem] place-items-center" @click="removeRow(row.key)" aria-label="Remove row">
                                    <x-ico name="close" :size="14" :stroke="1.5" />
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>

            <div class="border-t border-ink/10 px-5 py-4">
                <button type="button" class="btn btn-outline btn-sm" @click="addRow()">+ Add item</button>
            </div>
        </div>

        <div class="mt-8 flex flex-col items-end gap-4">
            <div class="w-full max-w-xs space-y-1.5 text-sm sm:text-right">
                <p class="flex justify-between gap-6 sm:justify-end"><span class="text-smoke">Items</span> <span class="tabular-nums" x-text="validRows().length"></span></p>
                <p class="flex justify-between gap-6 font-serif text-xl sm:justify-end"><span>Subtotal</span> <span class="tabular-nums" x-text="fmt(subtotal())"></span></p>
                <p class="text-[0.75rem] text-smoke">Delivery and any offers are applied at checkout.</p>
            </div>
            <p x-show="error" x-cloak class="text-sm text-rouge" x-text="error"></p>
            <button type="button" class="btn btn-primary btn-lg" :disabled="!validRows().length || submitting"
                    @click="placeOrder()" x-text="submitting ? 'Adding…' : 'Add to bag'"></button>
        </div>
    </section>

    @include('partials.bulk-order-script')
@endsection
