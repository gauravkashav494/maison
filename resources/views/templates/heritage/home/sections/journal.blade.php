@if($posts->isNotEmpty())
<section class="h-container section">
    <x-section-head :eyebrow="$g['journal_eyebrow'] ?? 'Recipes & stories'" :title="$g['journal_heading'] ?? 'From our kitchen journal'" :href="route('journal.index')" label="All stories" />
    <div class="grid gap-4 md:grid-cols-3">
        @foreach($posts as $post)
            <a href="{{ $post->url }}" class="card card-hover group overflow-hidden">
                <div class="aspect-[16/10] overflow-hidden bg-cream">@if($post->image_url)<img src="{{ $post->image_url }}" alt="" class="img-cover transition-transform duration-700 group-hover:scale-105" loading="lazy">@endif</div>
                <div class="p-5">
                    <p class="flex items-center gap-2 text-xs text-muted">@if($post->category)<span class="badge badge-soft">{{ $post->category }}</span>@endif @if($post->read_time)<span>{{ $post->read_time }} min read</span>@endif</p>
                    <h3 class="mt-3 font-serif text-xl font-semibold leading-snug group-hover:text-red">{{ $post->title }}</h3>
                    <p class="mt-2 line-clamp-2 text-sm text-muted">{{ $post->excerpt }}</p>
                </div>
            </a>
        @endforeach
    </div>
</section>
@endif
