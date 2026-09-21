{{-- Service card: image, icon, name, short description, Book/Call actions. `compact` = rail card on phones. --}}
@props(['service', 'compact' => false])
@php $biz = template()->contact(); @endphp
<article {{ $attributes->class(['card card-hover flex flex-col', 'w-[15.5rem]' => $compact]) }}>
    <div class="relative">
        <a href="{{ $service->url }}" class="relative block aspect-[4/3] overflow-hidden rounded-t-[var(--radius-card)] bg-sky">
            @if($service->image_url)<img src="{{ $service->image_url }}" alt="{{ $service->name }}" loading="lazy" decoding="async" class="img-cover">@endif
            @if($service->is_emergency)<span class="pill pill-danger absolute left-3 top-3"><span class="pulse-dot inline-block h-1.5 w-1.5 rounded-full bg-current"></span> 24×7</span>
            @elseif($service->is_popular)<span class="pill pill-accent absolute left-3 top-3">Popular</span>@endif
        </a>
        {{-- Icon badge overlaps the image edge; kept outside the clipped link so it is never cut off --}}
        <span class="svc-ico absolute -bottom-4 right-3 z-[1] h-11 w-11 bg-white shadow-card ring-1 ring-line {{ $service->is_emergency ? 'text-danger' : '' }}"><x-ico :name="$service->icon ?: 'wrench'" :size="22" /></span>
    </div>
    <div class="flex flex-1 flex-col p-4 pt-5">
        <h3 class="font-display text-[0.95rem] font-extrabold leading-snug"><a href="{{ $service->url }}" class="hover:text-primary">{{ $service->name }}</a></h3>
        <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-slate">{{ $service->excerpt }}</p>
        <div class="mt-auto flex items-center gap-2 pt-3">
            <a href="{{ $service->book_url }}" class="btn btn-primary btn-sm flex-1 shadow-none">{{ $service->is_emergency ? 'Get help now' : 'Book now' }}</a>
            <a href="{{ $service->is_emergency ? $biz['emergency_href'] : $biz['phone_href'] }}" class="icon-btn bg-sky text-primary" aria-label="Call about {{ $service->name }}"><x-ico name="phone" :size="18" /></a>
        </div>
    </div>
</article>