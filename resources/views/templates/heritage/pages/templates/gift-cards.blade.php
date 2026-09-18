@extends('layouts.app')

@section('content')
@php $steps = $page->data['steps'] ?? []; @endphp
<x-page-head :title="$page->title" :text="$page->excerpt" :breadcrumbs="[$page->title => null]" />
<section class="h-container grid gap-6 py-6 lg:grid-cols-12">
    <div class="lg:col-span-7">
        @if($page->image_url)<img src="{{ $page->image_url }}" alt="" class="mb-6 aspect-[16/8] w-full rounded-2xl object-cover">@endif
        @if($page->body)<div class="card prose-h p-5 text-sm">{!! $page->body !!}</div>@endif
        @if($steps)
            <ol class="mt-4 grid gap-3 sm:grid-cols-3">
                @foreach($steps as $i => $s)<li class="card p-4"><span class="step-num">{{ $i + 1 }}</span><p class="mt-3 text-sm font-semibold">{{ $s['title'] }}</p><p class="mt-1 text-xs leading-relaxed text-muted">{{ $s['text'] }}</p></li>@endforeach
            </ol>
        @endif
    </div>
    <div class="lg:col-span-5">
        @if($giftCard)
            <div class="card p-5 lg:sticky lg:top-[7.5rem]" x-data="{ amount: @js($giftCard->sizes[1] ?? $giftCard->sizes[0] ?? null) }">
                <p class="text-lg font-semibold">{{ $giftCard->name }}</p>
                <p class="mt-1 text-sm text-muted">{{ $giftCard->description }}</p>
                <p class="label mt-4">Choose an amount</p>
                <div class="grid grid-cols-3 gap-2">
                    @foreach($giftCard->sizes as $s)<button type="button" @click="amount = @js($s)" class="chip justify-center !py-2.5" :class="amount === @js($s) && 'chip-active'">{{ $s }}</button>@endforeach
                </div>
                <button type="button" @click="$store.cart.add({{ $giftCard->id }}, amount, null, 1, true)" class="btn btn-primary btn-lg btn-block mt-4">Add gift card <span x-text="amount"></span></button>
                <p class="mt-3 text-xs text-muted">Delivered by email within minutes. Valid for 12 months on every product.</p>
            </div>
        @else
            <x-empty-state icon="gift" title="Gift cards coming soon" />
        @endif
    </div>
</section>
@endsection
