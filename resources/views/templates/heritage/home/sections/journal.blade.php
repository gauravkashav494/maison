@if($posts->isNotEmpty())
@php $lead = $posts->first(); $second = $posts->skip(1)->first(); $rest = $posts->skip(2)->take(3); @endphp
<section class="h-container pt-10 lg:pt-0">
    <h2 class="title-c lg:leading-none">{{ $g['journal_heading'] ?? 'Blogs' }}</h2>
    <div class="mt-6 grid gap-5 lg:mt-[30px] lg:grid-cols-[359fr_359fr_543fr]">
        <a href="{{ $lead->url }}" class="group">
            <div class="overflow-hidden rounded-[20px] bg-cream-dark aspect-[4/3] lg:aspect-[359/279]">@if($lead->image_url)<img src="{{ $lead->image_url }}" alt="" class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105" loading="lazy">@endif</div>
            <p class="mt-2.5 text-sm leading-[25px] text-ink">{{ $lead->category ?: 'Journal' }}</p>
            <h3 class="mt-[5px] flex items-start justify-between gap-2 font-sans text-[15px] font-bold leading-snug group-hover:text-red">{{ $lead->title }}@if($lead->published_at)<span class="shrink-0 text-[13px] font-normal text-muted">{{ $lead->published_at->format('M d, Y') }}</span>@endif</h3>
            <p class="mt-[7px] line-clamp-5 text-[15px] leading-[25px] text-ink">{{ $lead->excerpt }}</p>
        </a>
        @if($second)
            <a href="{{ $second->url }}" class="group">
                <div class="overflow-hidden rounded-[20px] bg-cream-dark aspect-[16/10] lg:aspect-[359/196]">@if($second->image_url)<img src="{{ $second->image_url }}" alt="" class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105" loading="lazy">@endif</div>
                <p class="mt-2.5 text-sm leading-[25px] text-ink">{{ $second->category ?: 'Journal' }}</p>
                <h3 class="mt-[5px] flex items-start justify-between gap-2 font-sans text-[15px] font-bold leading-snug group-hover:text-red">{{ $second->title }}@if($second->published_at)<span class="shrink-0 text-[13px] font-normal text-muted">{{ $second->published_at->format('M d, Y') }}</span>@endif</h3>
                <p class="mt-[7px] line-clamp-3 text-[15px] leading-[25px] text-ink">{{ $second->excerpt }}</p>
            </a>
        @endif
        <div class="space-y-2.5">
            @foreach($rest as $p)
                <a href="{{ $p->url }}" class="group flex gap-2.5">
                    <div class="h-24 w-32 shrink-0 overflow-hidden rounded-[20px] bg-cream-dark lg:h-[169px] lg:w-[217px]">@if($p->image_url)<img src="{{ $p->image_url }}" alt="" class="h-full w-full object-cover" loading="lazy">@endif</div>
                    <div class="min-w-0 px-[5px] py-2.5"><p class="text-sm text-ink">{{ $p->category ?: 'Journal' }}@if($p->published_at) <span class="text-[13px] text-muted">· {{ $p->published_at->format('M d, Y') }}</span>@endif</p><h3 class="mt-1 line-clamp-2 font-sans text-[15px] font-bold leading-snug group-hover:text-red">{{ $p->title }}</h3><p class="mt-1 line-clamp-2 text-[15px] leading-[25px] text-ink">{{ $p->excerpt }}</p></div>
                </a>
            @endforeach
            @if($rest->isEmpty() && $second)<p class="text-sm text-muted">More stories coming soon.</p>@endif
        </div>
    </div>
    <p class="pt-5 text-center"><a href="{{ route('journal.index') }}" class="view-all">View All</a></p>
</section>
@endif
