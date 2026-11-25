{{--
    Heart that saves to the wishlist (Saved items) or takes off again. The list is the shared
    per-device one ($store.wishlist, see resources/js/saved-items.js), so it is the same list the
    header count and the Saved items page read.
    Without :options it is the product's heart: filled when anything of the product is saved, and
    tapping a filled one takes all of it off. With :options (["Size" => "M"]) it saves exactly
    that size.
--}}
@props(['product', 'options' => null])
@php
    $id = (int) $product->id;
    $options = $options ?: null;
    $args = $id.', '.Js::from($options);
    $what = $product->name.($options ? ' ('.implode(' / ', $options).')' : '');
@endphp

<button type="button" x-data {{ $attributes->class('bk-heart') }}
        @click="$store.wishlist.toggle({{ $id }}, {{ Js::from($product->name) }}, {{ Js::from($options) }})"
        :class="$store.wishlist.has({{ $args }}) && 'is-on'"
        :aria-pressed="$store.wishlist.has({{ $args }}).toString()"
        :aria-label="$store.wishlist.has({{ $args }}) ? @js('Remove '.$what.' from saved items') : @js('Save '.$what.' for later')"
        aria-pressed="false" aria-label="Save {{ $what }} for later">
    <x-heroicon-o-heart aria-hidden="true" />
</button>
