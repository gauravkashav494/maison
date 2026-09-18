@props(['order'])
<div {{ $attributes->class('card divide-y divide-line-soft') }}>
    <div class="px-4 py-3"><p class="font-serif text-lg">Items ({{ $order->items->sum('qty') }})</p></div>
    <ul class="divide-y divide-line-soft">
        @foreach($order->items as $item)
            <li class="flex items-center gap-3 px-4 py-3">
                <div class="h-14 w-14 shrink-0 overflow-hidden rounded-md border border-line-soft bg-cream">@if($item->image)<img src="{{ \App\Support\Media::url($item->image) }}" alt="" class="img-cover">@endif</div>
                <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold">{{ $item->name }}</p><p class="text-xs text-muted">{{ $item->size }} · Qty {{ $item->qty }} × {{ money($item->price) }}</p></div>
                <span class="text-sm font-semibold tabular">{{ money($item->total) }}</span>
            </li>
        @endforeach
    </ul>
    <dl class="space-y-1.5 px-4 py-3 text-sm">
        <div class="flex justify-between"><dt class="text-muted">Subtotal</dt><dd class="tabular">{{ money($order->subtotal) }}</dd></div>
        @if($order->discount > 0)<div class="flex justify-between"><dt class="text-muted">Coupon{{ $order->coupon_code ? ' ('.$order->coupon_code.')' : '' }}</dt><dd class="tabular text-leaf">− {{ money($order->discount) }}</dd></div>@endif
        <div class="flex justify-between"><dt class="text-muted">Delivery{{ $order->shipping_method ? ' · '.$order->shipping_method : '' }}</dt><dd class="tabular">{{ $order->shipping_cost > 0 ? money($order->shipping_cost) : 'Free' }}</dd></div>
        @if($order->tax > 0)<div class="flex justify-between"><dt class="text-muted">Taxes</dt><dd class="tabular">{{ money($order->tax) }}</dd></div>@endif
        <div class="flex justify-between border-t border-line pt-2 font-serif text-lg"><dt>Total</dt><dd class="tabular font-semibold">{{ money($order->total) }}</dd></div>
    </dl>
</div>
