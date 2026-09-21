{{-- Tips & guides --}}
@if($posts->isNotEmpty())
<section class="section">
    <div class="ps-container">
        <x-section-head :title="$g['blog_heading'] ?? 'Tips & guides'" :link="route('journal.index')" link-label="All guides" />
        <div class="rail rail-bleed no-scrollbar lg:grid lg:grid-cols-4 lg:gap-5 lg:overflow-visible lg:p-0 lg:mx-0">
            @foreach($posts->take(4) as $post)
                <a href="{{ $post->url }}" class="post-card card card-hover w-[15rem] overflow-hidden lg:w-auto">
                    <span class="block aspect-[16/10] overflow-hidden bg-sky">@if($post->image_url)<img src="{{ $post->image_url }}" alt="" loading="lazy" decoding="async" class="img-cover">@endif</span>
                    <span class="block p-4">
                        <span class="pill pill-sky">{{ $post->category }}</span>
                        <span class="mt-2 block font-display text-sm font-extrabold leading-snug line-clamp-2">{{ $post->title }}</span>
                        <span class="mt-1.5 block text-xs text-slate">{{ $post->read_time ? $post->read_time.' min read' : $post->published_at?->format('d M Y') }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif