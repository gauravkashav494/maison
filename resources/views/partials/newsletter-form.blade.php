@php $light = $light ?? false; $source = $source ?? 'site'; $class = $class ?? ''; @endphp
<div x-data="newsletter(@js($source))" class="{{ $class }}">
    <p x-show="done" x-cloak class="py-3 font-serif text-xl {{ $light ? 'text-ivory' : 'text-ink' }}">Welcome. Your first note from the house is on its way.</p>
    <form x-show="!done" @submit.prevent="submit()" class="flex items-end gap-4 {{ $light ? 'text-ivory' : 'text-ink' }}">
        <label class="sr-only" for="newsletter-{{ $source }}">Email address</label>
        <input id="newsletter-{{ $source }}" type="email" required x-model="email" placeholder="Your email address" class="input-luxe">
        <button type="submit" :disabled="busy" class="shrink-0 border-b pb-3 text-[0.6875rem] uppercase tracking-[0.2em] transition-opacity hover:opacity-60 {{ $light ? 'border-ivory/60' : 'border-ink' }}">Subscribe</button>
    </form>
    <p x-show="!done" class="mt-3 text-[0.6875rem] {{ $light ? 'text-ivory/45' : 'text-taupe' }}"><span x-show="error" x-text="error" class="text-rouge"></span><span x-show="!error">By subscribing you agree to our Privacy Policy. Unsubscribe at any time.</span></p>
</div>
