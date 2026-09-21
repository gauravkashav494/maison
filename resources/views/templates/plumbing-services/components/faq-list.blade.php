{{-- Accordion (native <details> for accessibility and zero JS) --}}
@props(['faqs'])
<div {{ $attributes->class('space-y-2.5') }}>
    @foreach($faqs as $i => $f)
        @php $q = is_array($f) ? $f['question'] : $f->question; $a = is_array($f) ? $f['answer'] : $f->answer; @endphp
        <details class="faq-item" @if($i === 0) open @endif>
            <summary class="flex items-center justify-between gap-4 px-4 py-3.5 lg:px-5 lg:py-4">
                <span class="font-display text-[0.95rem] font-bold leading-snug">{{ $q }}</span>
                <span class="faq-chev grid h-7 w-7 shrink-0 place-items-center rounded-full bg-sky text-primary transition-transform"><x-ico name="chevron-down" :size="16" /></span>
            </summary>
            <div class="prose-p px-4 pb-4 text-sm lg:px-5 lg:pb-5">{!! nl2br(e(strip_tags($a))) !!}</div>
        </details>
    @endforeach
</div>