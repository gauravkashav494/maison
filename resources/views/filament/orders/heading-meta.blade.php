{{--
    Under the order number: where the order stands and whether it is paid, then when it was
    placed and how many units it holds — what staff check first on any order.
--}}
@php /** @var \App\Models\Order $order */ @endphp
<span class="flex flex-wrap items-center gap-x-3 gap-y-2">
    <x-filament::badge :color="$order->statusColor()" size="lg">{{ $order->statusLabel() }}</x-filament::badge>
    <x-filament::badge :color="$order->paymentStatusColor()" size="lg" icon="heroicon-m-banknotes">{{ $order->paymentStatusLabel() }}</x-filament::badge>
    <span class="text-sm text-gray-500 dark:text-gray-400">
        Placed {{ $order->created_at?->format('d M Y, H:i') }}
        · {{ $order->items->sum('qty') }} {{ \Illuminate\Support\Str::plural('unit', $order->items->sum('qty')) }}
        · {{ money($order->total) }}
    </span>
</span>
