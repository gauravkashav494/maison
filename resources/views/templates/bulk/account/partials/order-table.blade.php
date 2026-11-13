{{-- A buyer's orders as a table: number, date, lines, status, total. Used by the overview and Orders. --}}
@if($orders->isEmpty())
    <div class="py-8 text-center">
        <x-heroicon-o-clipboard-document-list class="mx-auto size-10 text-brand" aria-hidden="true" />
        <p class="mt-2 font-extrabold">No orders yet</p>
        <p class="bk-muted mt-1 text-sm">Add your whole list at once with Bulk order.</p>
        <a href="{{ url('/#bulk-order') }}" class="bk-btn bk-btn-primary mt-4">Start a bulk order</a>
    </div>
@else
    <table class="bk-table is-stack">
        <thead>
            <tr>
                <th scope="col">Order</th>
                <th scope="col">Placed</th>
                <th scope="col" class="is-num">Lines</th>
                <th scope="col">Status</th>
                <th scope="col" class="is-num">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orders as $order)
                <tr>
                    <td><a href="{{ route('account.order', $order->number) }}" class="bk-link">{{ $order->number }}</a></td>
                    <td data-label="Placed">{{ $order->created_at->format('d M Y') }}</td>
                    <td class="is-num" data-label="Lines">{{ $order->items->count() }} <span class="bk-muted">({{ $order->items->sum('qty') }} units)</span></td>
                    <td data-label="Status"><x-order-status :order="$order" /></td>
                    <td class="is-num font-bold" data-label="Total">{{ money($order->total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
