@extends('layouts.app')

{{--
    Bulk order: add many products to the cart in one pass instead of one at a time.
    Behaviour lives in partials/bulk-order-script (shared by every template); lines go
    to the existing cart, so cart, checkout and confirmation are unchanged. Prices are
    never editable here — the shopper always pays the product's current price.
--}}

@section('content')
<x-page-head title="Bulk order" text="Search by name or code, set a quantity for each line, and add them all to your cart together." :breadcrumbs="['Bulk order' => null]" />

<section class="p-container py-4 lg:py-6" x-data="bulkOrder(@js($suggestions))">
    <div class="card overflow-hidden">
        <table class="block w-full border-collapse text-sm sm:table">
            <thead class="hidden sm:table-header-group">
                <tr class="border-b border-line bg-canvas text-left text-xs font-bold uppercase tracking-wide text-slate">
                    <th class="px-4 py-3">Item</th>
                    <th class="px-4 py-3 w-36">Qty</th>
                    <th class="px-4 py-3 w-28 text-right">Price</th>
                    <th class="px-4 py-3 w-28 text-right">Amount</th>
                    <th class="w-12 px-2 py-3"></th>
                </tr>
            </thead>
            <tbody class="block sm:table-row-group">
                <template x-for="row in rows" :key="row.key">
                    <tr class="border-b border-line last:border-0 align-top grid grid-cols-[auto_1fr_auto] items-start sm:table-row">
                        <td class="relative col-span-3 px-4 pb-1 pt-3 sm:py-3">
                            <template x-if="!row.product">
                                <div>
                                    <input type="text" class="field" placeholder="Search item or code…"
                                           x-model="row.query" @input="search(row)"
                                           @focus="row.open = true; row.anchorEl = $el.parentElement; row.pos = anchorPos($el)"
                                           autocomplete="off">
                                    <template x-teleport="body">
                                        <div x-show="row.open && listFor(row).length" x-cloak
                                             @click.outside="if (!(row.anchorEl && row.anchorEl.contains($event.target))) row.open = false"
                                             @scroll.window="row.open = false" @resize.window="row.open = false"
                                             :style="`top:${row.pos.top}px; left:${row.pos.left}px; width:${row.pos.width}px;`"
                                             class="absolute z-50 max-h-[28rem] overflow-y-auto rounded-xl border border-line bg-white shadow-float">
                                            <p x-show="!row.query.trim()" class="px-3 pt-2 text-[0.6875rem] font-bold uppercase tracking-wide text-slate">Popular items</p>
                                            <template x-for="p in listFor(row)" :key="p.id">
                                                <button type="button" @click="pick(row, p)"
                                                        class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left hover:bg-sky">
                                                    <span>
                                                        <span class="block font-semibold" x-text="p.name"></span>
                                                        <span class="block text-xs text-slate" x-text="meta(p)"></span>
                                                    </span>
                                                    <span class="whitespace-nowrap font-bold tabular" x-text="p.price_formatted"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </template>
                                    <p x-show="row.searching" x-cloak class="mt-1 text-xs text-slate">Searching…</p>
                                </div>
                            </template>
                            <template x-if="row.product">
                                <div class="flex items-start justify-between gap-2">
                                    <span class="min-w-0">
                                        <span class="block font-bold" x-text="row.product.name"></span>
                                        <span class="block text-xs text-slate" x-text="[row.product.sku, row.product.sizes.length === 1 ? row.product.sizes[0] : ''].filter(Boolean).join(' · ')"></span>
                                        @include('partials.bulk-order-variants', ['selectClass' => 'rounded-lg border border-line bg-white px-2 py-1 text-xs font-semibold'])
                                    </span>
                                    <button type="button" class="text-xs font-bold text-primary hover:underline" @click="clear(row)">Change</button>
                                </div>
                            </template>
                        </td>
                        <td class="px-4 py-3">
                            <div class="stepper" :class="{ 'opacity-40 pointer-events-none': !row.product }">
                                <button type="button" @click="setQty(row, row.qty - 1)" aria-label="Decrease quantity"><x-ico name="minus" :size="14" /></button>
                                <input type="number" min="1" :max="maxQty(row)" x-model.number="row.qty" @change="setQty(row, row.qty)"
                                       class="w-9 border-0 bg-transparent p-0 text-center font-bold tabular [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                                <button type="button" @click="setQty(row, row.qty + 1)" aria-label="Increase quantity"><x-ico name="plus" :size="14" /></button>
                            </div>
                            <p class="mt-1 text-xs text-slate" :class="{ invisible: !row.product?.max_qty }" x-text="row.product?.max_qty ? 'Max ' + row.product.max_qty + ' per order' : ' '"></p>
                        </td>
                        <td class="hidden px-4 py-3 text-right tabular sm:table-cell leading-[2.5875rem]" x-text="row.product ? row.product.price_formatted : '—'"></td>
                        <td class="px-4 py-3 text-right font-extrabold tabular leading-[2.5875rem]" x-text="row.product ? fmt(row.product.price * row.qty) : '—'"></td>
                        <td class="px-2 py-3 text-center">
                            <button type="button" class="text-mist hover:text-danger mx-auto grid h-[2.5875rem] place-items-center" @click="removeRow(row.key)" aria-label="Remove row">
                                <x-ico name="close" :size="16" />
                            </button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>

        <div class="border-t border-line px-4 py-3">
            <button type="button" class="btn btn-outline btn-sm" @click="addRow()">+ Add item</button>
        </div>
    </div>

    <div class="mt-6 flex flex-col items-end gap-3">
        <div class="w-full max-w-xs space-y-1 text-sm sm:text-right">
            <p class="flex justify-between gap-6 sm:justify-end"><span class="text-slate">Items</span> <span x-text="validRows().length"></span></p>
            <p class="flex justify-between gap-6 font-display text-base font-extrabold sm:justify-end"><span>Subtotal</span> <span class="tabular" x-text="fmt(subtotal())"></span></p>
            <p class="text-xs text-slate">Delivery and any offers are applied at checkout.</p>
        </div>
        <p x-show="error" x-cloak class="text-sm font-semibold text-danger" x-text="error"></p>
        <button type="button" class="btn btn-primary btn-lg" :disabled="!validRows().length || submitting"
                @click="placeOrder()" x-text="submitting ? 'Adding…' : 'Add to cart'"></button>
    </div>
</section>

@include('partials.bulk-order-script')
@endsection
