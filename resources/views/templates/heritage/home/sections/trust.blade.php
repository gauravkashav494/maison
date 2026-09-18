@php $items = array_values(array_filter($g['trust_items'] ?? [], fn ($t) => ! empty($t['title']))); @endphp
@if($items)
<section class="h-container section !pt-0">
    <x-ornament class="mb-8" />
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        @foreach($items as $t)
            <div class="flex flex-col items-center rounded-xl border border-line-soft bg-warm px-4 py-6 text-center">
                <span class="grid h-12 w-12 place-items-center rounded-full border border-gold bg-cream text-red"><x-ico :name="$t['icon'] ?? 'check'" :size="22" :stroke="1.5" /></span>
                <p class="mt-3 font-serif text-base font-semibold">{{ $t['title'] }}</p>
                <p class="mt-1 text-xs leading-relaxed text-muted">{{ $t['text'] ?? '' }}</p>
            </div>
        @endforeach
    </div>
</section>
@endif
