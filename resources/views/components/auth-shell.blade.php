@props(["authQuote" => null])
@php $authImage = \App\Support\Media::url(setting('home.hero_image')); @endphp
<section class="grid min-h-[100svh] lg:grid-cols-2">
    <div class="relative hidden bg-ink lg:block">
        @if($authImage)<img src="{{ $authImage }}" alt="" class="img-cover absolute inset-0 opacity-80">@endif
        <div class="absolute inset-0 bg-gradient-to-t from-ink/80 via-ink/20 to-ink/30"></div>
        <div class="absolute bottom-14 left-14 right-14 text-ivory">
            <p class="eyebrow text-ivory/60">{{ setting('site.name') }}</p>
            <p class="display-md mt-4 max-w-md text-balance">{{ $authQuote ?? 'The pieces you reach for, again and again.' }}</p>
        </div>
    </div>
    <div class="flex items-center justify-center px-5 pb-24 pt-32 lg:px-16 lg:pt-36">
        <div class="w-full max-w-md">
            @if(session('status'))<p class="mb-6 border border-emerald-700/20 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</p>@endif
            {{ $slot }}
        </div>
    </div>
</section>
