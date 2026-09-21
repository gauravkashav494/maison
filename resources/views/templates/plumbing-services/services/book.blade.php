@extends('layouts.app', ['appBar' => ['title' => $emergency ? 'Emergency visit' : 'Book a plumber', 'back' => true]])

@section('content')
@php
    $biz = template()->contact();
    $cfg = [
        'services' => $services->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'icon' => $s->icon, 'is_emergency' => $s->is_emergency]),
        'areas' => $areas->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'slug' => $a->slug]),
        'slots' => \App\Models\ServiceRequest::SLOTS,
        'service' => $preselected ? ['id' => $preselected->id, 'name' => $preselected->name] : null,
        'problem' => $problem,
        'area' => request()->query('area', ''),
        'emergency' => $emergency,
        'user' => $user ? ['name' => $user->name, 'phone' => $user->phone, 'email' => $user->email] : null,
        'errors' => $errors->toArray() ? array_map(fn ($m) => $m[0], $errors->toArray()) : [],
        'old' => old(),
    ];
    $stepTitles = ['What do you need help with?', 'Describe the problem', 'Where do you need the service?', 'When do you need the plumber?', 'Your contact details', 'Confirm your booking'];
@endphp
<section class="ps-container py-4 lg:py-10" x-data="booking(@js($cfg))">
    <div class="mx-auto max-w-2xl">
        <x-breadcrumbs :items="['Book a plumber' => null]" class="mb-4" />
        {{-- Step indicator --}}
        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-wider text-primary">Step <span x-text="step"></span> of <span x-text="total"></span></p>
                <h1 class="mt-0.5 font-display text-xl font-extrabold lg:text-3xl" x-text="@js($stepTitles)[step - 1]"></h1>
            </div>
            <ol class="flex shrink-0 items-center gap-1" aria-hidden="true">
                @for($i = 1; $i <= 6; $i++)<li><button type="button" @click="goto({{ $i }})" class="grid h-7 w-7 place-items-center rounded-full text-[0.65rem] font-extrabold transition" :class="step === {{ $i }} ? 'bg-primary text-white shadow-glow' : (step > {{ $i }} ? 'bg-success-light text-success' : 'bg-line-soft text-mist')"><span x-show="step <= {{ $i }}">{{ $i }}</span><x-ico name="check" :size="12" x-show="step > {{ $i }}" x-cloak /></button></li>@endfor
            </ol>
        </div>
        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-line-soft"><div class="h-full rounded-full bg-primary transition-all duration-300" :style="`width:${progress}%`"></div></div>

        <form x-ref="form" method="post" action="{{ route('booking.store') }}" class="card mt-5 p-4 sm:p-6" @submit.prevent="submit()" novalidate>
            @csrf
            <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
            <input type="hidden" name="service_id" :value="f.service_id">
            <input type="hidden" name="service_name" :value="f.service_name">
            <input type="hidden" name="service_area_id" :value="f.service_area_id">
            <input type="hidden" name="type" :value="f.type">
            <input type="hidden" name="preferred_date" :value="f.preferred_date">
            <input type="hidden" name="time_slot" :value="f.time_slot">

            {{-- Step 1: service --}}
            <div x-show="step === 1" x-transition.opacity.duration.200ms>
                <p class="text-sm text-slate">Pick the closest match — the plumber will confirm on site.</p>
                <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach($services as $s)
                        <button type="button" @click="pickService(@js(['id' => $s->id, 'name' => $s->name, 'is_emergency' => $s->is_emergency]))" class="option flex-col items-start gap-2 !p-3" :class="String(f.service_id) === '{{ $s->id }}' && 'option-active'">
                            <span class="svc-ico h-9 w-9 rounded-xl {{ $s->is_emergency ? 'svc-ico-danger' : '' }}"><x-ico :name="$s->icon ?: 'wrench'" :size="18" /></span>
                            <span class="text-xs font-bold leading-tight">{{ $s->name }}</span>
                        </button>
                    @endforeach
                    <button type="button" @click="pickService(null)" class="option flex-col items-start gap-2 !p-3" :class="f.other && 'option-active'">
                        <span class="svc-ico svc-ico-accent h-9 w-9 rounded-xl"><x-ico name="help" :size="18" /></span>
                        <span class="text-xs font-bold leading-tight">Other / not sure</span>
                    </button>
                </div>
                <div x-show="f.other" x-collapse class="mt-3">
                    <label class="label" for="service_name">Tell us what you need</label>
                    <input id="service_name" type="text" x-model="f.service_name" placeholder="e.g. Water is dripping from the ceiling" class="field" :class="errors.service_name && 'field-error'">
                    <p class="error" x-show="errors.service_name" x-text="errors.service_name"></p>
                    <button type="button" @click="next()" class="btn btn-primary mt-3">Continue</button>
                </div>
                <p class="error" x-show="errors.service_name && !f.other" x-text="errors.service_name"></p>
            </div>

            {{-- Step 2: problem --}}
            <div x-show="step === 2" x-cloak x-transition.opacity.duration.200ms>
                <p class="text-sm text-slate">Selected: <strong class="text-ink" x-text="serviceLabel"></strong> · <button type="button" @click="goto(1)" class="font-semibold text-primary">change</button></p>
                <label class="label mt-4" for="problem">What is happening? <span class="font-normal text-slate">(optional but helpful)</span></label>
                <textarea id="problem" name="problem" x-model="f.problem" rows="4" class="field" placeholder="e.g. The bathroom tap has been dripping for a week and the wall below is damp."></textarea>
                <div class="mt-2 flex flex-wrap gap-1.5">@foreach(['Leaking', 'Blocked', 'No water', 'Low pressure', 'Broken', 'New installation'] as $hint)<button type="button" @click="f.problem = f.problem ? f.problem + ', ' + @js(strtolower($hint)) : @js($hint)" class="chip !h-8 text-xs">{{ $hint }}</button>@endforeach</div>
                <div class="mt-5 flex justify-between"><button type="button" @click="back()" class="btn btn-ghost">Back</button><button type="button" @click="next()" class="btn btn-primary">Continue</button></div>
            </div>

            {{-- Step 3: address --}}
            <div x-show="step === 3" x-cloak x-transition.opacity.duration.200ms>
                <label class="label" for="area">City / area</label>
                <input id="area" name="area" list="areas" x-model="f.area" class="field" placeholder="Start typing your city" autocomplete="off" :class="errors.area && 'field-error'">
                <datalist id="areas">@foreach($areas as $a)<option value="{{ $a->name }}">@endforeach</datalist>
                <div class="mt-2 flex flex-wrap gap-1.5">@foreach($areas->take(6) as $a)<button type="button" @click="f.area = @js($a->name)" class="chip !h-8 text-xs" :class="f.area === @js($a->name) && 'chip-active'">{{ $a->name }}</button>@endforeach</div>
                <label class="label mt-4" for="address">Full address</label>
                <textarea id="address" name="address" x-model="f.address" rows="3" class="field" placeholder="House / flat, building, street, landmark" :class="errors.address && 'field-error'"></textarea>
                <p class="error" x-show="errors.address" x-text="errors.address"></p>
                @auth
                    @php $saved = auth()->user()->addresses()->orderByDesc('is_default')->get(); @endphp
                    @if($saved->isNotEmpty())
                        <p class="label mt-3">Saved addresses</p>
                        <div class="flex flex-wrap gap-1.5">@foreach($saved as $ad)<button type="button" @click="f.address = @js(implode(', ', $ad->lines())); f.area = @js($ad->city)" class="chip !h-8 text-xs"><x-ico name="map-pin" :size="12" /> {{ $ad->label ?: $ad->city }}</button>@endforeach</div>
                    @endif
                @endauth
                <div class="mt-5 flex justify-between"><button type="button" @click="back()" class="btn btn-ghost">Back</button><button type="button" @click="next()" class="btn btn-primary">Continue</button></div>
            </div>

            {{-- Step 4: when --}}
            <div x-show="step === 4" x-cloak x-transition.opacity.duration.200ms>
                <p class="label">Which day?</p>
                <div class="grid grid-cols-3 gap-2">
                    <button type="button" @click="pickWhen('today')" class="option justify-center text-sm font-bold" :class="f.when === 'today' && 'option-active'">Today</button>
                    <button type="button" @click="pickWhen('tomorrow')" class="option justify-center text-sm font-bold" :class="f.when === 'tomorrow' && 'option-active'">Tomorrow</button>
                    <button type="button" @click="pickWhen('date')" class="option justify-center text-sm font-bold" :class="f.when === 'date' && 'option-active'">Pick a date</button>
                </div>
                <div x-show="f.when === 'date'" x-collapse class="mt-3"><input type="date" x-model="f.preferred_date" :min="today" class="field" aria-label="Preferred date"></div>
                <p class="error" x-show="errors.preferred_date" x-text="errors.preferred_date"></p>
                <p class="label mt-5">Preferred time</p>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach(\App\Models\ServiceRequest::SLOTS as $key => $label)
                        <button type="button" @click="f.time_slot = @js($key)" class="option text-sm font-bold" :class="f.time_slot === @js($key) && 'option-active'"><span class="svc-ico h-8 w-8 rounded-lg {{ $key === 'asap' ? 'svc-ico-danger' : '' }}"><x-ico :name="$key === 'asap' ? 'bolt' : 'clock'" :size="16" /></span> {{ $label }}</button>
                    @endforeach
                </div>
                <p class="error" x-show="errors.time_slot" x-text="errors.time_slot"></p>
                <div class="mt-5 flex justify-between"><button type="button" @click="back()" class="btn btn-ghost">Back</button><button type="button" @click="next()" class="btn btn-primary">Continue</button></div>
            </div>

            {{-- Step 5: contact --}}
            <div x-show="step === 5" x-cloak x-transition.opacity.duration.200ms>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div><label class="label" for="name">Your name</label><input id="name" name="name" x-model="f.name" class="field" autocomplete="name" :class="errors.name && 'field-error'"><p class="error" x-show="errors.name" x-text="errors.name"></p></div>
                    <div><label class="label" for="phone">Mobile number</label><input id="phone" name="phone" type="tel" inputmode="tel" x-model="f.phone" class="field" placeholder="98765 43210" autocomplete="tel" :class="errors.phone && 'field-error'"><p class="error" x-show="errors.phone" x-text="errors.phone"></p></div>
                    <div class="sm:col-span-2"><label class="label" for="email">Email <span class="font-normal text-slate">(optional)</span></label><input id="email" name="email" type="email" x-model="f.email" class="field" autocomplete="email" :class="errors.email && 'field-error'"><p class="error" x-show="errors.email" x-text="errors.email"></p></div>
                </div>
                <p class="mt-3 text-xs text-slate">We call this number to confirm the slot. No spam, no marketing calls.</p>
                <div class="mt-5 flex justify-between"><button type="button" @click="back()" class="btn btn-ghost">Back</button><button type="button" @click="next()" class="btn btn-primary">Review booking</button></div>
            </div>

            {{-- Step 6: confirm --}}
            <div x-show="step === 6" x-cloak x-transition.opacity.duration.200ms>
                <dl class="divide-y divide-line-soft rounded-2xl bg-canvas ring-1 ring-line">
                    <div class="flex items-start justify-between gap-4 px-4 py-3"><dt class="text-xs font-bold uppercase tracking-wider text-slate">Service</dt><dd class="text-right text-sm font-bold" x-text="serviceLabel"></dd></div>
                    <div class="flex items-start justify-between gap-4 px-4 py-3" x-show="f.problem"><dt class="text-xs font-bold uppercase tracking-wider text-slate">Problem</dt><dd class="max-w-[60%] text-right text-sm" x-text="f.problem"></dd></div>
                    <div class="flex items-start justify-between gap-4 px-4 py-3"><dt class="text-xs font-bold uppercase tracking-wider text-slate">Location</dt><dd class="max-w-[60%] text-right text-sm" x-text="[f.address, f.area].filter(Boolean).join(', ')"></dd></div>
                    <div class="flex items-start justify-between gap-4 px-4 py-3"><dt class="text-xs font-bold uppercase tracking-wider text-slate">Date</dt><dd class="text-sm font-bold" x-text="dateLabel"></dd></div>
                    <div class="flex items-start justify-between gap-4 px-4 py-3"><dt class="text-xs font-bold uppercase tracking-wider text-slate">Time</dt><dd class="text-sm font-bold" x-text="slotLabel"></dd></div>
                    <div class="flex items-start justify-between gap-4 px-4 py-3"><dt class="text-xs font-bold uppercase tracking-wider text-slate">Contact</dt><dd class="text-right text-sm"><span class="font-bold" x-text="f.name"></span><br><span x-text="f.phone"></span><template x-if="f.email"><span><br><span x-text="f.email"></span></span></template></dd></div>
                </dl>
                <p class="mt-3 text-xs text-slate">By confirming you agree to our <a href="/terms" class="font-semibold text-primary">terms</a>. The visit time is confirmed on call. Cancel free any time before the plumber is dispatched.</p>
                <div class="mt-5 flex justify-between"><button type="button" @click="back()" class="btn btn-ghost">Back</button><button type="submit" class="btn btn-accent btn-lg" :disabled="submitting"><span x-show="!submitting">Confirm booking</span><span x-show="submitting" x-cloak>Sending…</span></button></div>
            </div>
        </form>

        <div class="mt-4 flex items-center justify-center gap-4 text-xs text-slate">
            <a href="{{ $biz['phone_href'] }}" class="flex items-center gap-1.5 font-semibold text-primary"><x-ico name="phone" :size="14" /> Prefer to call? {{ $biz['phone'] }}</a>
            @if($biz['whatsapp'])<a href="{{ $biz['whatsapp_href'] }}" target="_blank" rel="noopener" class="flex items-center gap-1.5 font-semibold text-whatsapp-dark"><x-ico name="whatsapp" :size="14" /> WhatsApp</a>@endif
        </div>
    </div>
</section>
@endsection