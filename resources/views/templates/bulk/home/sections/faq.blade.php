@if($faqs->isNotEmpty())
<section class="p-container section">
    <x-section-head :title="$g['faq_heading'] ?? 'Frequently asked questions'" href="/faq" link="All questions" />
    <div class="card divide-y divide-line-soft" x-data="accordion(0)">
        @foreach($faqs as $i => $f)
            <div>
                <button type="button" @click="toggle({{ $i }})" class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left text-sm font-semibold" :aria-expanded="open === {{ $i }}">{{ $f->question }}<x-ico name="chevron-down" :size="18" class="shrink-0 text-slate transition-transform" ::class="open === {{ $i }} && 'rotate-180'" /></button>
                <div x-show="open === {{ $i }}" x-collapse x-cloak class="prose-p px-5 pb-5 text-sm">{!! $f->answer !!}</div>
            </div>
        @endforeach
    </div>
</section>
@endif
