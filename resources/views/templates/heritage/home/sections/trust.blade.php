@php $items = array_values(array_filter($g['trust_items'] ?? [], fn ($t) => ! empty($t['title']))); $img = \App\Support\Media::url($g['trust_image'] ?? null); @endphp
@if($items)
<section class="mt-6 bg-red text-cream lg:mt-8">
    <div class="grid lg:grid-cols-12">
        <div class="relative min-h-[16rem] lg:col-span-5 lg:min-h-[22rem]">
            @if($img)<img src="{{ $img }}" alt="" class="absolute inset-0 h-full w-full object-cover lg:[clip-path:ellipse(100%_100%_at_0%_50%)]" loading="lazy">@endif
        </div>
        <div class="px-6 py-10 lg:col-span-7 lg:px-14 lg:py-14">
            @if(!empty($g['trust_heading']))<h2 class="font-serif text-2xl font-semibold text-gold-light lg:text-3xl">{{ $g['trust_heading'] }}</h2>@endif
            <div class="mt-8 grid grid-cols-2 gap-x-6 gap-y-8 sm:grid-cols-3">
                @foreach(array_slice($items, 0, 6) as $t)
                    <div class="flex flex-col items-center text-center">
                        <span class="grid h-16 w-16 place-items-center rounded-full bg-maroon-deep text-gold-light ring-2 ring-gold/60"><x-ico :name="$t['icon'] ?? 'check'" :size="26" :stroke="1.5" /></span>
                        <p class="mt-3 text-sm font-semibold">{{ $t['title'] }}</p>
                        @if(!empty($t['text']))<p class="mt-0.5 text-xs text-cream/75">{{ $t['text'] }}</p>@endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
@endif
