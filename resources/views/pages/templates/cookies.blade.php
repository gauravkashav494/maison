@extends('layouts.app', ['transparentHeader' => filled(setting('site.page_header_image'))])

@section('content')
<x-page-hero :eyebrow="$page->eyebrow ?? 'Policies'" :title="$page->title" :description="$page->excerpt" :breadcrumbs="[$page->title => null]" />

<section class="container-luxe py-16 lg:py-24">
    <div class="mx-auto grid max-w-5xl gap-12 lg:grid-cols-12">
        <aside class="lg:col-span-4">
            <div class="lg:sticky lg:top-28" x-data="{ analytics: $store.cookies.prefs?.analytics ?? false, marketing: $store.cookies.prefs?.marketing ?? false, saved: false }">
                <p class="eyebrow text-taupe">Cookie preferences</p>
                <div class="mt-4 divide-y divide-ink/10 border border-ink/10 bg-cream">
                    <div class="flex items-center justify-between px-5 py-4"><div><p class="text-sm font-medium">Essential</p><p class="text-xs text-smoke">Always on</p></div><span class="text-[0.625rem] uppercase tracking-[0.2em] text-taupe">Required</span></div>
                    <label class="flex cursor-pointer items-center justify-between px-5 py-4"><div><p class="text-sm font-medium">Analytics</p><p class="text-xs text-smoke">Anonymised usage data</p></div><input type="checkbox" x-model="analytics" class="h-4 w-4 accent-ink"></label>
                    <label class="flex cursor-pointer items-center justify-between px-5 py-4"><div><p class="text-sm font-medium">Marketing</p><p class="text-xs text-smoke">Campaign measurement</p></div><input type="checkbox" x-model="marketing" class="h-4 w-4 accent-ink"></label>
                </div>
                <button type="button" @click="$store.cookies.save(analytics, marketing); saved = true; setTimeout(() => saved = false, 2500)" class="btn btn-primary mt-4 w-full" x-text="saved ? 'Preferences saved' : 'Save preferences'"></button>
                <p class="mt-3 text-xs text-smoke">Last updated {{ $page->updated_at->format('d F Y') }}</p>
            </div>
        </aside>
        <div class="prose-luxe lg:col-span-8">{!! $page->body !!}</div>
    </div>
</section>
@endsection
