@php $certs = array_values(array_filter($g['certifications'] ?? [], fn ($c) => ! empty($c['label']))); @endphp
@if($certs)
<section class="h-container py-10 lg:py-[70px]">
    <h2 class="title-c">{{ $g['certifications_heading'] ?? 'Our Certifications' }}</h2>
    <div class="no-scrollbar mt-4 flex gap-3 overflow-x-auto pb-2 lg:mt-6 lg:flex-wrap lg:justify-center lg:gap-5 lg:overflow-visible">
        @foreach($certs as $c)
            @php $logo = \App\Support\Media::url($c['logo'] ?? null); @endphp
            <div class="cert-badge shrink-0 {{ $logo ? '' : 'is-text' }}">@if($logo)<img src="{{ $logo }}" alt="{{ $c['label'] }}" class="h-16 w-auto object-contain" loading="lazy">@else<span class="cert-ico"><x-ico name="badge" :size="20" /></span><span>{{ $c['label'] }}</span>@endif</div>
        @endforeach
    </div>
</section>
@endif
