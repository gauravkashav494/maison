@extends('layouts.app')

@section('content')
@php $label = 'block text-[0.6875rem] uppercase tracking-[0.2em] text-smoke'; @endphp
<x-account-shell title="Saved addresses">
    <div x-data="{ editing: {{ $errors->any() ? (old('_editing') ?: 'null') : 'null' }}, adding: {{ $errors->any() && !old('_editing') ? 'true' : 'false' }} }">
        <div class="grid gap-6 sm:grid-cols-2">
            @foreach($addresses as $a)
                <div class="border border-ink/10 p-6">
                    <div x-show="editing !== {{ $a->id }}">
                        <div class="flex items-start justify-between gap-4">
                            <p class="font-serif text-xl">{{ $a->label ?: 'Address' }}</p>
                            @if($a->is_default)<span class="text-[0.5625rem] uppercase tracking-[0.2em] text-gold">Default</span>@endif
                        </div>
                        <div class="mt-3 text-sm leading-relaxed text-smoke">@foreach($a->lines() as $l)<p>{{ $l }}</p>@endforeach</div>
                        <div class="mt-5 flex gap-4 text-[0.625rem] uppercase tracking-[0.2em]">
                            <button type="button" @click="editing = {{ $a->id }}" class="link-underline">Edit</button>
                            <form method="post" action="{{ route('account.addresses.destroy', $a) }}" onsubmit="return confirm('Remove this address?')">@csrf @method('DELETE')<button type="submit" class="link-underline text-smoke hover:text-rouge">Remove</button></form>
                        </div>
                    </div>
                    <form x-show="editing === {{ $a->id }}" x-cloak method="post" action="{{ route('account.addresses.update', $a) }}" class="space-y-4">
                        @csrf @method('PUT') <input type="hidden" name="_editing" value="{{ $a->id }}">
                        @include('account.partials.address-fields', ['a' => $a])
                        <div class="flex gap-3"><button type="submit" class="btn btn-primary btn-sm">Save</button><button type="button" @click="editing = null" class="btn btn-outline btn-sm">Cancel</button></div>
                    </form>
                </div>
            @endforeach

            <div class="border border-dashed border-ink/20 p-6">
                <button type="button" x-show="!adding" @click="adding = true" class="flex h-full w-full flex-col items-center justify-center gap-2 py-6 text-sm text-smoke hover:text-ink"><x-ico name="plus" :size="20" /> Add a new address</button>
                <form x-show="adding" x-cloak method="post" action="{{ route('account.addresses.store') }}" class="space-y-4">
                    @csrf
                    <p class="font-serif text-xl">New address</p>
                    @include('account.partials.address-fields', ['a' => null])
                    <div class="flex gap-3"><button type="submit" class="btn btn-primary btn-sm">Save address</button><button type="button" @click="adding = false" class="btn btn-outline btn-sm">Cancel</button></div>
                </form>
            </div>
        </div>
    </div>
</x-account-shell>
@endsection
