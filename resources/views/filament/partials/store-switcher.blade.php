{{-- Which store the admin is working in. Super admins can switch; store owners see their store only. --}}
@php
    $context = app(\App\Admin\StoreContext::class);
    $user = auth()->user();
@endphp
@if($user && $user->role)
    @if($user->isSuperAdmin())
        <form class="fi-store-switcher" method="get" action="{{ url()->current() }}">
            <label for="switch_store" class="fi-store-switcher-label">Managing</label>
            <select id="switch_store" name="switch_store" onchange="this.form.submit()" aria-label="Switch store">
                <option value="all" @selected(! $context->template())>All stores</option>
                @foreach(\App\Models\Storefront::orderBy('name')->get() as $store)
                    <option value="{{ $store->slug }}" @selected($context->template() === $store->template)>{{ $store->name }}</option>
                @endforeach
            </select>
        </form>
    @else
        <span class="fi-store-switcher fi-store-switcher-static" title="You manage this store only">
            <span class="fi-store-switcher-label">Managing</span>
            <strong>{{ $context->label() }}</strong>
        </span>
    @endif
@endif