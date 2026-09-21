@if($faqs->isNotEmpty())
<section class="section bg-white">
    <div class="ps-container lg:grid lg:grid-cols-12 lg:gap-10">
        <div class="lg:col-span-4">
            <x-section-head :title="$g['faq_heading'] ?? 'Frequently asked questions'" eyebrow="Good to know" />
            <p class="-mt-2 mb-4 text-sm text-slate lg:mb-0">Response times, estimates, warranty and payments. <a href="/faq" class="font-bold text-primary">All FAQs</a></p>
        </div>
        <div class="lg:col-span-8"><x-faq-list :faqs="$faqs" /></div>
    </div>
</section>
@endif