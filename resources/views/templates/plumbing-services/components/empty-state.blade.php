@props(['icon' => 'search', 'title', 'text' => null])
<div {{ $attributes->class('card flex flex-col items-center px-6 py-12 text-center') }}>
    <span class="grid h-16 w-16 place-items-center rounded-full bg-sky text-primary"><x-ico :name="$icon" :size="28" :stroke="1.5" /></span>
    <p class="mt-4 font-display text-lg font-bold">{{ $title }}</p>
    @if($text)<p class="mt-1 max-w-sm text-sm text-slate">{{ $text }}</p>@endif
    @if(trim($slot))<div class="mt-5 flex flex-wrap justify-center gap-2">{{ $slot }}</div>@endif
</div>