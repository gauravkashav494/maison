{{-- Contact options bottom sheet: Call · WhatsApp · Email · Book --}}
<div x-data x-show="$store.ui.contactOpen" x-cloak>
    <div class="overlay" x-show="$store.ui.contactOpen" x-transition.opacity @click="$store.ui.contactOpen = false"></div>
    <div class="sheet inset-x-0 bottom-0 mx-auto max-w-lg rounded-t-3xl lg:bottom-auto lg:top-1/2 lg:-translate-y-1/2 lg:rounded-3xl" x-show="$store.ui.contactOpen" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-full lg:translate-y-0 lg:opacity-0" x-transition:enter-end="translate-y-0 lg:opacity-100" x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-y-0 lg:opacity-100" x-transition:leave-end="translate-y-full lg:translate-y-0 lg:opacity-0" x-trap.noscroll="$store.ui.contactOpen" role="dialog" aria-modal="true" aria-label="Contact options">
        <div class="sheet-handle lg:hidden"></div>
        <div class="flex items-center justify-between px-5 pt-4">
            <div><p class="font-display text-lg font-extrabold">Talk to us</p><p class="text-xs text-slate">{{ $biz['hours'] }}@if($biz['emergency_available']) · Emergencies 24×7 @endif</p></div>
            <button type="button" @click="$store.ui.contactOpen = false" class="icon-btn" aria-label="Close"><x-ico name="close" :size="20" /></button>
        </div>
        <div class="safe-bottom space-y-2 p-4">
            <a href="{{ $biz['phone_href'] }}" class="option"><span class="svc-ico h-11 w-11"><x-ico name="phone" :size="20" /></span><span class="flex-1"><span class="block text-sm font-bold">Call now</span><span class="block text-xs text-slate">{{ $biz['phone'] }}</span></span><x-ico name="chevron-right" :size="18" class="text-mist" /></a>
            @if($biz['emergency_available'] && $biz['emergency_phone'] !== $biz['phone'])<a href="{{ $biz['emergency_href'] }}" class="option"><span class="svc-ico svc-ico-danger h-11 w-11"><x-ico name="alert" :size="20" /></span><span class="flex-1"><span class="block text-sm font-bold">Emergency line</span><span class="block text-xs text-slate">{{ $biz['emergency_phone'] }}</span></span><x-ico name="chevron-right" :size="18" class="text-mist" /></a>@endif
            @if($biz['whatsapp'])<a href="{{ $biz['whatsapp_href'] }}" target="_blank" rel="noopener" class="option"><span class="svc-ico svc-ico-whatsapp h-11 w-11"><x-ico name="whatsapp" :size="20" /></span><span class="flex-1"><span class="block text-sm font-bold">WhatsApp</span><span class="block text-xs text-slate">Send photos of the problem</span></span><x-ico name="chevron-right" :size="18" class="text-mist" /></a>@endif
            @if($biz['email'])<a href="{{ $biz['email_href'] }}" class="option"><span class="svc-ico h-11 w-11"><x-ico name="mail" :size="20" /></span><span class="flex-1"><span class="block text-sm font-bold">Email</span><span class="block text-xs text-slate">{{ $biz['email'] }}</span></span><x-ico name="chevron-right" :size="18" class="text-mist" /></a>@endif
            <a href="{{ route('booking.create') }}" class="btn btn-primary btn-block btn-lg mt-2">Book a plumber online</a>
        </div>
    </div>
</div>