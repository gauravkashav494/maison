{{-- Confirmation toast: a title naming the item and an optional second line (resources/js/toast.js) --}}
<div x-data x-show="$store.ui.toast" x-cloak x-transition.opacity.duration.200ms class="pointer-events-none fixed inset-x-0 bottom-20 z-[90] flex justify-center px-4 lg:bottom-8" aria-live="polite">
    <div class="flex max-w-md items-start gap-2.5 rounded-2xl px-4 py-3 text-sm font-bold text-white shadow-float" :class="$store.ui.toast?.tone === 'error' ? 'bg-nonveg' : 'bg-ink'">
        <template x-if="$store.ui.toast?.tone !== 'error'"><x-ico name="check" :size="16" class="mt-0.5 shrink-0" /></template>
        <template x-if="$store.ui.toast?.tone === 'error'"><x-ico name="alert" :size="16" class="mt-0.5 shrink-0" /></template>
        <div class="min-w-0">
            <p class="font-semibold leading-snug" x-text="$store.ui.toast?.title"></p>
            <p x-show="$store.ui.toast?.body" class="mt-0.5 text-xs font-normal leading-snug opacity-85" x-text="$store.ui.toast?.body"></p>
        </div>
    </div>
</div>
