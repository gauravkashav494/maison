@php
    $statePath = $getStatePath();
    $isDisabled = $isDisabled();
    // One country (India for now): its code is shown fixed in the box, with no dropdown.
    $hasChoice = count($getCountries()) > 1;
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.$entangle(@js($statePath)),
            countries: @js($getCountries()),
            country: @js($getDefaultCountry()),
            number: '',
            open: false,
            search: '',
            init() {
                this.read(this.state)
                // Another part of the form (or a reset) changed the value: show it.
                this.$watch('state', (value) => { if (value !== this.compose()) this.read(value) })
            },
            current() {
                return this.countries.find((c) => c.code === this.country) ?? this.countries[0]
            },
            // '+91 98765 43210' -> India and '98765 43210'; older values without '+' stay as typed.
            read(value) {
                value = (value ?? '').toString().trim()
                const match = value.match(/^\+(\d+)\s*(.*)$/)
                if (! match) { this.number = value; return }
                const digits = match[1] + match[2].replace(/\D+/g, '')
                const exact = this.countries.find((c) => c.dial === match[1])
                const found = exact ?? [...this.countries].sort((a, b) => b.dial.length - a.dial.length).find((c) => digits.startsWith(c.dial))
                if (! found) { this.number = value; return }
                this.country = found.code
                this.number = this.tidy(exact ? match[2] : digits.slice(found.dial.length))
            },
            // Digits only; the box itself takes at most 10 characters (maxlength).
            tidy(value) {
                return (value ?? '').toString().replace(/\D+/g, '').slice(0, 10)
            },
            compose() {
                const number = (this.number ?? '').trim()
                return number === '' ? null : '+' + this.current().dial + ' ' + number
            },
            write() { this.state = this.compose() },
            panelStyle: '',
            toggle() {
                this.open = ! this.open
                if (this.open) { this.place(); this.$nextTick(() => this.$refs.search.focus()) }
            },
            // Just under the box (or above it when there is no room below).
            place() {
                const box = this.$root.querySelector('.fi-phone-box').getBoundingClientRect()
                const height = 300
                const below = window.innerHeight - box.bottom > height || box.top < height
                const top = below ? box.bottom + 4 : box.top - height - 4
                this.panelStyle = `top:${top}px;left:${box.left}px;width:${Math.min(320, box.width)}px`
            },
            choose(code) {
                this.country = code
                this.open = false
                this.search = ''
                this.write()
                this.$nextTick(() => this.$refs.number.focus())
            },
            filtered() {
                const q = this.search.toLowerCase().replace(/^\+/, '')
                return q === '' ? this.countries : this.countries.filter((c) => c.name.toLowerCase().includes(q) || c.dial.startsWith(q) || c.code.toLowerCase() === q)
            },
        }"
        class="fi-phone"
        x-on:keydown.escape="open = false"
        x-on:click.outside="open = false"
    >
        <div class="fi-phone-box">
        <x-filament::input.wrapper :disabled="$isDisabled" :valid="! $errors->has($statePath)">
            <div class="fi-phone-row">
                @if ($hasChoice)
                <button
                    type="button"
                    class="fi-phone-country"
                    x-ref="toggle"
                    x-on:click="toggle()"
                    x-bind:aria-expanded="open"
                    x-bind:title="current().name"
                    aria-haspopup="listbox"
                    aria-label="Country"
                    @disabled($isDisabled)
                >
                    <span class="fi-phone-code" x-text="current().code"></span>
                    <span class="fi-phone-dial" x-text="'+' + current().dial"></span>
                    <svg class="fi-phone-caret" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/></svg>
                </button>
                @else
                    <span class="fi-phone-country is-fixed" x-bind:title="current().name">
                        <span class="fi-phone-dial" x-text="'+' + current().dial"></span>
                    </span>
                @endif

                <input
                    x-ref="number"
                    type="tel"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    autocomplete="tel-national"
                    id="{{ $getId() }}"
                    class="fi-input fi-phone-number"
                    x-model="number"
                    x-on:input="number = tidy($event.target.value); $event.target.value = number; write()"
                    @if (filled($getPlaceholder())) placeholder="{{ $getPlaceholder() }}" @else x-bind:placeholder="current().example" @endif
                    maxlength="10"
                    @disabled($isDisabled)
                    @if ($isRequired()) required @endif
                />
            </div>
        </x-filament::input.wrapper>

        @if ($hasChoice)
        {{-- Fixed to the viewport, so a section card with overflow hidden cannot cut it off. --}}
        <div x-show="open" x-cloak x-transition.opacity.duration.100ms class="fi-phone-panel" x-bind:style="panelStyle"
            x-on:scroll.window="open && place()" x-on:resize.window="open && place()">
            <input x-ref="search" type="search" class="fi-phone-search" placeholder="Search country or code" x-model="search"
                x-on:keydown.enter.prevent="filtered()[0] && choose(filtered()[0].code)" />
            <ul role="listbox" class="fi-phone-list">
                <template x-for="c in filtered()" :key="c.code">
                    <li>
                        <button type="button" class="fi-phone-option" x-bind:class="{ 'is-active': c.code === country }"
                            x-on:click="choose(c.code)" role="option" x-bind:aria-selected="c.code === country">
                            <span class="fi-phone-code" x-text="c.code"></span>
                            <span class="fi-phone-name" x-text="c.name"></span>
                            <span class="fi-phone-dial" x-text="'+' + c.dial"></span>
                        </button>
                    </li>
                </template>
                <li x-show="filtered().length === 0" class="fi-phone-empty">No matching country</li>
            </ul>
        </div>
        @endif
        </div>

        <p class="fi-phone-hint" x-text="current().hint + '.'"></p>
    </div>
</x-dynamic-component>
