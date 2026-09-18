@php $items = array_values(array_filter($g['values_strip'] ?? [], fn ($v) => ! empty($v['label']))); @endphp
@if($items)
<section class="h-container pt-4">
    <div class="rounded-2xl bg-cream-dark px-4 pb-6 pt-10 lg:px-10">
        <div class="-mt-16 grid grid-cols-3 gap-4 sm:grid-cols-6">
            @foreach(array_slice($items, 0, 6) as $v)
                <div class="flex flex-col items-center text-center">
                    <span class="icon-round"><x-ico :name="$v['icon'] ?? 'check'" :size="26" :stroke="1.4" /></span>
                    <p class="mt-3 text-xs font-medium text-ink sm:text-sm">{{ $v['label'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
