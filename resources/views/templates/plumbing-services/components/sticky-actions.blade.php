{{-- Mobile sticky action bar just above the tab bar: [Call] [Book] (or [WhatsApp] [Book]) --}}
@props(['bookUrl' => null, 'bookLabel' => 'Book a plumber', 'emergency' => false, 'whatsapp' => false])
@php $biz = template()->contact(); @endphp
<div class="above-tabs sticky-cta lg:hidden" role="region" aria-label="Quick actions">
    @if($whatsapp && $biz['whatsapp'])
        <a href="{{ $biz['whatsapp_href'] }}" target="_blank" rel="noopener" class="btn btn-whatsapp"><x-ico name="whatsapp" :size="18" /> WhatsApp</a>
    @else
        <a href="{{ $emergency ? $biz['emergency_href'] : $biz['phone_href'] }}" class="btn {{ $emergency ? 'btn-danger' : 'btn-outline' }}"><x-ico name="phone" :size="18" /> Call now</a>
    @endif
    <a href="{{ $bookUrl ?? route('booking.create') }}" class="btn btn-primary">{{ $bookLabel }}</a>
</div>