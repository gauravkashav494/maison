{{-- App-style quick actions (phones/tablets only): Emergency · Book · Quote · WhatsApp --}}
@php $biz = template()->contact(); $actions = collect($g['quick_actions'] ?? [])->filter(fn ($a) => ! empty($a['label']))->take(4); @endphp
@if($actions->isNotEmpty())
<section class="ps-container mt-3 lg:hidden" aria-label="Quick actions">
    <div class="grid grid-cols-4 gap-2">
        @foreach($actions as $a)
            @php
                $href = match ($a['action'] ?? '') { 'call' => $biz['phone_href'], 'whatsapp' => $biz['whatsapp_href'], default => $a['url'] ?: route('booking.create') };
                $tone = match ($a['tone'] ?? 'primary') { 'danger' => 'svc-ico-danger', 'accent' => 'svc-ico-accent', 'whatsapp' => 'svc-ico-whatsapp', default => '' };
            @endphp
            @if(($a['action'] ?? '') !== 'whatsapp' || $biz['whatsapp'])
                <a href="{{ $href }}" class="quick-tile" @if(($a['action'] ?? '') === 'whatsapp') target="_blank" rel="noopener" @endif>
                    <span class="svc-ico {{ $tone }}"><x-ico :name="$a['icon'] ?? 'wrench'" :size="22" /></span>
                    <span class="leading-tight">{{ $a['label'] }}</span>
                </a>
            @endif
        @endforeach
    </div>
    @if($biz['response'])<p class="mt-2.5 flex items-center justify-center gap-1.5 text-center text-xs font-semibold text-slate"><x-ico name="bolt" :size="13" class="text-accent" /> {{ $biz['response'] }}</p>@endif
</section>
@endif