{{-- Floating WhatsApp button (desktop only — phones have it in the tab bar / sticky CTA) --}}
@if($biz['whatsapp'])
<a href="{{ $biz['whatsapp_href'] }}" target="_blank" rel="noopener" class="fixed bottom-6 right-6 z-30 hidden h-14 w-14 place-items-center rounded-full bg-whatsapp text-white shadow-float transition hover:scale-105 lg:grid" aria-label="Chat on WhatsApp"><x-ico name="whatsapp" :size="28" /></a>
@endif