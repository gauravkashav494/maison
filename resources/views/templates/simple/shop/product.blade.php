@extends('layouts.app')

{{--
    Product detail without a gallery: the facts, the options when there are any, a quantity
    box and the add button. Reached from a list row, and the only way to order a product that
    has options to choose.
--}}

@php
    $card = $product->toCard();
    $attributes = $product->variantAttributes();
    $max = $product->max_qty ?: ($product->stock > 0 ? $product->stock : 1);
@endphp

@section('content')
<div class="ol-page" x-data="productPage(@js($card))">
    <p class="ol-sub"><a href="{{ route('shop.index') }}">Order list</a>
        @if($product->category) · <a href="{{ route('shop.category', $product->category->slug) }}">{{ $product->category->name }}</a>@endif
    </p>

    <div class="ol-detail">
        <div class="ol-head">
            <h1>{{ $product->name }}</h1>
            @if($product->description)<p>{{ $product->description }}</p>@endif
        </div>

        <dl>
            <dt>Code</dt>
            <dd class="ol-code">{{ $product->sku ?: '—' }}</dd>

            <dt>Price</dt>
            <dd>{{ money($product->price) }}
                @if($product->compare_at_price)<span class="ol-sub" style="display:inline"> was {{ money($product->compare_at_price) }}</span>@endif
            </dd>

            @if($product->brand)
                <dt>Brand</dt>
                <dd>{{ $product->brand }}</dd>
            @endif

            @if($unit = ($product->sizes[0] ?? null))
                <dt>Supplied as</dt>
                <dd>{{ $unit }}</dd>
            @endif

            <dt>Stock</dt>
            <dd>
                <span class="ol-stock {{ $product->stock <= 0 ? 'is-out' : ($product->stock <= 10 ? 'is-low' : 'is-ok') }}">
                    {{ $product->stock <= 0 ? 'Out of stock' : number_format($product->stock).' available' }}
                </span>
            </dd>
        </dl>

        {{-- Colour and size are rendered here; any further attributes come from the shared component. --}}
        @foreach($attributes as $attribute)
            @if(count($attribute['values']) > 1 && in_array(strtolower($attribute['name']), ['size', 'colour', 'color'], true))
                <div>
                    <p class="ol-sub" style="margin-bottom:.4rem">{{ $attribute['name'] }}</p>
                    <div style="display:flex;flex-wrap:wrap;gap:.4rem">
                        @foreach($attribute['values'] as $value)
                            <button type="button"
                                    class="ol-add is-secondary"
                                    @click="options[@js($attribute['name'])] = @js($value); size = @js($value)"
                                    :class="options[@js($attribute['name'])] === @js($value) && 'ol-add'">{{ $value }}</button>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach

        <x-variant-options :product="$product" />

        @if($product->stock > 0)
            <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:center">
                <span class="ol-qty">
                    <button type="button" @click="qty = Math.max(1, qty - 1)" aria-label="Decrease quantity">−</button>
                    <input type="number" min="1" max="{{ $max }}" x-model.number="qty" aria-label="Quantity">
                    <button type="button" @click="qty = Math.min({{ $max }}, qty + 1)" aria-label="Increase quantity">+</button>
                </span>
                <button type="button" class="ol-add" :disabled="$store.cart.busy" @click="add()">Add to order</button>
                <a href="{{ route('cart') }}" class="ol-choose">View order</a>
            </div>
            @if(collect($attributes)->contains(fn (array $attribute) => count($attribute['values']) > 1))
                <p class="ol-note">Choose every option above before adding this product.</p>
            @endif
        @else
            <p class="ol-note">This product is out of stock. Contact us for the next delivery date.</p>
        @endif

        @if($product->details)
            <div class="ol-note">{!! $product->details !!}</div>
        @endif
    </div>
</div>
@endsection
