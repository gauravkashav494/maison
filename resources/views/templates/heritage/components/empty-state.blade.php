@props(['icon' => 'package', 'title', 'text' => null])
<div {{ $attributes->class('card flex flex-col items-center px-6 py-14 text-center') }}>
    <span class="grid h-16 w-16 place-items-center rounded-full border border-gold bg-cream text-gold"><x-ico :name="$icon" :size="26" :stroke="1.4" /></span>
    <p class="mt-4 font-serif text-xl">{{ $title }}</p>
    @if($text)<p class="mt-1 max-w-sm text-sm text-muted">{{ $text }}</p>@endif
    @if(trim($slot))<div class="mt-5">{{ $slot }}</div>@endif
</div>
