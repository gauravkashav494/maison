{{-- Emergency plumbing band: deep blue with red accent, Call + Get help. --}}
@php $biz = template()->contact(); @endphp
@if($biz['emergency_available'])
<section class="ps-container section pt-0 lg:pt-0">
    <div class="band-deep relative overflow-hidden rounded-3xl">
        <div class="absolute -right-10 -top-10 h-48 w-48 rounded-full bg-danger/40 blur-3xl"></div>
        <div class="absolute -bottom-16 left-1/3 h-48 w-48 rounded-full bg-bright/40 blur-3xl"></div>
        <div class="relative grid gap-6 p-5 lg:grid-cols-[1fr_auto] lg:items-center lg:p-10">
            <div>
                <p class="inline-flex items-center gap-2 rounded-full bg-danger px-3 py-1 text-xs font-extrabold uppercase tracking-wider text-white"><span class="pulse-dot inline-block h-1.5 w-1.5 rounded-full bg-white"></span> 24×7 emergency</p>
                <h2 class="mt-3 font-display text-2xl font-extrabold leading-tight text-balance lg:text-4xl">{{ $g['emergency_heading'] ?? 'Plumbing emergency?' }}</h2>
                <p class="mt-2 max-w-2xl text-sm text-white/85 lg:text-base">{{ $g['emergency_text'] ?? '' }}</p>
                @if(!empty($g['emergency_points']))
                    <ul class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm">
                        @foreach($g['emergency_points'] as $pt)<li class="flex items-center gap-2 text-white/90"><span class="grid h-5 w-5 place-items-center rounded-full bg-white/15"><x-ico name="check" :size="12" class="text-accent" /></span> {{ $pt }}</li>@endforeach
                    </ul>
                @endif
            </div>
            <div class="flex flex-col gap-2.5 sm:flex-row lg:flex-col lg:min-w-[15rem]">
                <a href="{{ $biz['emergency_href'] }}" class="btn btn-danger btn-lg"><x-ico name="phone" :size="18" /> Call {{ $biz['emergency_phone'] }}</a>
                <a href="{{ route('services.emergency') }}" class="btn btn-glass btn-lg">Get emergency help</a>
            </div>
        </div>
    </div>
</section>
@endif