<script setup>
import { computed, nextTick, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { pluralize } from '../planLabel';

// Inline entry for one grade level: type a name and press Enter, or paste a
// list (one subject per line) and confirm it. Code and hours are edited later.
const props = defineProps({
    planId: { type: Number, required: true },
    gradeLevel: { type: Object, required: true },
    compact: { type: Boolean, default: false },
});

const MAX_NAMES = 50;

const name = ref('');
const pending = ref([]);
const error = ref(null);
const saving = ref(false);
const input = ref(null);

const tooMany = computed(() => pending.value.length > MAX_NAMES);

// "1. Matemática", "- Lengua", "• Arte" all become the bare name.
function parseLines(text) {
    return text
        .split(/\r?\n/)
        .map((line) => line.replace(/^\s*(?:[-*•·]|\d+[.)-])\s*/, '').trim())
        .filter((line) => line !== '');
}

function onPaste(event) {
    const text = event.clipboardData?.getData('text') ?? '';

    if (!/\r?\n/.test(text.trim())) return;

    event.preventDefault();
    pending.value = parseLines(text);
    error.value = null;
}

function submit(names) {
    if (names.length === 0 || saving.value) return;

    saving.value = true;
    error.value = null;

    router.post(
        route('subjects.subjects.store-many', props.planId, false),
        { grade_level_id: props.gradeLevel.id, names },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                name.value = '';
                pending.value = [];
            },
            onError: (errors) => {
                error.value = Object.values(errors)[0] ?? 'No se pudo agregar.';
            },
            onFinish: () => {
                saving.value = false;
                nextTick(() => input.value?.inputRef?.focus());
            },
        },
    );
}

function submitOne() {
    const value = name.value.trim();

    if (value !== '') submit([value]);
}

function cancelPending() {
    pending.value = [];
    nextTick(() => input.value?.inputRef?.focus());
}
</script>

<template>
    <div :class="compact ? 'space-y-2' : 'space-y-2 px-5 py-3'">
        <div v-if="pending.length > 0" class="space-y-3 rounded-lg border border-primary/40 bg-primary/5 p-3">
            <p class="text-sm font-semibold text-highlighted">
                Agregar {{ pluralize(pending.length, 'asignatura', 'asignaturas') }} a {{ gradeLevel.name }}
            </p>
            <ul class="flex flex-wrap gap-1.5">
                <li v-for="(item, index) in pending" :key="index" class="rounded-md bg-default px-2 py-1 text-xs text-default ring-1 ring-default">
                    {{ item }}
                </li>
            </ul>
            <p v-if="tooMany" class="text-xs text-error">Puedes agregar hasta {{ MAX_NAMES }} a la vez. Divide la lista.</p>
            <div class="flex flex-wrap gap-2">
                <UButton size="sm" icon="i-lucide-check" :loading="saving" :disabled="tooMany" @click="submit(pending)">
                    Agregar {{ pending.length }}
                </UButton>
                <UButton size="sm" color="neutral" variant="ghost" :disabled="saving" @click="cancelPending">Cancelar</UButton>
            </div>
        </div>

        <form v-else novalidate @submit.prevent="submitOne">
            <UInput
                ref="input"
                v-model="name"
                :maxlength="150"
                :size="compact ? 'sm' : 'md'"
                icon="i-lucide-plus"
                :placeholder="compact ? 'Agregar asignatura' : `Agregar asignatura a ${gradeLevel.name}`"
                :aria-label="`Agregar asignatura a ${gradeLevel.name}`"
                :loading="saving"
                variant="soft"
                class="w-full"
                @paste="onPaste"
            >
                <template v-if="name.trim() !== '' && !compact" #trailing>
                    <UKbd value="enter" />
                </template>
            </UInput>
        </form>

        <p v-if="error" class="text-xs text-error">{{ error }}</p>
    </div>
</template>
