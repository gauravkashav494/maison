{{-- Floating WhatsApp support button (desktop; phones have it in the menu and on product pages) --}}
@php $wa = tsetting('site.whatsapp_number'); @endphp
@if($wa)
<a href="https://wa.me/{{ preg_replace('/\D/', '', $wa) }}?text={{ rawurlencode('Hi, I need help choosing a product on '.(setting('site.name') ?? 'your store')) }}" target="_blank" rel="noopener" class="fixed bottom-6 right-6 z-40 hidden h-14 w-14 place-items-center rounded-full bg-whatsapp text-white shadow-float transition-transform hover:scale-105 lg:grid" aria-label="Chat on WhatsApp"><x-ico name="whatsapp" :size="28" /></a>
@endif
