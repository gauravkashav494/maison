{{--
    Where an order has got to: the shared order statuses as numbered steps, with the time each
    was reached. A cancelled order shows a single notice instead.
--}}
@props(['order'])
@php
    $steps = \App\Models\Order::STATUSES;
    $at = $order->statusIndex();
    $history = collect($order->status_history ?? [])->keyBy('status');
@endphp

@if($order->status === 'cancelled')
    <p class="bk-notice is-bad" role="status">
        <x-heroicon-o-x-circle aria-hidden="true" />
        This order was cancelled{{ ($h = $history['cancelled'] ?? null) ? ' on '.\Carbon\Carbon::parse($h['at'])->format('d M Y') : '' }}.
    </p>
@else
    <ol class="bk-progress" aria-label="Order progress">
        @foreach($steps as $key => $label)
            @php
                $i = $loop->index;
                $done = $i < $at || ($i === $at && $loop->last); // the last step reached is complete
                $time = $history[$key]['at'] ?? null;
            @endphp
            <li @class(['is-done' => $done, 'is-now' => $i === $at && ! $done]) @if($i === $at) aria-current="step" @endif>
                <span class="bk-progress-dot">@if($done)<x-heroicon-m-check aria-hidden="true" />@else{{ $i + 1 }}@endif</span>
                <span class="bk-progress-label">{{ $label }}</span>
                @if($time)<span class="bk-progress-time">{{ \Carbon\Carbon::parse($time)->format('d M, H:i') }}</span>@endif
            </li>
        @endforeach
    </ol>
@endif
