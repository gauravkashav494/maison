@extends('layouts.app', ['transparentHeader' => filled($page->image_url ?: setting('site.page_header_image'))])

@section('content')
<x-page-hero :eyebrow="$page->eyebrow" :title="$page->title" :description="$page->excerpt" :image="$page->image_url" :breadcrumbs="[$page->title => null]" />

<section class="container-luxe py-16 lg:py-24">
    <div class="grid gap-14 lg:grid-cols-12 lg:gap-20">
        <div class="lg:col-span-6">
            @if($giftCard)
                <div x-data="{ amount: @js($giftCard->sizes[1] ?? $giftCard->sizes[0] ?? null), qty: 1 }" class="border border-ink/10 bg-cream p-8">
                    <p class="eyebrow text-taupe">Digital gift card</p>
                    <h2 class="mt-3 font-serif text-3xl">Choose a value</h2>
                    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach($giftCard->sizes as $s)
                            <button type="button" @click="amount = @js($s)" :class="amount === @js($s) ? 'border-ink bg-ink text-ivory' : 'border-ink/20 hover:border-ink'" class="border py-4 font-serif text-xl transition-colors">{{ $s }}</button>
                        @endforeach
                    </div>
                    <div class="mt-6 flex gap-3">
                        <div class="flex h-12 items-center border border-ink/20"><button type="button" @click="qty = Math.max(1, qty - 1)" class="grid h-full w-11 place-items-center" aria-label="Decrease"><x-ico name="minus" :size="14" /></button><span class="w-8 text-center text-sm" x-text="qty"></span><button type="button" @click="qty++" class="grid h-full w-11 place-items-center" aria-label="Increase"><x-ico name="plus" :size="14" /></button></div>
                        <button type="button" @click="$store.cart.add({{ $giftCard->id }}, amount, null, qty)" :disabled="!amount" class="btn btn-primary flex-1">Add to Bag</button>
                    </div>
                    <p class="mt-4 text-xs text-smoke">Add a personal message in the order notes at checkout. Cards are sent by email within the hour and never expire.</p>
                </div>
            @endif
            @if($page->body)<div class="prose-luxe mt-10">{!! $page->body !!}</div>@endif
        </div>
        <div class="lg:col-span-5 lg:col-start-8">
            <p class="eyebrow text-taupe">How it works</p>
            <ol class="mt-6 divide-y divide-ink/10 border-y border-ink/10">
                @foreach($page->data['steps'] ?? [] as $i => $s)
                    <li class="flex gap-6 py-6"><span class="font-serif text-3xl text-taupe">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span><div><h3 class="font-serif text-xl">{{ $s['title'] }}</h3><p class="mt-1 text-sm leading-relaxed text-smoke">{{ $s['text'] }}</p></div></li>
                @endforeach
            </ol>
            <div class="mt-10 text-sm text-smoke">
                <p class="eyebrow mb-2 text-taupe">Good to know</p>
                <ul class="space-y-1.5"><li>Redeemable online and in our boutiques</li><li>Can be used across several orders</li><li>Not exchangeable for cash</li></ul>
            </div>
        </div>
    </div>
</section>
@endsection
