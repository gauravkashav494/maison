@php $promises = $home['promises'] ?? []; $n = count($promises); @endphp
@if($n)
<section class="border-y border-ink/10 bg-cream">
    <div class="container-luxe">
        <div class="grid grid-cols-1 divide-y divide-ink/10 sm:-mx-6 sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:-mx-8 lg:grid-cols-{{ $n }}">
            @foreach($promises as $i => $p)
                <div class="reveal flex flex-col gap-4 px-0 py-9 sm:px-6 lg:px-8 lg:py-12 {{ ($n % 2 === 1 && $i === $n - 1) ? 'sm:col-span-2 sm:border-t sm:border-ink/10 lg:col-span-1 lg:border-t-0' : '' }}"
                     style="--reveal-delay: {{ $i * 0.06 }}s" x-data x-intersect.once="$el.classList.add('is-visible')">
                    <x-ico :name="$p['icon'] ?? 'badge-check'" :size="22" :stroke="1" class="text-ink" />
                    <div>
                        <h3 class="text-[0.75rem] font-medium uppercase tracking-[0.18em]">{{ $p['title'] ?? '' }}</h3>
                        <p class="mt-2 text-[0.8125rem] leading-relaxed text-smoke">{{ $p['text'] ?? '' }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
