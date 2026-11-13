{{--
    Picture box for a product or category. Shows the uploaded photo when there is one;
    otherwise an icon drawn from the category's name on a tint picked from its slug, so every
    category keeps the same look everywhere without anyone uploading artwork.
--}}
@props(['category' => null, 'image' => null, 'alt' => ''])

@php
    $name = \Illuminate\Support\Str::lower(($category?->slug ?? '').' '.($category?->name ?? ''));
    $icon = match (true) {
        \Illuminate\Support\Str::contains($name, ['clean', 'chemical', 'wash', 'deterg']) => 'beaker',
        \Illuminate\Support\Str::contains($name, ['pack', 'dispos', 'contain', 'bag', 'carton']) => 'archive-box',
        \Illuminate\Support\Str::contains($name, ['glove', 'hygien', 'safety', 'sanit']) => 'hand-raised',
        \Illuminate\Support\Str::contains($name, ['kitchen', 'food', 'cater', 'bak']) => 'fire',
        \Illuminate\Support\Str::contains($name, ['office', 'station', 'paper', 'print']) => 'document-text',
        default => 'cube',
    };
    $tone = $category ? crc32((string) $category->slug) % 5 : 0;
@endphp

<div {{ $attributes->merge(['class' => 'bk-art']) }} data-tone="{{ $tone }}">
    <x-dynamic-component :component="'heroicon-o-'.$icon" aria-hidden="true" />
    @if($image)
        <img src="{{ $image }}" alt="{{ $alt }}" loading="lazy">
    @endif
</div>
