@if($posts->isNotEmpty())
@php $lead = $posts->first(); $second = $posts->skip(1)->first(); $rest = $posts->skip(2)->take(3); @endphp
<section class="h-container section !py-8 lg:!py-10">
    <h2 class="title-c">{{ $g['journal_heading'] ?? 'Blogs' }}</h2>
    <div class="mt-6 grid gap-6 lg:mt-8 lg:grid-cols-12">
        <a href="{{ $lead->url }}" class="group lg:col-span-4">
            <div class="banner-round aspect-[4/3] bg-cream-dark">@if($lead->image_url)<img src="{{ $lead->image_url }}" alt="" class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105" loading="lazy">@endif</div>
            <p class="mt-3 text-xs text-muted">{{ $lead->category ?: 'Journal' }}@if($lead->published_at) · {{ $lead->published_at->format('M d, Y') }}@endif</p>
            <h3 class="mt-1 font-serif text-lg font-semibold leading-snug group-hover:text-red">{{ $lead->title }}</h3>
            <p class="mt-1 line-clamp-3 text-sm text-muted">{{ $lead->excerpt }}</p>
        </a>
        @if($second)
            <a href="{{ $second->url }}" class="group lg:col-span-4">
                <div class="banner-round aspect-[16/10] bg-cream-dark">@if($second->image_url)<img src="{{ $second->image_url }}" alt="" class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105" loading="lazy">@endif</div>
                <p class="mt-3 text-xs text-muted">{{ $second->category ?: 'Journal' }}@if($second->published_at) · {{ $second->published_at->format('M d, Y') }}@endif</p>
                <h3 class="mt-1 font-serif text-lg font-semibold leading-snug group-hover:text-red">{{ $second->title }}</h3>
                <p class="mt-1 line-clamp-2 text-sm text-muted">{{ $second->excerpt }}</p>
            </a>
        @endif
        <div class="space-y-4 lg:col-span-4">
            @foreach($rest as $p)
                <a href="{{ $p->url }}" class="group flex gap-4">
                    <div class="banner-round h-24 w-32 shrink-0 bg-cream-dark">@if($p->image_url)<img src="{{ $p->image_url }}" alt="" class="h-full w-full object-cover" loading="lazy">@endif</div>
                    <div class="min-w-0"><p class="text-xs text-muted">{{ $p->category ?: 'Journal' }}@if($p->published_at) · {{ $p->published_at->format('M d, Y') }}@endif</p><h3 class="mt-1 line-clamp-2 text-sm font-semibold leading-snug group-hover:text-red">{{ $p->title }}</h3><p class="mt-1 line-clamp-1 text-xs text-muted">{{ $p->excerpt }}</p></div>
                </a>
            @endforeach
            @if($rest->isEmpty() && $second)<p class="text-sm text-muted">More stories coming soon.</p>@endif
        </div>
    </div>
    <p class="text-center"><a href="{{ route('journal.index') }}" class="view-all">View All</a></p>
</section>
@endif
