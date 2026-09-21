{{-- Shop for your project: application-led cards (Bathroom, Kitchen, Water supply, …) --}}
@php $projects = array_values(array_filter($g['projects'] ?? [], fn ($x) => ! empty($x['title']))); @endphp
@if($projects)
<section class="bg-white section">
    <div class="p-container">
        <x-section-head :title="$g['projects_heading'] ?? 'Shop for your project'" :text="$g['projects_text'] ?? null" />
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 lg:gap-4">
            @foreach($projects as $x)
                <a href="{{ $x['url'] ?? '/shop' }}" class="group relative block aspect-[4/3] overflow-hidden rounded-2xl bg-deep">
                    @if(!empty($x['image']))<img src="{{ \App\Support\Media::url($x['image']) }}" alt="" class="img-cover opacity-90 transition-transform duration-500 group-hover:scale-105" loading="lazy">@endif
                    <span class="absolute inset-0 bg-gradient-to-t from-deep via-deep/40 to-transparent"></span>
                    <span class="absolute inset-x-0 bottom-0 p-4 text-white">
                        <span class="block font-display text-base font-bold leading-tight lg:text-lg">{{ $x['title'] }}</span>
                        @if(!empty($x['text']))<span class="mt-1 hidden text-xs text-white/80 sm:block">{{ $x['text'] }}</span>@endif
                        <span class="mt-2 inline-flex items-center gap-1 text-xs font-bold text-accent">Shop now <x-ico name="arrow-right" :size="14" /></span>
                    </span>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif
