{{--
    Colour swatches on a product card. Hovering (or focusing) a colour that has photos shows its
    first photo in the card's picture (<x-card-colour-photo>), as most fashion stores do; leaving
    puts the product's own picture back. Photos are picked per colour in the product form
    (Variants → Colour).

    $withPhotosOnly: show the row only when at least one colour has photos (the default, so a
    card whose template never showed colours is unchanged otherwise).
    $select: on a product page, a click keeps the colour shown (a second click lets go) instead of
    opening the product; hovering still previews. Every colour can be chosen: one without photos
    shows the product's own picture. The choice is kept by colour name, so two colours sharing a
    photo are still told apart.
--}}
@props(['product', 'withPhotosOnly' => true, 'max' => 5, 'select' => false])
@php
    $colours = $product->cardColours();
    $hasPhotos = collect($colours)->contains(fn ($c) => $c['image'] !== null);
@endphp
@if($colours && ($hasPhotos || ! $withPhotosOnly))
    @once
        {{-- Inline once per page: every template has its own CSS bundle. --}}
        <style>
            .card-colours { display: flex; flex-wrap: wrap; align-items: center; gap: 0.35rem; }
            .card-colour { position: relative; display: inline-grid; place-items: center; width: 0.95rem; height: 0.95rem; border-radius: 9999px;
                background: var(--swatch); box-shadow: inset 0 0 0 1px rgba(0,0,0,.12); outline-offset: 2px; transition: transform .15s; }
            .card-colour.has-photo, .card-colour.is-selectable { cursor: pointer; }
            .card-colour.has-photo:hover, .card-colour.has-photo:focus-visible, .card-colour.is-selectable:hover, .card-colour.is-selectable:focus-visible, .card-colour.is-chosen { transform: scale(1.2); box-shadow: inset 0 0 0 1px rgba(0,0,0,.12), 0 0 0 2px #fff, 0 0 0 3px currentColor; }
            .card-colour:disabled { cursor: default; }
            .card-colours.is-large { gap: 0.6rem; }
            .card-colours.is-large .card-colour { width: 1.6rem; height: 1.6rem; }
            .card-colour-text { font-size: .5rem; line-height: 1; text-transform: uppercase; color: inherit; opacity: .7; }
            .card-colour-more { font-size: .625rem; opacity: .7; }
            .card-colour-photo { position: absolute; inset: 0; z-index: 5; width: 100%; height: 100%; object-fit: cover; pointer-events: none; }
        </style>
    @endonce
    <div {{ $attributes->class('card-colours') }}
         {{-- show(name) previews a colour (null for one without photos: the product's own picture);
              restore() goes back to the chosen colour, or to the product's picture. --}}
         x-data="{
             chosen: null,
             photos: @js(collect($colours)->pluck('image', 'name')),
             show(name) { this.$dispatch('card-colour', { id: {{ $product->id }}, src: this.photos[name] ?? null }) },
             restore() { this.show(this.chosen) },
         }"
         aria-label="Available colours">
        @foreach(array_slice($colours, 0, $max) as $c)
            @php
                $src = $c['image'];
                $text = $c['hex'] ? '' : '<span class="card-colour-text">'.e(\Illuminate\Support\Str::limit($c['name'], 3, '')).'</span>';
            @endphp
            @if($select)
                <button type="button" title="{{ $c['name'] }}" aria-label="{{ $c['name'] }}" @class(['card-colour', 'is-selectable', 'has-photo' => $src])
                        style="--swatch: {{ $c['hex'] ?: 'transparent' }}"
                        x-bind:aria-pressed="chosen === @js($c['name'])"
                        x-bind:class="{ 'is-chosen': chosen === @js($c['name']) }"
                        x-on:click="chosen = chosen === @js($c['name']) ? null : @js($c['name']); restore()"
                        x-on:mouseenter="show(@js($c['name']))" x-on:mouseleave="restore()"
                >{!! $text !!}</button>
            @else
                <a href="{{ $product->url }}" title="{{ $c['name'] }}" aria-label="{{ $c['name'] }}" @class(['card-colour', 'has-photo' => $src])
                   style="--swatch: {{ $c['hex'] ?: 'transparent' }}"
                   @if($src)
                       x-on:mouseenter="show(@js($c['name']))" x-on:focus="show(@js($c['name']))"
                       x-on:mouseleave="restore()" x-on:blur="restore()"
                   @endif
                >{!! $text !!}</a>
            @endif
        @endforeach
        @if(count($colours) > $max)<span class="card-colour-more">+{{ count($colours) - $max }}</span>@endif
    </div>
@endif
