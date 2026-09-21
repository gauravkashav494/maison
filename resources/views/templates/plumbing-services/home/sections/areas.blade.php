{{-- Service areas: location cards --}}
@if($areas->isNotEmpty())
<section class="section">
    <div class="ps-container">
        <x-section-head :title="$g['areas_heading'] ?? 'Plumbing services near you'" :sub="$g['areas_sub'] ?? null" :link="route('areas.index')" link-label="All areas" />
        <div class="rail rail-bleed no-scrollbar lg:hidden">@foreach($areas as $a)<x-area-card :area="$a" compact />@endforeach</div>
        <div class="hidden grid-cols-2 gap-4 lg:grid xl:grid-cols-4">@foreach($areas->take(8) as $a)<x-area-card :area="$a" />@endforeach</div>
    </div>
</section>
@endif