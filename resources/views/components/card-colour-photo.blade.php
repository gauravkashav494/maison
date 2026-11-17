{{--
    The photo of the colour being hovered on a card (<x-card-colours>), laid over the card's
    picture. Placed inside the card's picture box, after its own image(s). Its z-index keeps it
    above a template's own picture (Bulk's .bk-art img sits on layer 2).
--}}
@props(['product'])
@if(collect($product->cardColours())->contains(fn ($c) => $c['image'] !== null))
    <img x-data="{ src: null }"
         x-on:card-colour.window="$event.detail.id === {{ $product->id }} && (src = $event.detail.src)"
         x-show="src" x-cloak x-bind:src="src" alt="" aria-hidden="true" decoding="async"
         class="card-colour-photo" style="position:absolute;inset:0;z-index:5;width:100%;height:100%;object-fit:cover;pointer-events:none">
@endif
