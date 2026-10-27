{{--
    Pickers for the attributes a product varies by, beyond the colour and size lists each
    template already renders itself. The chosen values go into the Alpine `options` object of
    the surrounding product component, which posts them with the add-to-bag request; the server
    checks them again and prices the line from the matching variant.

    Styling is deliberately neutral (stock Tailwind greys) so the one component fits every
    template. Each template bundle lists this file as a source so the classes are built.
--}}
@props(['product'])

@php
    $skip = ['size', 'colour', 'color'];
    $extras = collect($product->variantAttributes())
        ->reject(fn (array $attribute) => in_array(strtolower($attribute['name']), $skip, true))
        ->values();
    $variants = $product->sellsVariants()
        ? array_map(fn (array $variant) => $variant + ['price_formatted' => money($variant['price'])], $product->variantList())
        : [];
@endphp

@if($extras->isNotEmpty())
    <div class="mt-8 space-y-6">
        @foreach($extras as $attribute)
            @php($name = $attribute['name'])
            <div>
                <p class="mb-3 text-[0.6875rem] font-medium uppercase tracking-[0.2em]">
                    {{ $name }}
                    <span class="ml-2 normal-case tracking-normal text-neutral-500" x-text="options[@js($name)] ?? ''"></span>
                </p>
                <div class="flex flex-wrap gap-2">
                    @foreach($attribute['values'] as $value)
                        @if(! empty($attribute['swatches'][$value]))
                            <button type="button"
                                    @click="options[@js($name)] = @js($value)"
                                    aria-label="{{ $value }}"
                                    :aria-pressed="options[@js($name)] === @js($value)"
                                    class="h-8 w-8 rounded-full ring-1 ring-offset-2 transition-all"
                                    :class="options[@js($name)] === @js($value) ? 'ring-neutral-900' : 'ring-neutral-300 hover:ring-neutral-500'"
                                    style="background-color: {{ $attribute['swatches'][$value] }}"></button>
                        @else
                            <button type="button"
                                    @click="options[@js($name)] = @js($value)"
                                    :aria-pressed="options[@js($name)] === @js($value)"
                                    class="min-w-14 border px-4 py-3 text-[0.75rem] uppercase tracking-wider transition-colors"
                                    :class="options[@js($name)] === @js($value) ? 'border-neutral-900 bg-neutral-900 text-white' : 'border-neutral-300 hover:border-neutral-900'">{{ $value }}</button>
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endif

@if($variants)
    {{-- Sold by variant: the price and availability follow the chosen combination. --}}
    <div x-data="variantPricing(@js($variants))" class="mt-6" x-cloak>
        <template x-if="current">
            <p class="text-[0.9375rem]">
                <span class="font-medium tabular-nums" x-text="current.price_formatted"></span>
                <span class="ml-2 text-[0.75rem] uppercase tracking-wider"
                      :class="current.stock > 0 ? 'text-neutral-500' : 'text-red-700'"
                      x-text="current.stock > 0 ? (current.sku ? current.sku : 'In stock') : 'Out of stock'"></span>
            </p>
        </template>
        <template x-if="!current">
            <p class="text-[0.75rem] uppercase tracking-wider text-neutral-500">Choose every option to see the price</p>
        </template>
    </div>
@endif
