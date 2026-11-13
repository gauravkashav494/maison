{{-- An order's status as a coloured badge. --}}
@props(['order'])
<span {{ $attributes->class(['bk-badge', match ($order->status) {
    'delivered' => 'bk-badge-ok',
    'cancelled' => 'bk-badge-out',
    default => 'bk-badge-low',
}]) }}>{{ $order->statusLabel() }}</span>
