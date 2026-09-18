@props(['order'])
<div {{ $attributes->class('card divide-y divide-line') }}>
    <div class="px-4 py-3"><p class="text-sm font-extrabold">Items ({{ $order->items->sum('qty') }})</p></div>
    <ul class="divide-y divide-line">
        @foreach($order->items as $item)
            <li class="flex items-center gap-3 px-4 py-3">
                <div class="h-14 w-14 shrink-0 overflow-hidden rounded-lg border border-line bg-paper">@if($item->image)<img src="{{ \App\Support\Media::url($item->image) }}" alt="" class="img-cover">@endif</div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-bold">{{ $item->name }}</p>
                    <p class="text-xs text-slate">{{ $item->size }} · Qty {{ $item->qty }} × {{ money($item->price) }}</p>
                </div>
                <span class="text-sm font-bold tabular">{{ money($item->total) }}</span>
            </li>
        @endforeach
    </ul>
    <dl class="space-y-1.5 px-4 py-3 text-sm">
        <div class="flex justify-between"><dt class="text-slate">Item total</dt><dd class="tabular">{{ money($order->subtotal) }}</dd></div>
        @if($order->discount > 0)<div class="flex justify-between"><dt class="text-slate">Coupon{{ $order->coupon_code ? ' ('.$order->coupon_code.')' : '' }}</dt><dd class="tabular text-leaf">− {{ money($order->discount) }}</dd></div>@endif
        <div class="flex justify-between"><dt class="text-slate">Delivery{{ $order->shipping_method ? ' · '.$order->shipping_method : '' }}</dt><dd class="tabular">{{ $order->shipping_cost > 0 ? money($order->shipping_cost) : 'FREE' }}</dd></div>
        @if($order->tax > 0)<div class="flex justify-between"><dt class="text-slate">Taxes</dt><dd class="tabular">{{ money($order->tax) }}</dd></div>@endif
        <div class="flex justify-between border-t border-line pt-2 text-base font-extrabold"><dt>Total paid</dt><dd class="tabular">{{ money($order->total) }}</dd></div>
    </dl>
</div>
