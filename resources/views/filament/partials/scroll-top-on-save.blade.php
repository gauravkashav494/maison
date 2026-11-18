{{--
    After a form saves (create, edit or settings page) the "Saved" toast appears at the top while
    the page stays wherever the user had scrolled, which reads as if nothing happened. Scroll back
    to the top once the save succeeds. A save that fails validation is left alone: Filament
    already scrolls to the first error. Table actions and modals are not form submits, so they
    keep their place.
--}}
<script>
    document.addEventListener('livewire:init', () => {
        const SAVES = ['save', 'create', 'createAnother'];

        Livewire.interceptMessage(({ message, onSuccess }) => {
            const isSave = [...message.actions].some((action) => SAVES.includes(action.name));
            if (! isSave) return;

            onSuccess(({ payload }) => {
                let errors = {};
                try {
                    const snapshot = typeof payload?.snapshot === 'string' ? JSON.parse(payload.snapshot) : payload?.snapshot;
                    errors = snapshot?.memo?.errors ?? {};
                } catch (e) {}

                if (Object.keys(errors).length > 0) return;

                // After the page has redrawn, so the scroll is not undone by the update.
                requestAnimationFrame(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
            });
        });
    });
</script>
