@props(['title', 'text' => null, 'href' => null, 'link' => 'View all'])
<div class="sec-head">
    <div><h2 class="sec-title">{{ $title }}</h2>@if($text)<p class="sec-sub">{{ $text }}</p>@endif</div>
    @if($href)<a href="{{ $href }}" class="sec-link">{{ $link }} <x-ico name="arrow-right" :size="16" /></a>@endif
</div>
