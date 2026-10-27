{{-- The client this panel belongs to. It is fixed for the session: staff cannot switch client. --}}
@php($clients = app(\App\Clients\ClientContext::class))
@if($clients->client())
    <span class="fi-store-switcher fi-store-switcher-static" title="You manage this account only">
        <span class="fi-store-switcher-label">Managing</span>
        <strong>{{ $clients->client()->name }}</strong>
    </span>
@endif
