{{--
    Heart that saves a product to the wishlist (Saved items) or takes it off again. The list is
    the shared per-device one ($store.wishlist, kept in the browser), so it is the same list the
    header count and the Saved items page read.
--}}
@props(['product'])
@php $id = (int) $product->id; @endphp

<button type="button" x-data {{ $attributes->class('bk-heart') }}
        @click="$store.wishlist.toggle({{ $id }}, {{ Js::from($product->name) }})"
        :class="$store.wishlist.has({{ $id }}) && 'is-on'"
        :aria-pressed="$store.wishlist.has({{ $id }}).toString()"
        :aria-label="$store.wishlist.has({{ $id }}) ? @js('Remove '.$product->name.' from saved items') : @js('Save '.$product->name.' for later')"
        aria-pressed="false" aria-label="Save {{ $product->name }} for later">
    <x-heroicon-o-heart aria-hidden="true" />
</button>
