{{--
    The customer's "Cancel order" box, on the account order page and the confirmation page.
    Shown while the order can still be cancelled (Order::customerCanCancel); afterwards it shows
    the outcome. The button opens a confirmation dialog (native <dialog>, so Esc and focus are
    handled by the browser) holding the reason and the final button; posts to CancelOrderController.
--}}
@props(['order'])
@if(session('order_cancelled'))
    <p {{ $attributes->class('bk-notice') }} role="status"><x-heroicon-o-check-circle aria-hidden="true" />{{ session('order_cancelled') }}</p>
@elseif($order->customerCanCancel())
    <div x-data x-init="@if($errors->has('reason')) $nextTick(() => $refs.dialog.showModal()) @endif" {{ $attributes->class('bk-card flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between') }}>
        <p class="text-sm"><strong>Need to cancel?</strong> <span class="bk-muted">This order can be cancelled until it is dispatched.</span></p>
        <button type="button" @click="$refs.dialog.showModal()" class="bk-btn bk-btn-outline shrink-0"><x-heroicon-o-x-circle aria-hidden="true" />Cancel order</button>
        @error('cancel')<p class="bk-error sm:order-last">{{ $message }}</p>@enderror

        <dialog x-ref="dialog" @click.self="$refs.dialog.close()" aria-labelledby="cancel-order-title" class="bk-card m-auto w-[min(28rem,calc(100%-2rem))] p-0 text-left text-[var(--bk-ink)] shadow-2xl backdrop:bg-black/50">
            <form method="POST" action="{{ route('orders.cancel', $order->number) }}" class="p-6">
                @csrf
                <h2 id="cancel-order-title" class="text-lg font-extrabold tracking-tight">Cancel order {{ $order->number }}?</h2>
                <p class="bk-muted mt-1 text-sm">The order will be cancelled and cannot be restored.@if($order->payment_status === 'paid') Your payment will be refunded to the original payment method.@endif</p>
                <label class="bk-label mt-4">Reason (optional)<textarea name="reason" rows="3" maxlength="500" class="bk-textarea" placeholder="Ordered by mistake, need a different size…">{{ old('reason') }}</textarea>@error('reason')<span class="bk-error">{{ $message }}</span>@enderror</label>
                <div class="mt-6 flex flex-wrap justify-end gap-2">
                    <button type="button" autofocus @click="$refs.dialog.close()" class="bk-btn bk-btn-outline">Keep order</button>
                    <button type="submit" class="bk-btn bk-btn-primary">Yes, cancel order</button>
                </div>
            </form>
        </dialog>
    </div>
@elseif($errors->has('cancel'))
    <p {{ $attributes->class('bk-card flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between') }}>{{ $errors->first('cancel') }}</p>
@endif
