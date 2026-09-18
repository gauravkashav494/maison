@php $items = array_values(array_filter($g['promises'] ?? [], fn ($p) => ! empty($p['title']))); @endphp
@if($items)
<section class="g-container section">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach($items as $p)
            <div class="card flex gap-3 p-4">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-leaf-light text-leaf"><x-ico :name="$p['icon'] ?? 'check'" :size="22" /></span>
                <div><p class="text-sm font-extrabold">{{ $p['title'] }}</p><p class="mt-0.5 text-xs leading-relaxed text-slate">{{ $p['text'] ?? '' }}</p></div>
            </div>
        @endforeach
    </div>
</section>
@endif
