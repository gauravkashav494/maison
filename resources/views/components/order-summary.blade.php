@props(['order'])
<div {{ $attributes->class('border border-ink/10 bg-cream') }}>
    <ul class="divide-y divide-ink/10 px-6">
        @foreach($order->items as $item)
            <li class="flex items-center gap-4 py-4">
                <div class="relative h-20 w-16 shrink-0 overflow-hidden bg-sand">@if($item->image_url)<img src="{{ $item->image_url }}" alt="" class="img-cover">@endif</div>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-serif text-[0.9375rem]">{{ $item->name }}</p>
                    <p class="text-xs text-smoke">{{ $item->color ? $item->color.' · ' : '' }}{{ $item->size }} · Qty {{ $item->qty }}</p>
                </div>
                <span class="text-sm tabular-nums">{{ money($item->total) }}</span>
            </li>
        @endforeach
    </ul>
    <dl class="space-y-2.5 border-t border-ink/10 px-6 py-5 text-sm">
        <div class="flex justify-between"><dt class="text-smoke">Subtotal</dt><dd class="tabular-nums">{{ money($order->subtotal) }}</dd></div>
        @if($order->discount)<div class="flex justify-between"><dt class="text-smoke">Discount{{ $order->coupon_code ? " ({$order->coupon_code})" : '' }}</dt><dd class="tabular-nums text-emerald-700">− {{ money($order->discount) }}</dd></div>@endif
        <div class="flex justify-between"><dt class="text-smoke">Shipping{{ $order->shipping_method ? " · {$order->shipping_method}" : '' }}</dt><dd class="tabular-nums">{{ $order->shipping_cost ? money($order->shipping_cost) : 'Complimentary' }}</dd></div>
        @if($order->tax)<div class="flex justify-between"><dt class="text-smoke">Taxes</dt><dd class="tabular-nums">{{ money($order->tax) }}</dd></div>@endif
        <div class="flex justify-between border-t border-ink/10 pt-3 text-base"><dt>Total</dt><dd class="tabular-nums">{{ money($order->total) }}</dd></div>
    </dl>
</div>
