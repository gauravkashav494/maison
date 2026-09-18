@extends('layouts.app')

@section('content')
<x-page-head :title="$page->title" :text="$page->excerpt" :breadcrumbs="['Legal' => null, $page->title => null]" />
<section class="h-container grid gap-6 py-6 lg:grid-cols-12">
    <div class="lg:col-span-8"><div class="card prose-h p-5 sm:p-8"><p class="!mt-0 text-xs text-muted">Last updated {{ $page->updated_at->format('d M Y') }}</p>{!! $page->body !!}</div></div>
    <aside class="lg:col-span-4">
        <div class="card p-5 lg:sticky lg:top-[7.5rem]" x-data="{ analytics: $store.cookies.prefs?.analytics ?? false, marketing: $store.cookies.prefs?.marketing ?? false, saved: false }">
            <p class="text-sm font-semibold">Your cookie preferences</p>
            <label class="mt-3 flex items-center justify-between gap-3 text-sm"><span><span class="block font-semibold">Essential</span><span class="text-xs text-muted">Cart, login and location — always on.</span></span><input type="checkbox" checked disabled class="check"></label>
            <label class="mt-3 flex items-center justify-between gap-3 text-sm"><span><span class="block font-semibold">Analytics</span><span class="text-xs text-muted">Helps us improve the store.</span></span><input type="checkbox" x-model="analytics" class="check"></label>
            <label class="mt-3 flex items-center justify-between gap-3 text-sm"><span><span class="block font-semibold">Marketing</span><span class="text-xs text-muted">Personalised offers.</span></span><input type="checkbox" x-model="marketing" class="check"></label>
            <button type="button" @click="$store.cookies.save(analytics, marketing); saved = true; setTimeout(() => saved = false, 2500)" class="btn btn-primary btn-block mt-4" x-text="saved ? 'Preferences saved' : 'Save preferences'"></button>
        </div>
    </aside>
</section>
@endsection
