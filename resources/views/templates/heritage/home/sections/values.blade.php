@php $items = array_values(array_filter($g['values_strip'] ?? [], fn ($v) => ! empty($v['label']))); @endphp
@if($items)
<section class="h-container pb-10 pt-[72px] lg:pb-[50px] lg:pt-10">
    <div class="rounded-[30px] bg-cream-dark px-4 pb-7 pt-3 lg:min-h-[130px] lg:px-10">
        <div class="-mt-11 grid grid-cols-3 gap-4 sm:grid-cols-6">
            @foreach(array_slice($items, 0, 6) as $v)
                <div class="flex flex-col items-center text-center">
                    <span class="icon-round"><x-ico :name="$v['icon'] ?? 'check'" :size="26" :stroke="1.4" /></span>
                    <p class="mt-2.5 text-xs font-medium text-ink sm:text-sm">{{ $v['label'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
