@extends('layouts.app', ['appBar' => ['title' => 'Request a quote', 'back' => true]])

@section('content')
@php $biz = template()->contact(); $site = tsetting('site'); @endphp
<section class="ps-container py-4 lg:py-10">
    <div class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-12 lg:gap-10">
        <div class="lg:col-span-5">
            <x-breadcrumbs :items="['Request a quote' => null]" class="mb-4" />
            <p class="eyebrow">Free, no obligation</p>
            <h1 class="mt-2 font-display text-2xl font-extrabold lg:text-4xl">{{ $site['quote_heading'] ?? 'Need a quote?' }}</h1>
            <p class="mt-2 text-sm text-slate lg:text-base">{{ $site['quote_text'] ?? '' }}</p>
            <ul class="mt-5 space-y-2.5 text-sm">
                <li class="flex items-center gap-2.5"><span class="trust-ico h-8 w-8"><x-ico name="phone" :size="15" /></span> We call back within business hours</li>
                <li class="flex items-center gap-2.5"><span class="trust-ico h-8 w-8"><x-ico name="rupee" :size="15" /></span> Labour and material explained separately</li>
                <li class="flex items-center gap-2.5"><span class="trust-ico h-8 w-8"><x-ico name="user-check" :size="15" /></span> Site visit for bigger jobs, if needed</li>
            </ul>
            <p class="mt-5 text-xs text-slate">Prefer talking? <a href="{{ $biz['phone_href'] }}" class="font-semibold text-primary">{{ $biz['phone'] }}</a>@if($biz['whatsapp']) · <a href="{{ $biz['whatsapp_href'] }}" target="_blank" rel="noopener" class="font-semibold text-whatsapp-dark">WhatsApp us photos</a>@endif</p>
        </div>
        <form method="post" action="{{ route('quote.store') }}" class="card space-y-4 p-4 sm:p-6 lg:col-span-7" x-data="leadForm()" @submit.prevent="submit()">
            @csrf
            <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
            <div>
                <label class="label" for="service_id">Service</label>
                <select id="service_id" name="service_id" class="field">
                    <option value="">Not sure / general requirement</option>
                    @foreach($services as $s)<option value="{{ $s->id }}" @selected(old('service_id', request()->query('service')) == $s->id || request()->query('service') === $s->slug)>{{ $s->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label" for="problem">Tell us about the requirement</label>
                <textarea id="problem" name="problem" rows="5" required class="field @error('problem') field-error @enderror" placeholder="e.g. Two bathrooms on the first floor need full re-plumbing — old GI pipes, new concealed CPVC lines, and new fittings.">{{ old('problem') }}</textarea>
                @error('problem')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div><label class="label" for="area">City / area</label><input id="area" name="area" list="areas" value="{{ old('area', request()->query('area')) }}" class="field" autocomplete="off"><datalist id="areas">@foreach($areas as $a)<option value="{{ $a->name }}">@endforeach</datalist></div>
                <div><label class="label" for="address">Address <span class="font-normal text-slate">(optional)</span></label><input id="address" name="address" value="{{ old('address') }}" class="field"></div>
                <div><label class="label" for="name">Your name</label><input id="name" name="name" required value="{{ old('name', $user?->name) }}" class="field @error('name') field-error @enderror" autocomplete="name">@error('name')<p class="error">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="phone">Mobile number</label><input id="phone" name="phone" type="tel" inputmode="tel" required value="{{ old('phone', $user?->phone) }}" class="field @error('phone') field-error @enderror" autocomplete="tel">@error('phone')<p class="error">{{ $message }}</p>@enderror</div>
                <div class="sm:col-span-2"><label class="label" for="email">Email <span class="font-normal text-slate">(optional)</span></label><input id="email" name="email" type="email" value="{{ old('email', $user?->email) }}" class="field @error('email') field-error @enderror" autocomplete="email">@error('email')<p class="error">{{ $message }}</p>@enderror</div>
            </div>
            <button type="submit" class="btn btn-accent btn-lg btn-block" :disabled="submitting"><span x-show="!submitting">Request a quote</span><span x-show="submitting" x-cloak>Sending…</span></button>
            <p class="text-center text-xs text-slate">No prices are shown online — every requirement is different. We explain the cost before any work starts.</p>
        </form>
    </div>
</section>
@endsection