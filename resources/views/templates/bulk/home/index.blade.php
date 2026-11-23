@extends('layouts.app')

{{--
    Store front page: banner, then Bulk order (categories are in the header bar).
    No product lists: trade buyers come with a list, and reorders are served by the
    workspace's recent orders. $quickIndex and $recentOrders come from App\Templates\Bulk\HomeComposer.
--}}

@section('content')
<div class="bk-wrap mt-6 space-y-12">

    {{-- Banner --}}
    <section class="bk-hero">
        <div class="relative grid items-center gap-10 p-7 sm:p-10 lg:grid-cols-[1.35fr_1fr] lg:p-14">
            <div>
                @if($eyebrow = tsetting('site.hero_eyebrow'))<span class="bk-hero-eyebrow">{{ $eyebrow }}</span>@endif
                <h1 class="mt-4">{{ tsetting('site.hero_heading') }}</h1>
                <p class="mt-4 max-w-xl text-base leading-relaxed text-white/80">{{ tsetting('site.hero_text') }}</p>
                <div class="mt-7 flex flex-wrap gap-3">
                    <a href="#bulk-order" class="bk-btn bk-btn-accent !px-5 !py-3 text-base">
                        <x-heroicon-o-clipboard-document-list aria-hidden="true" />{{ tsetting('site.hero_cta_label', 'Start a bulk order') }}
                    </a>
                    <a href="{{ route('shop.index') }}" class="bk-btn !border-white/30 !px-5 !py-3 text-base text-white hover:!bg-white/10">
                        {{ tsetting('site.hero_secondary_label', 'Browse the catalogue') }}
                    </a>
                </div>
            </div>
            <div class="bk-crates hidden lg:grid" aria-hidden="true">
                <span class="bk-crate"><x-heroicon-o-beaker /></span>
                <span class="bk-crate is-accent"><x-heroicon-o-archive-box /></span>
                <span class="bk-crate"><x-heroicon-o-hand-raised /></span>
                <span class="bk-crate is-accent"><x-heroicon-o-document-text /></span>
                <span class="bk-crate"><x-heroicon-o-fire /></span>
                <span class="bk-crate"><x-heroicon-o-truck /></span>
            </div>
        </div>
    </section>

    {{-- Bulk order --}}
    <section id="bulk-order" class="scroll-mt-4">
        <div class="bk-section-head">
            <div>
                <h2>{{ tsetting('site.heading', 'Bulk order') }}</h2>
                <p>{{ tsetting('site.intro') }}</p>
            </div>
        </div>
        @include('partials.order-workspace')
    </section>
</div>
@endsection
