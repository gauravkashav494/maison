{{-- Small confirmation toast (added to cart, saved, errors) --}}
<div x-data x-show="$store.ui.toast" x-cloak x-transition.opacity.duration.200ms class="pointer-events-none fixed inset-x-0 bottom-20 z-[90] flex justify-center px-4 lg:bottom-8" aria-live="polite">
    <div class="flex items-center gap-2 rounded-full px-4 py-2.5 text-sm font-bold text-white shadow-float" :class="$store.ui.toast?.tone === 'error' ? 'bg-nonveg' : 'bg-ink'">
        <template x-if="$store.ui.toast?.tone !== 'error'"><x-ico name="check" :size="16" /></template>
        <template x-if="$store.ui.toast?.tone === 'error'"><x-ico name="alert" :size="16" /></template>
        <span x-text="$store.ui.toast?.message"></span>
    </div>
</div>
