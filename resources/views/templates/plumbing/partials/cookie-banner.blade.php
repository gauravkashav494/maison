<div x-data x-show="!$store.cookies.decided" x-cloak x-transition class="fixed inset-x-3 bottom-20 z-[55] mx-auto max-w-xl rounded-2xl border border-line bg-white p-4 shadow-float lg:bottom-6 lg:left-6 lg:right-auto lg:mx-0">
    <p class="text-sm text-slate">We use cookies to keep your cart, remember your preferences and understand what helps you shop. <a href="/cookies" class="font-semibold text-primary underline underline-offset-2">Cookie policy</a></p>
    <div class="mt-3 flex gap-2">
        <button type="button" @click="$store.cookies.acceptAll()" class="btn btn-primary btn-sm">Accept all</button>
        <button type="button" @click="$store.cookies.rejectAll()" class="btn btn-ghost btn-sm">Essential only</button>
    </div>
</div>
