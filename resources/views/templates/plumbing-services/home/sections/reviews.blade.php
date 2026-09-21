{{-- Customer reviews: swipeable cards on phones, 3-column grid on desktop --}}
@if($testimonials->isNotEmpty())
<section class="section bg-white">
    <div class="ps-container">
        <x-section-head :title="$g['reviews_heading'] ?? 'What our customers say'" eyebrow="Reviews">
        </x-section-head>
        <div class="rail rail-bleed no-scrollbar lg:hidden">@foreach($testimonials as $t)<x-review-card :review="$t" compact />@endforeach</div>
        <div class="hidden grid-cols-3 gap-5 lg:grid">@foreach($testimonials->take(6) as $t)<x-review-card :review="$t" />@endforeach</div>
    </div>
</section>
@endif