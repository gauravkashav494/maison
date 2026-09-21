{{-- Popular services: horizontal rail on phones, 4-column grid on desktop --}}
@if($popular->isNotEmpty())
<section class="section" id="services">
    <div class="ps-container">
        <x-section-head :title="$g['popular_heading'] ?? 'Popular plumbing services'" :sub="$g['popular_sub'] ?? null" :link="route('services.index')" link-label="All services" />
    </div>
    <div class="ps-container lg:hidden"><div class="rail rail-bleed no-scrollbar">@foreach($popular as $s)<x-service-card :service="$s" compact />@endforeach</div></div>
    <div class="ps-container hidden lg:block" x-data="rail()">
        <div class="grid grid-cols-2 gap-5 md:grid-cols-3 xl:grid-cols-4">@foreach($popular->take(8) as $s)<x-service-card :service="$s" />@endforeach</div>
    </div>
</section>
@endif