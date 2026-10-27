{{-- Which client the platform operator is working in. A client's own staff never see this. --}}
@php
    $clients = app(\App\Clients\ClientContext::class);
    $isolated = app(\App\Clients\ClientDatabase::class)->isolated();
@endphp
@if($clients->isSuperAdmin())
    <form class="fi-store-switcher" method="get" action="{{ url()->current() }}">
        <label for="switch_client" class="fi-store-switcher-label">Managing</label>
        <select id="switch_client" name="switch_client" onchange="this.form.submit()" aria-label="Switch client">
            @unless($isolated)<option value="all" @selected(! $clients->id())>All clients</option>@endunless
            @foreach(\App\Models\Client::with('storefront')->orderBy('name')->get() as $client)
                <option value="{{ $client->id }}" @selected($clients->id() === $client->id)>{{ $client->name }}@if($client->storefront) · {{ $client->storefront->name }}@endif</option>
            @endforeach
        </select>
    </form>
@endif
