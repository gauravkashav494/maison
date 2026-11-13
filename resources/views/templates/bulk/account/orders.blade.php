@extends('layouts.app')

@section('content')
<x-account-shell title="My orders" text="Every order placed with this account. Open one to see its lines, codes and delivery.">
    <x-slot:actions>
        <a href="{{ url('/#bulk-order') }}" class="bk-btn bk-btn-outline"><x-heroicon-o-arrow-path aria-hidden="true" />Order again</a>
    </x-slot:actions>
    <section class="bk-card p-5">
        @include('account.partials.order-table', ['orders' => $orders])
    </section>
    @if($orders->hasPages())<div class="bk-pager mt-6">{{ $orders->links() }}</div>@endif
</x-account-shell>
@endsection
