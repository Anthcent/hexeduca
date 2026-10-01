<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({
    period: { type: Object, required: true },
    offers: { type: Array, default: () => [] },
    offerId: { type: Number, required: true },
    moments: { type: Array, default: () => [] },
    moment: { type: Object, default: null },
    periodOpen: { type: Boolean, default: true },
    canEdit: { type: Boolean, default: false },
    canCorrect: { type: Boolean, default: false },
    scale: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    edited: { type: Array, default: () => [] },
});

const rows = reactive(props.rows.map((row) => ({ ...row })));
const edited = reactive(new Set(props.edited));
const state = reactive({});
const errors = reactive({});
const latest = {};
let sequence = 0;

const selectedOffer = ref(props.offerId);
const offerItems = computed(() => props.offers.map((o) => ({ label: o.label, value: o.id })));

function visit(query) {
    router.get(route('grades.conduct', undefined, false), { period: props.period.id, offer: props.offerId, moment: props.moment?.id, ...query }, { preserveScroll: true, replace: true });
}

watch(selectedOffer, (value) => {
    if (value !== props.offerId) visit({ offer: value });
});

const graded = computed(() => rows.filter((row) => row.letter !== null).length);
const saving = computed(() => Object.values(state).includes('saving'));
const failed = computed(() => Object.values(state).includes('error'));

async function choose(row, letter) {
    if (!props.canEdit) return;

    const next = row.letter === letter ? null : letter;
    const previous = row.letter;
    const ticket = ++sequence;
    latest[row.id] = ticket;
    row.letter = next;
    state[row.id] = 'saving';
    delete errors[row.id];

    try {
        const { data } = await axios.put(route('grades.conduct.record', undefined, false), {
            offer_id: props.offerId,
            moment_id: props.moment.id,
            student_id: row.id,
            letter: next,
        });

        if (latest[row.id] !== ticket) return;

        // The server decides: edited = the letter changed after its first entry.
        if (data.edited) edited.add(row.id);
        else edited.delete(row.id);
        state[row.id] = 'saved';
    } catch (error) {
        if (latest[row.id] !== ticket) return;

        row.letter = previous;
        state[row.id] = 'error';
        errors[row.id] = error.response?.data?.message ?? 'No se pudo guardar. Revisa tu conexión.';
    }
}

const readOnlyReason = computed(() => {
    if (props.canEdit || !props.moment) return null;
    if (!props.periodOpen) return 'El periodo está cerrado: Convivir es de solo lectura.';

    return 'La carga de este momento está cerrada. El personal administrativo puede corregirla.';
});
</script>

<template>
    <Head title="Convivir" />

    <DashboardLayout active="notas">
        <PageHeader
            :eyebrow="`Notas · ${period.name}`"
            title="Convivir"
            description="Calificación de convivencia por momento, a cargo del docente orientador de la sección. No forma parte de las notas de las asignaturas."
            icon="i-lucide-handshake"
        >
            <template #leading>
                <UButton :to="route('grades.index', { period: period.id }, false)" color="neutral" variant="link" icon="i-lucide-arrow-left" class="px-0">
                    Volver a notas
                </UButton>
            </template>
        </PageHeader>

        <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-center">
            <USelect v-if="offers.length > 1" v-model="selectedOffer" :items="offerItems" icon="i-lucide-layout-grid" class="w-full sm:w-72" aria-label="Sección" />
            <p v-else class="text-sm font-semibold text-highlighted">{{ offers[0]?.label }}</p>

            <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="Momento">
                <UButton
                    v-for="item in moments"
                    :key="item.id"
                    role="tab"
                    :aria-selected="moment?.id === item.id"
                    size="sm"
                    class="rounded-full px-3"
                    :color="moment?.id === item.id ? 'primary' : 'neutral'"
                    :variant="moment?.id === item.id ? 'solid' : 'outline'"
                    @click="visit({ moment: item.id })"
                >
                    {{ item.name }}
                </UButton>
            </div>

            <div v-if="moment" class="flex items-center gap-3 text-sm text-muted lg:ms-auto">
                <span><span class="font-semibold tabular-nums text-highlighted">{{ graded }}</span> de {{ rows.length }} calificados</span>
                <span
                    v-if="canEdit"
                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                    :class="failed ? 'bg-error/10 text-error' : saving ? 'bg-elevated text-muted' : 'bg-success/10 text-success'"
                >
                    {{ failed ? 'Hay cambios sin guardar' : saving ? 'Guardando…' : 'Todo guardado' }}
                </span>
            </div>
        </div>

        <EmptyState
            v-if="!moment"
            icon="i-lucide-calendar-x"
            title="El periodo no tiene momentos académicos"
            description="Crea los momentos del periodo para calificar Convivir."
        />
        <EmptyState
            v-else-if="rows.length === 0"
            icon="i-lucide-users"
            title="La sección no tiene estudiantes inscritos"
            description="Cuando haya estudiantes inscritos, aquí podrás calificar Convivir."
        />

        <template v-else>
            <UAlert v-if="readOnlyReason" :description="readOnlyReason" icon="i-lucide-lock" color="neutral" variant="subtle" class="mb-4" />
            <UAlert
                v-else-if="canCorrect && !moment.windowOpen"
                description="La carga de este momento está cerrada; con tu permiso de corrección puedes modificarla. Cada cambio queda registrado."
                icon="i-lucide-pencil-ruler"
                color="warning"
                variant="subtle"
                class="mb-4"
            />

            <ul class="divide-y divide-default overflow-hidden rounded-xl border border-default bg-default shadow-card">
                <li v-for="row in rows" :key="row.id" class="flex flex-col gap-2 px-5 py-3 sm:flex-row sm:items-center">
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-highlighted">{{ row.name }}</p>
                        <p v-if="errors[row.id]" class="text-xs text-error">{{ errors[row.id] }}</p>
                    </div>
                    <div class="flex gap-1.5" role="radiogroup" :aria-label="`Convivir de ${row.name}`">
                        <UButton
                            v-for="option in scale"
                            :key="option.letter"
                            role="radio"
                            :aria-checked="row.letter === option.letter"
                            :title="option.label"
                            :disabled="!canEdit"
                            size="sm"
                            class="w-9 justify-center font-bold"
                            :class="edited.has(row.id) && row.letter === option.letter ? 'ring-2 ring-warning ring-offset-1' : ''"
                            :color="row.letter === option.letter ? 'primary' : 'neutral'"
                            :variant="row.letter === option.letter ? 'solid' : 'outline'"
                            @click="choose(row, option.letter)"
                        >
                            {{ option.letter }}
                        </UButton>
                    </div>
                </li>
            </ul>

            <p class="mt-3 text-xs text-muted">
                <template v-for="(option, index) in scale" :key="option.letter"><template v-if="index > 0"> · </template><span class="font-semibold">{{ option.letter }}</span> {{ option.label }}</template>.
                Vuelve a pulsar una letra para quitarla. El contorno naranja marca una calificación modificada.
            </p>
        </template>
    </DashboardLayout>
</template>
