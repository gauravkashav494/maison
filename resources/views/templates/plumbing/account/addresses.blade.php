@extends('layouts.app')

@section('content')
<x-account-shell title="Saved addresses">
    <div x-data="{ editing: {{ $errors->any() ? (old('_editing') ?: 'null') : 'null' }}, adding: {{ $errors->any() && !old('_editing') ? 'true' : 'false' }} }">
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach($addresses as $a)
                <div class="card p-4">
                    <div x-show="editing !== {{ $a->id }}">
                        <div class="flex items-start justify-between gap-3">
                            <p class="flex items-center gap-2 text-sm font-extrabold"><x-ico name="pin" :size="16" class="text-primary" /> {{ $a->label ?: 'Address' }}</p>
                            @if($a->is_default)<span class="badge badge-out">Default</span>@endif
                        </div>
                        <div class="mt-2 text-sm leading-relaxed text-slate">@foreach($a->lines() as $l)<p>{{ $l }}</p>@endforeach</div>
                        <div class="mt-3 flex gap-2">
                            <button type="button" @click="editing = {{ $a->id }}" class="btn btn-ghost btn-sm"><x-ico name="edit" :size="14" /> Edit</button>
                            <form method="post" action="{{ route('account.addresses.destroy', $a) }}" onsubmit="return confirm('Remove this address?')">@csrf @method('DELETE')<button type="submit" class="btn btn-ghost btn-sm text-danger"><x-ico name="trash" :size="14" /> Remove</button></form>
                        </div>
                    </div>
                    <form x-show="editing === {{ $a->id }}" x-cloak method="post" action="{{ route('account.addresses.update', $a) }}" class="space-y-3">
                        @csrf @method('PUT') <input type="hidden" name="_editing" value="{{ $a->id }}">
                        @include('account.partials.address-fields', ['a' => $a])
                        <div class="flex gap-2"><button type="submit" class="btn btn-primary btn-sm">Save</button><button type="button" @click="editing = null" class="btn btn-ghost btn-sm">Cancel</button></div>
                    </form>
                </div>
            @endforeach

            <div class="card border-dashed p-4">
                <button type="button" x-show="!adding" @click="adding = true" class="flex h-full w-full flex-col items-center justify-center gap-2 py-6 text-sm font-semibold text-slate hover:text-primary"><span class="grid h-10 w-10 place-items-center rounded-full bg-sky text-primary"><x-ico name="plus" :size="20" /></span> Add a new address</button>
                <form x-show="adding" x-cloak method="post" action="{{ route('account.addresses.store') }}" class="space-y-3">
                    @csrf
                    <p class="text-sm font-extrabold">New address</p>
                    @include('account.partials.address-fields', ['a' => null])
                    <div class="flex gap-2"><button type="submit" class="btn btn-primary btn-sm">Save address</button><button type="button" @click="adding = false" class="btn btn-ghost btn-sm">Cancel</button></div>
                </form>
            </div>
        </div>
    </div>
</x-account-shell>
@endsection
