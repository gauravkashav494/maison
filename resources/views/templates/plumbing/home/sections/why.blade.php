{{-- Why choose us: six trust points with icons --}}
@php $items = array_values(array_filter($g['why_items'] ?? [], fn ($x) => ! empty($x['title']))); @endphp
@if($items)
<section class="p-container section">
    <x-section-head :title="$g['why_heading'] ?? 'Why buy from us'" />
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6 lg:gap-4">
        @foreach(array_slice($items, 0, 6) as $x)
            <div class="card p-4 lg:p-5">
                <span class="trust-ico"><x-ico :name="$x['icon'] ?? 'check'" :size="22" /></span>
                <p class="mt-3 text-sm font-bold leading-tight">{{ $x['title'] }}</p>
                @if(!empty($x['text']))<p class="mt-1 text-xs leading-relaxed text-slate">{{ $x['text'] }}</p>@endif
            </div>
        @endforeach
    </div>
</section>
@endif
