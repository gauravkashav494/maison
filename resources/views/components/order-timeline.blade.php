@props(['order'])
@php
    $steps = \App\Models\Order::STATUSES;
    $idx = $order->statusIndex();
    $history = collect($order->status_history ?? [])->keyBy('status');
@endphp
@if($order->status === 'cancelled')
    <div class="border border-rouge/30 bg-rouge/5 px-5 py-4 text-sm text-rouge">This order was cancelled{{ ($h = $history['cancelled'] ?? null) ? ' on '.\Carbon\Carbon::parse($h['at'])->format('d M Y') : '' }}.</div>
@else
<ol class="relative grid grid-cols-5 gap-2 text-center text-[0.5625rem] uppercase tracking-[0.15em] sm:text-[0.625rem]">
    @foreach($steps as $key => $label)
        @php $i = $loop->index; $done = $i <= $idx; $current = $i === $idx; $at = $history[$key]['at'] ?? null; @endphp
        <li class="relative">
            @if(!$loop->first)<span class="absolute right-1/2 top-3 h-px w-full {{ $done ? 'bg-ink' : 'bg-ink/15' }}"></span>@endif
            <span class="relative mx-auto grid h-6 w-6 place-items-center rounded-full border {{ $done ? 'border-ink bg-ink text-ivory' : 'border-ink/20 bg-ivory text-taupe' }} {{ $current ? 'ring-4 ring-gold/30' : '' }}">
                @if($done && !$current)<svg width="10" height="10" viewBox="0 0 12 12" fill="none"><path d="M2 6.5 4.8 9 10 3" stroke="currentColor" stroke-width="1.4"/></svg>@else<span class="h-1.5 w-1.5 rounded-full {{ $current ? 'bg-ivory' : 'bg-taupe/50' }}"></span>@endif
            </span>
            <p class="mt-3 {{ $done ? 'text-ink' : 'text-taupe' }}">{{ $label }}</p>
            @if($at)<p class="mt-1 normal-case tracking-normal text-taupe">{{ \Carbon\Carbon::parse($at)->format('d M, H:i') }}</p>@endif
        </li>
    @endforeach
</ol>
@endif
