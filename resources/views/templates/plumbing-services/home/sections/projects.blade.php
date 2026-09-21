{{-- Recent work: before/after cards --}}
@if($projects->isNotEmpty())
<section class="section">
    <div class="ps-container">
        <x-section-head :title="$g['projects_heading'] ?? 'Recent plumbing work'" :sub="$g['projects_sub'] ?? null" :link="route('projects.index')" link-label="See all work" />
        <div class="rail rail-bleed no-scrollbar lg:hidden">@foreach($projects as $p)<x-project-card :project="$p" compact />@endforeach</div>
        <div class="hidden grid-cols-3 gap-5 lg:grid">@foreach($projects->take(6) as $p)<x-project-card :project="$p" />@endforeach</div>
    </div>
</section>
@endif