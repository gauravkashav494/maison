@extends('layouts.app')

{{--
    Cookie policy: the policy text beside a panel for changing the choice made in the cookie
    banner. Saves through the shared consent store ($store.cookies), so it is honoured exactly
    as a choice made in the banner is. The banner links here.
--}}

@section('content')
<div class="bk-wrap mt-6">
    <x-page-head :title="$page->title" :text="$page->excerpt" :crumbs="[$page->title => null]" />

    <div class="mt-5 grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <article class="bk-card bk-prose p-5 sm:p-8">
            <p class="bk-muted !mt-0 text-xs">Last updated {{ $page->updated_at->format('d M Y') }}</p>
            {!! $page->body !!}
        </article>

        <aside class="bk-card p-5 lg:sticky lg:top-4"
               x-data="{ analytics: $store.cookies.prefs?.analytics ?? false, marketing: $store.cookies.prefs?.marketing ?? false, saved: false }">
            <p class="font-extrabold">Your cookie choices</p>
            <div class="mt-3 divide-y divide-[var(--bk-line)] rounded-lg border border-[var(--bk-line)] text-sm">
                <p class="flex items-center justify-between gap-3 px-3 py-2.5">
                    <span><span class="block font-semibold">Essential</span><span class="bk-muted text-xs">Your order, sign-in and saved lists.</span></span>
                    <span class="bk-badge bk-badge-ok">Always on</span>
                </p>
                <label class="flex cursor-pointer items-center justify-between gap-3 px-3 py-2.5">
                    <span><span class="block font-semibold">Analytics</span><span class="bk-muted text-xs">Helps us improve the store.</span></span>
                    <input type="checkbox" x-model="analytics" class="size-4 accent-[var(--bk-brand)]">
                </label>
                <label class="flex cursor-pointer items-center justify-between gap-3 px-3 py-2.5">
                    <span><span class="block font-semibold">Marketing</span><span class="bk-muted text-xs">Relevant offers on other sites.</span></span>
                    <input type="checkbox" x-model="marketing" class="size-4 accent-[var(--bk-brand)]">
                </label>
            </div>
            <button type="button" @click="$store.cookies.save(analytics, marketing); saved = true; setTimeout(() => saved = false, 2500)"
                    class="bk-btn bk-btn-primary bk-btn-block mt-4" x-text="saved ? 'Choices saved' : 'Save choices'"></button>
            <p class="bk-muted mt-4 text-xs">Questions? <a href="{{ url('/contact') }}" class="bk-link">Contact the trade desk</a>.</p>
        </aside>
    </div>
</div>
@endsection
