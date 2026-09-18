{{-- Delivery location picker (stored per device) --}}
@php $g = tsetting('site'); @endphp
<div x-show="$store.ui.locationOpen" x-cloak x-data="locationPicker()">
    <div class="overlay" x-show="$store.ui.locationOpen" x-transition.opacity @click="$store.ui.closeAll()"></div>
    <div class="sheet sheet-bottom mx-auto sm:inset-x-0 sm:bottom-auto sm:top-24 sm:w-[28rem] sm:rounded-2xl" x-show="$store.ui.locationOpen" x-transition x-trap.noscroll="$store.ui.locationOpen">
        <div class="sheet-handle sm:hidden"></div>
        <div class="flex items-center justify-between px-5 pt-4">
            <h2 class="text-lg font-extrabold">Where should we deliver?</h2>
            <button type="button" @click="$store.ui.closeAll()" class="grid h-9 w-9 place-items-center rounded-lg hover:bg-paper" aria-label="Close"><x-ico name="close" :size="20" /></button>
        </div>
        <div class="space-y-5 px-5 pb-6 pt-4">
            <form @submit.prevent="check()" class="flex gap-2">
                <input x-model="pincode" inputmode="numeric" maxlength="6" placeholder="Enter 6-digit pincode" class="field flex-1" aria-label="Pincode">
                <button type="submit" class="btn btn-primary">Check</button>
            </form>
            <p x-show="error" x-cloak class="-mt-3 text-xs text-nonveg" x-text="error"></p>
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-mist">Or choose a city</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <template x-for="a in areas" :key="a">
                        <button type="button" @click="$store.location.set(a)" class="chip" :class="$store.location.area === a && 'chip-active'" x-text="a"></button>
                    </template>
                </div>
            </div>
            <div class="flex items-start gap-3 rounded-xl bg-leaf-light p-3 text-sm text-leaf-dark">
                <x-ico name="bolt" :size="18" class="mt-0.5 shrink-0" />
                <p><strong>{{ $g['delivery_promise'] ?? 'Fast delivery' }}</strong> in serviceable areas. Slots and charges are shown at checkout.</p>
            </div>
        </div>
    </div>
</div>
