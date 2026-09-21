@props(['order'])
@php
    $steps = \App\Models\Order::STATUSES;
    $idx = $order->statusIndex();
    $history = collect($order->status_history ?? [])->keyBy('status');
@endphp
@if($order->status === 'cancelled')
    <div class="flex items-center gap-2 rounded-xl border border-danger/30 bg-danger-light px-4 py-3 text-sm text-danger"><x-ico name="alert" :size="18" /> This order was cancelled{{ ($h = $history['cancelled'] ?? null) ? ' on '.\Carbon\Carbon::parse($h['at'])->format('d M Y') : '' }}.</div>
@else
<ol class="relative grid grid-cols-5 gap-1 text-center text-[0.625rem] font-semibold sm:text-xs">
    @foreach($steps as $key => $label)
        @php $i = $loop->index; $done = $i <= $idx; $current = $i === $idx; $at = $history[$key]['at'] ?? null; @endphp
        <li class="relative">
            @if(!$loop->first)<span class="absolute right-1/2 top-3.5 h-0.5 w-full {{ $done ? 'bg-primary' : 'bg-line' }}"></span>@endif
            <span class="relative mx-auto grid h-7 w-7 place-items-center rounded-full border-2 {{ $done ? 'border-primary bg-primary text-white' : 'border-line bg-white text-mist' }} {{ $current ? 'ring-4 ring-primary/20' : '' }}">
                @if($done)<x-ico name="check" :size="14" :stroke="3" />@else<span class="h-1.5 w-1.5 rounded-full bg-line"></span>@endif
            </span>
            <p class="mt-2 leading-tight {{ $done ? 'text-ink' : 'text-mist' }}">{{ $label }}</p>
            @if($at)<p class="mt-0.5 font-normal text-mist">{{ \Carbon\Carbon::parse($at)->format('d M, H:i') }}</p>@endif
        </li>
    @endforeach
</ol>
@endif
