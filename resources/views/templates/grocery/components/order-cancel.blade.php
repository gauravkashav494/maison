{{--
    The customer's "Cancel order" box, on the account order page and the confirmation page.
    Shown while the order can still be cancelled (Order::customerCanCancel); afterwards it shows
    the outcome. The button opens a confirmation dialog (native <dialog>, so Esc and focus are
    handled by the browser) holding the reason and the final button; posts to CancelOrderController.
--}}
@props(['order'])
@if(session('order_cancelled'))
    <p {{ $attributes->class('flex items-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold bg-leaf-light text-leaf-dark') }} role="status"><x-ico name="check" :size="16" /> {{ session('order_cancelled') }}</p>
@elseif($order->customerCanCancel())
    <div x-data x-init="@if($errors->has('reason')) $nextTick(() => $refs.dialog.showModal()) @endif" {{ $attributes->class('card flex flex-col gap-3 p-4 text-left sm:flex-row sm:items-center sm:justify-between') }}>
        <div><p class="text-sm font-bold">Changed your mind?</p><p class="text-xs text-slate">You can cancel this order until it is dispatched.</p></div>
        <button type="button" @click="$refs.dialog.showModal()" class="btn btn-outline btn-sm shrink-0">Cancel order</button>
        @error('cancel')<p class="error-text sm:order-last">{{ $message }}</p>@enderror

        <dialog x-ref="dialog" @click.self="$refs.dialog.close()" aria-labelledby="cancel-order-title" class="m-auto w-[min(28rem,calc(100%-2rem))] rounded-2xl bg-white p-0 text-left text-ink shadow-2xl backdrop:bg-black/50">
            <form method="POST" action="{{ route('orders.cancel', $order->number) }}" class="p-6">
                @csrf
                <h2 id="cancel-order-title" class="text-lg font-bold">Cancel order {{ $order->number }}?</h2>
                <p class="mt-1 text-sm text-slate">The order will be cancelled and cannot be restored.@if($order->payment_status === 'paid') Your payment will be refunded to the original payment method.@endif</p>
                <label class="mt-4 block"><span class="label">Reason (optional)</span><textarea name="reason" rows="3" maxlength="500" class="field @error('reason') field-error @enderror" placeholder="Ordered by mistake, need a different size…">{{ old('reason') }}</textarea>@error('reason')<span class="error-text">{{ $message }}</span>@enderror</label>
                <div class="mt-6 flex flex-wrap justify-end gap-2">
                    <button type="button" autofocus @click="$refs.dialog.close()" class="btn btn-ghost btn-sm">Keep order</button>
                    <button type="submit" class="btn btn-primary btn-sm">Yes, cancel order</button>
                </div>
            </form>
        </dialog>
    </div>
@elseif($errors->has('cancel'))
    <p {{ $attributes->class('card flex flex-col gap-3 p-4 text-left sm:flex-row sm:items-center sm:justify-between') }}>{{ $errors->first('cancel') }}</p>
@endif
