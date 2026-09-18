@php $certs = array_values(array_filter($g['certifications'] ?? [], fn ($c) => ! empty($c['label']))); @endphp
@if($certs)
<section class="h-container section !py-8 lg:!py-10">
    <h2 class="title-c">{{ $g['certifications_heading'] ?? 'Our Certifications' }}</h2>
    <div class="no-scrollbar mt-6 flex gap-3 overflow-x-auto pb-2 lg:justify-center">
        @foreach($certs as $c)
            @php $logo = \App\Support\Media::url($c['logo'] ?? null); @endphp
            <div class="cert-badge shrink-0">@if($logo)<img src="{{ $logo }}" alt="{{ $c['label'] }}" class="h-10 w-auto object-contain" loading="lazy">@else<span class="flex items-center gap-2"><x-ico name="badge" :size="18" class="text-gold" />{{ $c['label'] }}</span>@endif</div>
        @endforeach
    </div>
</section>
@endif
