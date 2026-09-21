{{-- Footer: business card (contact, hours, emergency), four link columns, trust line and policies --}}
@php $p = tsetting('site'); $areas = $navAreas ?? collect(); @endphp
<footer class="mt-8 bg-white lg:mt-16">
    <div class="border-y border-line bg-canvas">
        <div class="ps-container grid gap-8 py-10 lg:grid-cols-12 lg:py-14">
            <div class="lg:col-span-4">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-primary text-white"><x-ico name="droplet" :size="22" :stroke="2" /></span>
                    <span class="font-display text-xl font-extrabold text-deep">{{ $p['logo_primary'] ?? 'Pipe' }}<span class="text-bright">{{ $p['logo_accent'] ?? 'Care' }}</span></span>
                </a>
                <p class="mt-4 text-sm leading-relaxed text-slate">{{ $p['footer_blurb'] ?? '' }}</p>
                <ul class="mt-5 space-y-2.5 text-sm">
                    <li><a href="{{ $biz['phone_href'] }}" class="flex items-center gap-2.5 font-semibold text-ink hover:text-primary"><span class="trust-ico h-9 w-9"><x-ico name="phone" :size="16" /></span> {{ $biz['phone'] }}</a></li>
                    @if($biz['whatsapp'])<li><a href="{{ $biz['whatsapp_href'] }}" target="_blank" rel="noopener" class="flex items-center gap-2.5 font-semibold text-ink hover:text-primary"><span class="trust-ico h-9 w-9 bg-success-light text-whatsapp-dark"><x-ico name="whatsapp" :size="16" /></span> WhatsApp us</a></li>@endif
                    @if($biz['email'])<li><a href="{{ $biz['email_href'] }}" class="flex items-center gap-2.5 font-semibold text-ink hover:text-primary"><span class="trust-ico h-9 w-9"><x-ico name="mail" :size="16" /></span> {{ $biz['email'] }}</a></li>@endif
                    @if($biz['address'])<li class="flex items-start gap-2.5 text-slate"><span class="trust-ico h-9 w-9 shrink-0"><x-ico name="map-pin" :size="16" /></span> <span class="pt-2">{{ $biz['address'] }}</span></li>@endif
                    @if($biz['hours'])<li class="flex items-center gap-2.5 text-slate"><span class="trust-ico h-9 w-9"><x-ico name="clock" :size="16" /></span> {{ $biz['hours'] }}@if($biz['emergency_available']) · <span class="font-semibold text-danger">24×7 emergencies</span>@endif</li>@endif
                </ul>
            </div>
            <div class="grid grid-cols-2 gap-6 lg:col-span-8 lg:grid-cols-4">
                @foreach([['Services', 'footer_services'], ['Company', 'footer_company'], ['Help', 'footer_help']] as [$label, $alias])
                    <div>
                        <p class="font-display text-sm font-extrabold">{{ $label }}</p>
                        <ul class="mt-3 space-y-2 text-sm text-slate">
                            @foreach($menus[$alias] ?? [] as $item)<li><a href="{{ $item->url }}" class="hover:text-primary">{{ $item->label }}</a></li>@endforeach
                        </ul>
                    </div>
                @endforeach
                <div>
                    <p class="font-display text-sm font-extrabold">Service areas</p>
                    <ul class="mt-3 space-y-2 text-sm text-slate">
                        @foreach($areas->take(7) as $a)<li><a href="{{ $a->url }}" class="hover:text-primary">Plumber in {{ $a->name }}</a></li>@endforeach
                        <li><a href="{{ route('areas.index') }}" class="font-semibold text-primary">All areas</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <div class="ps-container flex flex-col gap-3 py-5 text-xs text-slate lg:flex-row lg:items-center lg:justify-between">
        <p>© {{ date('Y') }} {{ $biz['name'] }}. @if(!empty($p['footer_note'])){{ $p['footer_note'] }}@endif</p>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            @foreach($menus['legal'] ?? [] as $item)<a href="{{ $item->url }}" class="hover:text-primary">{{ $item->label }}</a>@endforeach
            @foreach($p['social'] ?? [] as $s)
                @if(!empty($s['url']) && $s['url'] !== '#')<a href="{{ $s['url'] }}" target="_blank" rel="noopener" class="icon-btn h-8 w-8" aria-label="{{ ucfirst($s['network']) }}"><x-ico :name="in_array($s['network'], ['facebook','instagram','youtube','linkedin']) ? $s['network'] : 'external'" :size="16" /></a>@endif
            @endforeach
        </div>
    </div>
</footer>