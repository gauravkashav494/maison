{{--
    The customer's "Cancel order" box, on the account order page and the confirmation page.
    Shown while the order can still be cancelled (Order::customerCanCancel); afterwards it shows
    the outcome. The button opens a confirmation dialog (native <dialog>, so Esc and focus are
    handled by the browser) holding the reason and the final button; posts to CancelOrderController.
--}}
@props(['order'])
@if(session('order_cancelled'))
    <p {{ $attributes->class('border border-emerald-700/20 bg-emerald-50 px-4 py-3 text-sm text-emerald-800') }} role="status">{{ session('order_cancelled') }}</p>
@elseif($order->customerCanCancel())
    <div x-data x-init="@if($errors->has('reason')) $nextTick(() => $refs.dialog.showModal()) @endif" {{ $attributes->class('flex flex-col gap-3 border border-ink/15 px-5 py-4 text-left sm:flex-row sm:items-center sm:justify-between') }}>
        <p class="text-sm"><span class="font-medium">Changed your mind?</span> <span class="text-smoke">You can cancel this order until it is dispatched.</span></p>
        <button type="button" @click="$refs.dialog.showModal()" class="btn btn-outline btn-sm shrink-0">Cancel order</button>
        @error('cancel')<p class="text-xs text-rouge sm:order-last">{{ $message }}</p>@enderror

        <dialog x-ref="dialog" @click.self="$refs.dialog.close()" aria-labelledby="cancel-order-title" class="m-auto w-[min(30rem,calc(100%-2rem))] bg-ivory p-0 text-left text-ink shadow-2xl backdrop:bg-ink/50">
            <form method="POST" action="{{ route('orders.cancel', $order->number) }}" class="p-6">
                @csrf
                <h2 id="cancel-order-title" class="font-serif text-2xl">Cancel order {{ $order->number }}?</h2>
                <p class="mt-2 text-sm text-smoke">The order will be cancelled and cannot be restored.@if($order->payment_status === 'paid') Your payment will be refunded to the original payment method.@endif</p>
                <label class="mt-5 block"><span class="eyebrow block text-taupe">Reason (optional)</span><textarea name="reason" rows="3" maxlength="500" class="input-luxe mt-2" placeholder="Ordered by mistake, need a different size…">{{ old('reason') }}</textarea>@error('reason')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
                <div class="mt-6 flex flex-wrap justify-end gap-2">
                    <button type="button" autofocus @click="$refs.dialog.close()" class="btn btn-outline btn-sm">Keep order</button>
                    <button type="submit" class="btn btn-primary btn-sm">Yes, cancel order</button>
                </div>
            </form>
        </dialog>
    </div>
@elseif($errors->has('cancel'))
    <p {{ $attributes->class('flex flex-col gap-3 border border-ink/15 px-5 py-4 text-left sm:flex-row sm:items-center sm:justify-between') }}>{{ $errors->first('cancel') }}</p>
@endif
