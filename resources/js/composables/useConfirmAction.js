import { ref } from 'vue';

// State for a ConfirmModal guarding one action on a row: `ask(row)` opens it,
// `run(visit)` performs the Inertia visit and closes the modal when it ends.
// `target` is kept until the modal has fully closed (clear it on after:leave).
export function useConfirmAction() {
    const target = ref(null);
    const open = ref(false);
    const processing = ref(false);

    function ask(row) {
        target.value = row;
        open.value = true;
    }

    function run(visit) {
        processing.value = true;

        visit({
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                open.value = false;
            },
        });
    }

    function clear() {
        target.value = null;
    }

    return { target, open, processing, ask, run, clear };
}
