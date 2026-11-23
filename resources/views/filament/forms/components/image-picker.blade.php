@php
    $statePath = $getStatePath();
    $options = $getOptions();
    $isDisabled = $isDisabled();
    $chosen = array_values(array_filter((array) $getState(), 'is_string'));
@endphp

{{-- The label is printed here as well: inside a table repeater (Step 1 · Options) Filament hides field labels. --}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.$entangle(@js($statePath)),
            initial: @js($chosen),
            list() { return Array.isArray(this.state) ? this.state : (this.state ? Object.values(this.state) : this.initial) },
            has(path) { return this.list().includes(path) },
            toggle(path) {
                const list = [...this.list()]
                const at = list.indexOf(path)
                at === -1 ? list.push(path) : list.splice(at, 1)
                this.state = list
            },
        }"
        class="fi-image-picker"
    >
        @if ($showsInlineLabel())
            <p class="fi-image-picker-label">{{ $getLabel() }}</p>
        @endif
        @if ($options === [])
            <p class="fi-image-picker-empty">Add images in the Media tab, save, then pick them here.</p>
        @else
            <div class="fi-image-picker-grid" role="group" aria-label="{{ $getLabel() }}">
                @foreach ($options as $option)
                    @php $isChosen = in_array($option['path'], $chosen, true); @endphp
                    <button
                        type="button"
                        @class(['fi-image-picker-item', 'is-chosen' => $isChosen])
                        x-bind:class="{ 'is-chosen': has(@js($option['path'])) }"
                        x-bind:aria-pressed="has(@js($option['path']))"
                        aria-pressed="{{ $isChosen ? 'true' : 'false' }}"
                        x-on:click="toggle(@js($option['path']))"
                        title="{{ $isChosen ? 'Selected · click to remove' : 'Click to select' }}: {{ basename($option['path']) }}"
                        @disabled($isDisabled)
                    >
                        <img src="{{ $option['url'] }}" alt="" loading="lazy">
                        <span class="fi-image-picker-check" aria-hidden="true" x-show="has(@js($option['path']))" @unless($isChosen) style="display: none" @endunless>
                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                        </span>
                    </button>
                @endforeach
            </div>
        @endif
        @if ($hasPendingImages())
            <p class="fi-image-picker-empty">New images can be picked after saving.</p>
        @endif
    </div>
</x-dynamic-component>
