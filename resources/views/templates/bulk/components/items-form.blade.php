{{--
    The order form, laid out like an invoice: one row per item. Each row's Item is a search box
    over the catalogue (name, size, code or category), with results grouped by category; the
    Category filter on the tab row — searchable too — narrows every row to one category. Rate and amount are the server's figures for the
    chosen item — the buyer chooses only what and how many.

    Rows are kept in this browser as a draft until they are added, so a long order survives a
    reload. $index comes from App\Templates\Bulk\OrderLines::index().
--}}
@props(['index'])

<div id="order-form" x-data="itemsForm(@js($index))" @bk-load-order.window="load($event.detail)">
    {{-- The Category filter sits on the tab row (the workspace's #bk-tab-tools slot) but is still
         part of this form: x-teleport moves where it is drawn, not whose data it uses. --}}
    <template x-teleport="#bk-tab-tools">
        <div class="flex w-full items-center gap-2 sm:w-auto">
            <label for="bk-cat-filter" class="text-sm font-bold">Category</label>
            <div class="bk-combo min-w-0 flex-1 sm:w-64 sm:flex-none" @click.outside="closeCategory()">
                <input id="bk-cat-filter" type="text" class="bk-input bk-combo-input" autocomplete="off" spellcheck="false"
                       role="combobox" aria-autocomplete="list" aria-controls="bk-cat-list" :aria-expanded="cat.open"
                       :aria-activedescendant="cat.open && categoryChoices.length ? 'bk-cat-opt-' + cat.hi : null"
                       :value="cat.open ? cat.q : (category || 'All categories')"
                       @input="openCategory(); cat.q = $event.target.value; cat.hi = 0"
                       :placeholder="category || 'All categories'"
                       @focus="openCategory()" @click="openCategory()"
                       @keydown.down.prevent="moveCategory(1)" @keydown.up.prevent="moveCategory(-1)"
                       @keydown.enter.prevent="cat.open ? pickCategory(categoryChoices[cat.hi]) : openCategory()"
                       @keydown.escape="closeCategory()" @keydown.tab="closeCategory()">
                <ul id="bk-cat-list" class="bk-combo-list" role="listbox" x-show="cat.open" x-cloak>
                    <template x-for="(name, i) in categoryChoices" :key="name">
                        <li :id="'bk-cat-opt-' + i" role="option" :aria-selected="name === category"
                            class="bk-combo-opt" :class="{ 'is-hi': i === cat.hi, 'is-on': name === category }"
                            @mousedown.prevent="pickCategory(name)" @mousemove="cat.hi = i"
                            x-text="name || 'All categories'"></li>
                    </template>
                    <li class="bk-combo-empty" x-show="!categoryChoices.length">No category matches “<span x-text="cat.q"></span>”</li>
                </ul>
            </div>
        </div>
    </template>

    <table class="bk-lines">
        <thead>
            <tr>
                <th scope="col">Item <span aria-hidden="true">*</span></th>
                <th scope="col" class="is-num">Qty <span aria-hidden="true">*</span></th>
                <th scope="col" class="is-num">Rate ₹</th>
                <th scope="col" class="is-num">Amount</th>
                <th scope="col"><span class="sr-only">Remove</span></th>
            </tr>
        </thead>
        <tbody>
            <template x-for="row in rows" :key="row.id">
                <tr :class="{ 'has-error': row.error, 'is-collapsed': !row.expanded }">
                    {{-- Phones only: the row's card header (item, amount, and a toggle that folds the card). --}}
                    <td class="bk-lines-head">
                        <button type="button" class="bk-lines-toggle" @click="toggle(row)"
                                :aria-expanded="row.expanded" :aria-controls="'bk-item-' + row.id">
                            <span class="bk-lines-title" x-text="heading(row)"></span>
                            <span class="bk-lines-badge" x-show="row.line" x-text="money(amount(row)) + '.00'"></span>
                            <x-heroicon-m-chevron-down class="bk-lines-chev" aria-hidden="true" />
                        </button>
                    </td>
                    <td class="bk-lines-item" data-label="Item *">
                        <div class="bk-combo" @click.outside="row.open && closePicker(row)">
                            <input type="text" :id="'bk-item-' + row.id" class="bk-cell-select bk-combo-input" autocomplete="off" spellcheck="false"
                                   role="combobox" aria-autocomplete="list" aria-label="Item" :aria-controls="'bk-list-' + row.id" :aria-expanded="row.open"
                                   :aria-activedescendant="row.open && results(row).flat.length ? 'bk-opt-' + row.id + '-' + row.hi : null"
                                   :value="row.q" @input="openPicker(row); row.q = $event.target.value; row.hi = Math.max(0, results(row).flat.findIndex((l) => l.max > 0))"
                                   :placeholder="row.line ? display(row.line) : 'Search by name or code…'"
                                   @focus="openPicker(row)" @click="openPicker(row)"
                                   @keydown.down.prevent="move(row, 1)" @keydown.up.prevent="move(row, -1)"
                                   @keydown.enter.prevent="pickHighlighted(row)"
                                   @keydown.escape="closePicker(row)" @keydown.tab="closePicker(row)">
                            <div :id="'bk-list-' + row.id" class="bk-combo-list" role="listbox" aria-label="Items" x-show="row.open" x-cloak>
                                <template x-for="group in results(row).groups" :key="group.name">
                                    <div role="group" :aria-label="group.name">
                                        <p class="bk-combo-group" x-text="group.name"></p>
                                        <template x-for="item in group.items" :key="item.line.key">
                                            <div :id="'bk-opt-' + row.id + '-' + item.i" role="option"
                                                 :aria-selected="item.line.key === row.key" :aria-disabled="item.line.max <= 0"
                                                 class="bk-combo-opt" :class="{ 'is-hi': item.i === row.hi, 'is-on': item.line.key === row.key, 'is-off': item.line.max <= 0 }"
                                                 @mousedown.prevent="pick(row, item.line)" @mousemove="item.line.max > 0 && (row.hi = item.i)">
                                                <span x-text="title(item.line)"></span>
                                                <span class="bk-combo-meta" :class="item.line.max <= 0 && 'is-out'" x-text="item.line.max <= 0 ? 'Out of stock' : (item.line.sku || '')"></span>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                <p class="bk-combo-empty" x-show="!results(row).flat.length">
                                    No item matches “<span x-text="row.q"></span>”<template x-if="category"><span> in <span x-text="category"></span></span></template>
                                </p>
                                <p class="bk-combo-empty" x-show="results(row).more > 0" x-text="results(row).more + ' more — keep typing to narrow down'"></p>
                            </div>
                        </div>
                        <span class="bk-error" x-show="row.error" x-text="row.error"></span>
                    </td>
                    <td class="is-num" data-label="Qty *">
                        <input type="number" :id="'bk-qty-' + row.id" inputmode="numeric" min="1" :max="row.line?.max"
                               class="bk-lines-qty" x-model.number="row.qty" :disabled="!row.line"
                               @change="fitQty(row)" @keydown.enter.prevent="nextRow(row)" aria-label="Quantity">
                    </td>
                    <td class="is-num" data-label="Rate ₹"><span class="bk-lines-val" x-text="row.line ? rate(row.line.list_price) : ''"></span></td>
                    <td class="is-num is-strong" data-label="Amount ₹"><span class="bk-lines-val"><span class="bk-lines-cur" x-show="row.line">₹</span><span x-text="row.line ? rate(amount(row)) : ''"></span></span></td>
                    <td class="is-icon">
                        <button type="button" class="bk-del" @click="removeRow(row)" aria-label="Remove row"><x-heroicon-o-trash aria-hidden="true" /><span class="bk-del-label" aria-hidden="true">Remove item</span></button>
                    </td>
                </tr>
            </template>
        </tbody>
    </table>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex w-full flex-wrap items-center gap-3 sm:w-auto">
            <button type="button" class="bk-btn bk-btn-outline" @click="addRow()"><x-heroicon-o-plus aria-hidden="true" />Add item</button>
            <button type="button" class="bk-btn bk-btn-ghost ml-auto text-sm sm:ml-0" x-show="filled.length" x-cloak @click="reset()">Clear</button>
            <p class="bk-ok w-full sm:w-auto" role="status" x-show="message" x-text="message" x-cloak></p>
        </div>
        <button type="button" class="bk-btn bk-btn-primary w-full sm:w-auto sm:!px-6 sm:!py-3" :disabled="busy || !filled.length" @click="addAll()">
            <x-heroicon-o-shopping-cart aria-hidden="true" />
            <span x-text="busy ? 'Adding…' : 'Add ' + filled.length + (filled.length === 1 ? ' item' : ' items') + ' to order'">Add to order</span>
        </button>
    </div>
    <p class="bk-muted mt-2 text-xs">At most {{ \App\Templates\Bulk\OrderLines::MAX_PER_LINE }} per item. Delivery and GST are added at checkout.</p>
</div>
