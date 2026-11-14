{{--
    Paste a list. The buyer pastes "CODE, QTY" lines (or picks a CSV, read in the browser),
    presses Check, and sees every line matched against the catalogue — name, option, price and
    a status — before anything is added. Unknown codes, out-of-stock lines and quantities above
    the per-line limit are called out on their own row; only clean lines are added, and
    whatever fails stays in the box with its reason.

    $index comes from App\Templates\Bulk\OrderLines::index().
--}}
@props(['index'])

<div id="paste-list" x-data="quickOrder(@js($index))">
    <p class="font-extrabold">{{ tsetting('site.pad_heading', 'Paste a list') }}</p>
    <p class="bk-muted mt-1 text-sm">{{ tsetting('site.pad_help') }}</p>

    <label class="sr-only" for="bk-pad">Product codes and quantities</label>
    <textarea id="bk-pad" class="bk-pad mt-3" x-model="text" spellcheck="false" autocomplete="off"
              placeholder="{{ tsetting('site.pad_example', 'CODE, QTY') }}"
              @keydown.ctrl.enter.prevent="check()" @keydown.meta.enter.prevent="check()"></textarea>

    <div class="mt-3 flex flex-wrap items-center gap-2">
        <button type="button" class="bk-btn bk-btn-primary" :disabled="!text.trim()" @click="check()"><x-heroicon-o-check-circle aria-hidden="true" />Check codes</button>
        <label class="bk-btn bk-btn-outline bk-file">
            <x-heroicon-o-arrow-up-tray aria-hidden="true" />Upload CSV
            <input type="file" accept=".csv,.txt,text/csv,text/plain" @change="readFile($event)" aria-label="Upload a CSV of codes and quantities">
        </label>
        <button type="button" class="bk-btn bk-btn-ghost" @click="downloadSample()" title="A CSV with this store's codes, ready to edit and upload">
            <x-heroicon-o-arrow-down-tray aria-hidden="true" />Download sample CSV
        </button>
        <button type="button" class="bk-btn bk-btn-ghost" x-show="text || rows.length" x-cloak @click="reset()">Clear</button>
        <span class="bk-muted text-xs">At most {{ \App\Templates\Bulk\OrderLines::MAX_PER_LINE }} per line.</span>
    </div>

    <p class="bk-ok mt-3" role="status" x-show="message" x-text="message" x-cloak></p>

    <div class="mt-4" x-show="checked && rows.length" x-cloak>
        <table class="bk-table is-stack">
            <thead>
                <tr>
                    <th scope="col">Code</th>
                    <th scope="col">Product</th>
                    <th scope="col">Qty</th>
                    <th scope="col" class="is-num">Amount</th>
                    <th scope="col"><span class="sr-only">Remove</span></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="(row, i) in rows" :key="row.code + i">
                    <tr>
                        <td class="bk-code" data-label="Code" x-text="row.code"></td>
                        <td data-label="Product">
                            <template x-if="row.line">
                                <span class="block text-sm font-bold">
                                    <span x-text="row.line.name"></span>
                                    <span class="bk-muted font-normal" x-show="row.line.label || row.line.unit" x-text="' · ' + (row.line.label || row.line.unit)"></span>
                                </span>
                            </template>
                            <span class="bk-badge mt-1" :class="row.error ? 'bk-badge-out' : (row.note ? 'bk-badge-low' : 'bk-badge-ok')"
                                  x-text="row.error || row.note || 'Ready'"></span>
                        </td>
                        <td data-label="Qty">
                            <input type="number" inputmode="numeric" min="1" class="bk-input !w-20 !py-1.5 text-right"
                                   x-model.number="row.qty" :disabled="!row.line || row.error === 'Out of stock'"
                                   @change="row.line && fit(row)" :aria-label="'Quantity for ' + row.code">
                        </td>
                        <td class="is-num font-bold" data-label="Amount"
                            x-text="row.line && !row.error ? '₹' + (row.qty * row.line.price).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—'"></td>
                        <td class="text-right">
                            <button type="button" class="bk-del" @click="remove(i)" aria-label="Remove line"><x-heroicon-o-trash /></button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>

        <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-[var(--bk-line)] pt-4">
            <button type="button" class="bk-btn bk-btn-primary" :disabled="busy || !ready.length" @click="addAll()">
                <x-heroicon-o-shopping-cart aria-hidden="true" />
                <span x-text="busy ? 'Adding…' : 'Add ' + ready.length + (ready.length === 1 ? ' line' : ' lines') + ' to order'"></span>
            </button>
            <span class="text-sm" x-show="ready.length">About <strong x-text="estimate"></strong> {{ prices_include_tax() ? 'including GST, before delivery' : 'before GST and delivery' }}</span>
            <span class="text-sm text-[var(--bk-out)]" x-show="problems" x-text="problems + (problems === 1 ? ' line needs' : ' lines need') + ' attention and will not be added'"></span>
        </div>
    </div>
</div>
