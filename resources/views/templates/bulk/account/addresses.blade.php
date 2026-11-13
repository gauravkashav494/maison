@extends('layouts.app')

@section('content')
<x-account-shell title="Delivery addresses" text="Saved addresses are offered at checkout.">
    <div x-data="{ editing: {{ $errors->any() ? (old('_editing') ?: 'null') : 'null' }}, adding: {{ $errors->any() && ! old('_editing') ? 'true' : 'false' }} }">
        <div class="grid items-start gap-4 sm:grid-cols-2">
            @foreach($addresses as $a)
                <div class="bk-card p-5">
                    <div x-show="editing !== {{ $a->id }}">
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-extrabold">{{ $a->label ?: 'Address' }}</p>
                            @if($a->is_default)<span class="bk-badge bk-badge-new">Default</span>@endif
                        </div>
                        <div class="bk-muted mt-2 text-sm leading-relaxed">@foreach($a->lines() as $line)<p>{{ $line }}</p>@endforeach</div>
                        <div class="mt-4 flex items-center gap-2">
                            <button type="button" @click="editing = {{ $a->id }}" class="bk-btn bk-btn-outline !px-3 !py-1.5 text-sm"><x-heroicon-o-pencil-square aria-hidden="true" />Edit</button>
                            <form method="post" action="{{ route('account.addresses.destroy', $a) }}" onsubmit="return confirm('Remove this address?')" novalidate>
                                @csrf @method('DELETE')
                                <button type="submit" class="bk-btn bk-btn-ghost !px-3 !py-1.5 text-sm text-[var(--bk-out)]"><x-heroicon-o-trash aria-hidden="true" />Remove</button>
                            </form>
                        </div>
                    </div>
                    <form x-show="editing === {{ $a->id }}" x-cloak method="post" action="{{ route('account.addresses.update', $a) }}" class="space-y-4" novalidate>
                        @csrf @method('PUT')
                        <input type="hidden" name="_editing" value="{{ $a->id }}">
                        <p class="font-extrabold">Edit address</p>
                        @include('account.partials.address-fields', ['a' => $a])
                        <div class="flex gap-2"><button type="submit" class="bk-btn bk-btn-primary">Save</button><button type="button" @click="editing = null" class="bk-btn bk-btn-ghost">Cancel</button></div>
                    </form>
                </div>
            @endforeach

            <div class="bk-card border-dashed p-5" :class="adding && 'sm:col-span-2'">
                <button type="button" x-show="!adding" @click="adding = true" class="flex w-full flex-col items-center justify-center gap-2 py-6 text-sm font-bold text-brand">
                    <x-heroicon-o-plus class="size-6" aria-hidden="true" />Add an address
                </button>
                <form x-show="adding" x-cloak method="post" action="{{ route('account.addresses.store') }}" class="space-y-4" novalidate>
                    @csrf
                    <p class="font-extrabold">New address</p>
                    @include('account.partials.address-fields', ['a' => null])
                    <div class="flex gap-2"><button type="submit" class="bk-btn bk-btn-primary">Save address</button><button type="button" @click="adding = false" class="bk-btn bk-btn-ghost">Cancel</button></div>
                </form>
            </div>
        </div>
    </div>
</x-account-shell>
@endsection
