<script setup>
import { computed, nextTick, reactive, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({
    context: { type: Object, required: true },
    plan: { type: Object, required: true },
    sheet: { type: Object, required: true },
});

const PASS = 10;
const canEdit = computed(() => props.sheet.canEdit);

// Cells changed after their first entry get an orange outline, like the
// old system; the history panel tells who changed what.
const edited = reactive(new Set(props.sheet.edited ?? []));

// Correction mode (staff): reopen this plan outside its grading window.

function isoDate(date) {
    return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
}

const correctionOpen = ref(false);
const correctionForm = useForm({ reason: '', expires_on: isoDate(new Date(Date.now() + 3 * 86400000)) });

function openCorrection() {
    correctionForm.post(route('grades.correction.open', props.plan.id, false), {
        preserveScroll: true,
        onSuccess: () => {
            correctionOpen.value = false;
            correctionForm.reset('reason');
        },
    });
}

function closeCorrection() {
    router.delete(route('grades.correction.close', props.plan.id, false), { preserveScroll: true });
}

function formatDateTime(value) {
    if (!value) return '';

    return new Date(value).toLocaleString('es', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

// History panel, loaded on demand.

const historyOpen = ref(false);
const history = ref({ loading: false, changes: [], truncated: false, error: null });

async function showHistory() {
    historyOpen.value = true;
    history.value = { loading: true, changes: [], truncated: false, error: null };

    try {
        const { data } = await axios.get(route('grades.history', props.plan.id, false));
        history.value = { loading: false, changes: data.changes, truncated: data.truncated, error: null };
    } catch {
        history.value = { loading: false, changes: [], truncated: false, error: 'No se pudo cargar el historial.' };
    }
}

const ROLE_LABELS = { 'staff/admin': 'Personal', teacher: 'Docente' };

// Rows are local state: each save answers with the student's recomputed
// standing, which replaces the row's totals and final grade.
const rows = reactive(props.sheet.rows.map((row) => ({
    ...row,
    scores: { ...row.scores },
    draft: {},
})));

// Referent focus: all referentes, or just one to work column by column.
const focus = ref('all');
const visibleReferents = computed(() => props.plan.referents
    .map((referent, index) => ({ ...referent, index }))
    .filter((referent) => focus.value === 'all' || focus.value === referent.id));

// Editable columns, left to right: every visible indicator, then the extra.
const columns = computed(() => [
    ...visibleReferents.value.flatMap((r) => r.indicators.map((i) => ({ key: `i${i.id}`, indicatorId: i.id, max: i.maxPoints }))),
    { key: 'extra', indicatorId: null, max: null },
]);

// Cell save state: key `${studentId}:${columnKey}` → saving | saved | error.
const state = reactive({});
const errors = reactive({});
const timers = {};

// Responses can arrive out of order on a slow network. Every save gets a
// number per cell and per student; a response only applies when it answers
// the latest save, so an older answer never overwrites a newer grade.
let sequence = 0;
const latestForCell = {};
const latestForRow = {};

const saving = computed(() => Object.values(state).some((s) => s === 'saving'));
const failed = computed(() => Object.values(state).some((s) => s === 'error'));

function cellValue(row, column) {
    const key = column.key;

    if (key in row.draft) return row.draft[key];
    if (column.indicatorId === null) return row.extra ?? '';

    return row.scores[column.indicatorId] ?? '';
}

function maxFor(row, column) {
    return column.indicatorId === null ? row.maxExtra : column.max;
}

function onInput(row, column, event) {
    const raw = event.target.value.replace(/[^0-9]/g, '').slice(0, 2);
    event.target.value = raw;
    row.draft[column.key] = raw;

    const id = `${row.id}:${column.key}`;
    const max = maxFor(row, column);
    clearTimeout(timers[id]);

    if (raw !== '' && Number(raw) > max) {
        state[id] = 'error';
        errors[id] = column.indicatorId === null ? `Máximo ${max} para no pasar de 20` : `Máximo ${max}`;

        return;
    }

    delete errors[id];
    timers[id] = setTimeout(() => save(row, column, raw), 500);
}

async function save(row, column, raw) {
    const id = `${row.id}:${column.key}`;
    const ticket = ++sequence;
    latestForCell[id] = ticket;
    latestForRow[row.id] = ticket;
    state[id] = 'saving';

    try {
        const { data } = await axios.put(route('grades.record', props.plan.id, false), {
            student_id: row.id,
            indicator_id: column.indicatorId,
            points: raw === '' ? null : Number(raw),
        });

        if (latestForCell[id] !== ticket) return;

        // Same rule as the server: edited = the value changed after its first entry.
        const previous = column.indicatorId === null ? row.extra : (row.scores[column.indicatorId] ?? null);
        const next = raw === '' ? null : Number(raw);
        if (previous !== null && previous !== next) edited.add(`${row.id}:${column.key}`);

        if (column.indicatorId === null) {
            row.extra = raw === '' ? null : Number(raw);
        } else if (raw === '') {
            delete row.scores[column.indicatorId];
        } else {
            row.scores[column.indicatorId] = Number(raw);
        }

        if (row.draft[column.key] === raw) delete row.draft[column.key];

        state[id] = 'saved';

        // A newer save of another cell of this student is still on its way:
        // its answer carries the fresher totals.
        if (latestForRow[row.id] !== ticket) return;

        Object.assign(row, {
            referentTotals: data.row.referentTotals,
            average: data.row.average,
            maxExtra: data.row.maxExtra,
            final: data.row.final,
            passes: data.row.passes,
            complete: data.row.complete,
        });
    } catch (error) {
        if (latestForCell[id] !== ticket) return;

        state[id] = 'error';
        errors[id] = error.response?.data?.message ?? 'No se pudo guardar. Revisa tu conexión.';
    }
}

// Keyboard: Enter / ↓ next student, ↑ previous, ← → across cells when the
// caret is at the edge of the value.
function move(rowIndex, colIndex) {
    nextTick(() => {
        const cell = document.querySelector(`[data-cell="${rowIndex}-${colIndex}"]`);

        if (cell) {
            cell.focus();
            cell.select();
        }
    });
}

function onKeydown(event, rowIndex, colIndex) {
    const input = event.target;
    const atStart = input.selectionStart === 0 && input.selectionEnd === 0;
    const atEnd = input.selectionStart === input.value.length;

    if (event.key === 'Enter' || event.key === 'ArrowDown') {
        event.preventDefault();
        move(Math.min(rows.length - 1, rowIndex + 1), colIndex);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        move(Math.max(0, rowIndex - 1), colIndex);
    } else if (event.key === 'ArrowRight' && atEnd) {
        event.preventDefault();
        move(rowIndex, Math.min(columns.value.length - 1, colIndex + 1));
    } else if (event.key === 'ArrowLeft' && atStart) {
        event.preventDefault();
        move(rowIndex, Math.max(0, colIndex - 1));
    }
}

function twoDigits(value) {
    return value === null || value === undefined ? '—' : String(value).padStart(2, '0');
}

const loaded = computed(() => rows.filter((row) => row.complete).length);
const failing = computed(() => rows.filter((row) => row.final !== null && row.final < PASS).length);

function formatDate(value) {
    if (!value) return '';
    const [y, m, d] = value.split('-');

    return `${d}/${m}/${y}`;
}
</script>

<template>
    <Head :title="`Notas · ${context.subjectName}`" />

    <DashboardLayout active="notas">
        <PageHeader :eyebrow="`Carga de notas · ${context.momentName}`" :title="context.subjectName" :description="context.offerLabel" icon="i-lucide-table-2">
            <template #leading>
                <UButton :to="route('grades.index', undefined, false)" color="neutral" variant="link" icon="i-lucide-arrow-left" class="px-0">
                    Volver a notas
                </UButton>
            </template>
            <template #actions>
                <UButton
                    color="neutral"
                    variant="outline"
                    icon="i-lucide-history"
                    class="bg-white/10 text-white ring-white/25 hover:bg-white/20"
                    @click="showHistory"
                >
                    Historial
                </UButton>
                <UButton
                    :to="route('grades.plan', { offer: context.offerId, subject: context.subjectId, moment: context.momentId }, false)"
                    color="neutral"
                    variant="outline"
                    icon="i-lucide-list-tree"
                    class="bg-white/10 text-white ring-white/25 hover:bg-white/20"
                >
                    Plan de evaluación
                </UButton>
            </template>
        </PageHeader>

        <UAlert
            v-if="sheet.correction"
            class="mb-5"
            color="warning"
            variant="subtle"
            icon="i-lucide-pencil-ruler"
            :title="`Corrección abierta hasta el ${formatDateTime(sheet.correction.expiresAt)}`"
            :description="`Motivo: ${sheet.correction.reason}${sheet.correction.openedBy ? ` · Abierta por ${sheet.correction.openedBy}` : ''}. Cada cambio queda registrado con este motivo.`"
            :actions="sheet.canCorrect ? [{ label: 'Cerrar corrección', color: 'neutral', variant: 'outline', icon: 'i-lucide-lock', onClick: closeCorrection }] : []"
        />
        <UAlert
            v-else-if="!sheet.periodOpen"
            class="mb-5"
            color="neutral"
            variant="subtle"
            icon="i-lucide-lock"
            title="Periodo cerrado: solo lectura"
        />
        <UAlert
            v-else-if="!sheet.windowOpen"
            class="mb-5"
            color="warning"
            variant="subtle"
            icon="i-lucide-lock"
            title="La carga de notas de este momento está cerrada"
            :description="sheet.moment?.gradingOpensOn
                ? `La ventana de carga es del ${formatDate(sheet.moment.gradingOpensOn)} al ${formatDate(sheet.moment.gradingClosesOn)}.`
                : 'El momento todavía no tiene fechas de carga. Se definen en Momentos académicos.'"
        />
        <div v-if="sheet.canCorrect && !sheet.correction && (!sheet.periodOpen || !sheet.windowOpen)" class="-mt-3 mb-5">
            <UButton color="warning" variant="soft" icon="i-lucide-pencil-ruler" @click="correctionOpen = true">
                Abrir corrección para este plan
            </UButton>
        </div>

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <div class="flex flex-wrap gap-1.5" role="group" aria-label="Mostrar referentes">
                <UButton
                    size="sm"
                    class="rounded-full px-3"
                    :color="focus === 'all' ? 'primary' : 'neutral'"
                    :variant="focus === 'all' ? 'solid' : 'outline'"
                    :aria-pressed="focus === 'all'"
                    @click="focus = 'all'"
                >
                    Todos
                </UButton>
                <UButton
                    v-for="(referent, r) in plan.referents"
                    :key="referent.id"
                    size="sm"
                    class="rounded-full px-3"
                    :color="focus === referent.id ? 'primary' : 'neutral'"
                    :variant="focus === referent.id ? 'solid' : 'outline'"
                    :aria-pressed="focus === referent.id"
                    :title="referent.topic"
                    @click="focus = referent.id"
                >
                    R{{ r + 1 }}
                </UButton>
            </div>

            <div class="ms-auto flex items-center gap-4 text-sm">
                <span class="text-muted"><span class="font-semibold tabular-nums text-highlighted">{{ loaded }}</span> de {{ rows.length }} completos</span>
                <span v-if="failing > 0" class="text-error"><span class="font-semibold tabular-nums">{{ failing }}</span> reprobados</span>
                <span v-if="canEdit" class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium" :class="failed ? 'bg-error/10 text-error' : saving ? 'bg-elevated text-muted' : 'bg-success/10 text-success'">
                    <UIcon :name="failed ? 'i-lucide-circle-alert' : saving ? 'i-lucide-loader-circle' : 'i-lucide-cloud-check'" class="size-3.5" :class="saving && !failed ? 'animate-spin' : ''" />
                    {{ failed ? 'Hay celdas sin guardar' : saving ? 'Guardando…' : 'Todo guardado' }}
                </span>
            </div>
        </div>

        <EmptyState
            v-if="rows.length === 0"
            icon="i-lucide-users"
            title="La sección no tiene estudiantes inscritos"
            description="Cuando haya estudiantes inscritos en la sección, aparecerán aquí."
        />

        <div v-else class="overflow-auto rounded-xl border border-default bg-default shadow-card" style="max-height: 70vh">
            <table class="w-max min-w-full border-separate border-spacing-0 text-sm">
                <thead class="sticky top-0 z-20">
                    <tr>
                        <th rowspan="2" class="sticky left-0 z-30 min-w-56 border-b border-e border-default bg-elevated px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-muted">
                            Estudiante
                        </th>
                        <th
                            v-for="referent in visibleReferents"
                            :key="referent.id"
                            :colspan="referent.indicators.length + 1"
                            class="border-b border-e border-default bg-elevated px-3 py-2 text-left"
                        >
                            <p class="text-xs font-bold text-highlighted">R{{ referent.index + 1 }}</p>
                            <p class="max-w-56 truncate text-xs font-normal text-muted" :title="referent.topic">{{ referent.topic }}</p>
                        </th>
                        <th rowspan="2" class="border-b border-e border-default bg-elevated px-3 py-2 text-center text-xs font-semibold text-muted">
                            Extra
                        </th>
                        <th rowspan="2" class="border-b border-default bg-elevated px-3 py-2 text-center text-xs font-semibold text-muted">
                            Nota
                        </th>
                    </tr>
                    <tr>
                        <template v-for="referent in visibleReferents" :key="`h${referent.id}`">
                            <th
                                v-for="indicator in referent.indicators"
                                :key="indicator.id"
                                class="border-b border-default bg-elevated px-1 py-1.5 text-center text-xs font-semibold text-default"
                                :title="indicator.description"
                            >
                                {{ indicator.letter }}<span class="font-normal text-muted"> /{{ indicator.maxPoints }}</span>
                            </th>
                            <th class="border-b border-e border-default bg-elevated px-2 py-1.5 text-center text-xs font-semibold text-muted">Total</th>
                        </template>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, rowIndex) in rows" :key="row.id" class="group">
                        <th scope="row" class="sticky left-0 z-10 border-b border-e border-default bg-default px-4 py-1.5 text-left font-medium text-highlighted group-hover:bg-elevated">
                            <span class="me-2 inline-block w-6 text-xs tabular-nums text-muted">{{ rowIndex + 1 }}</span>{{ row.name }}
                        </th>

                        <template v-for="referent in visibleReferents" :key="`r${row.id}-${referent.id}`">
                            <td v-for="indicator in referent.indicators" :key="indicator.id" class="border-b border-default px-1 py-1 text-center group-hover:bg-elevated/50">
                                <input
                                    :value="cellValue(row, { key: `i${indicator.id}`, indicatorId: indicator.id })"
                                    :data-cell="`${rowIndex}-${columns.findIndex((c) => c.key === `i${indicator.id}`)}`"
                                    :disabled="!canEdit"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    :aria-label="`${row.name}, R${referent.index + 1} indicador ${indicator.letter}, máximo ${indicator.maxPoints}`"
                                    :title="errors[`${row.id}:i${indicator.id}`] ?? ''"
                                    class="h-8 w-11 rounded-md border bg-transparent text-center tabular-nums outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/30 disabled:cursor-default disabled:opacity-80"
                                    :class="{
                                        'border-error bg-error/10 text-error': state[`${row.id}:i${indicator.id}`] === 'error',
                                        'border-default': state[`${row.id}:i${indicator.id}`] !== 'error',
                                        'bg-success/10': state[`${row.id}:i${indicator.id}`] === 'saved',
                                        'ring-2 ring-warning/70': edited.has(`${row.id}:i${indicator.id}`) && state[`${row.id}:i${indicator.id}`] !== 'error',
                                    }"
                                    @input="onInput(row, { key: `i${indicator.id}`, indicatorId: indicator.id, max: indicator.maxPoints }, $event)"
                                    @keydown="onKeydown($event, rowIndex, columns.findIndex((c) => c.key === `i${indicator.id}`))"
                                    @focus="$event.target.select()"
                                />
                            </td>
                            <td class="border-b border-e border-default bg-elevated/40 px-2 text-center font-semibold tabular-nums text-default">
                                {{ row.referentTotals[referent.index] }}
                            </td>
                        </template>

                        <td class="border-b border-e border-default px-1 py-1 text-center">
                            <input
                                :value="cellValue(row, { key: 'extra', indicatorId: null })"
                                :data-cell="`${rowIndex}-${columns.length - 1}`"
                                :disabled="!canEdit"
                                inputmode="numeric"
                                autocomplete="off"
                                :placeholder="canEdit ? `≤${row.maxExtra}` : ''"
                                :aria-label="`${row.name}, nota extra, máximo ${row.maxExtra}`"
                                :title="errors[`${row.id}:extra`] ?? `Participación extracurricular. Máximo ${row.maxExtra} para no pasar de 20.`"
                                class="h-8 w-12 rounded-md border bg-transparent text-center tabular-nums outline-none transition-colors placeholder:text-xs placeholder:text-dimmed focus:border-primary focus:ring-2 focus:ring-primary/30 disabled:cursor-default disabled:opacity-80"
                                :class="{
                                    'border-error bg-error/10 text-error': state[`${row.id}:extra`] === 'error',
                                    'border-default': state[`${row.id}:extra`] !== 'error',
                                    'bg-success/10': state[`${row.id}:extra`] === 'saved',
                                    'ring-2 ring-warning/70': edited.has(`${row.id}:extra`) && state[`${row.id}:extra`] !== 'error',
                                }"
                                @input="onInput(row, { key: 'extra', indicatorId: null, max: null }, $event)"
                                @keydown="onKeydown($event, rowIndex, columns.length - 1)"
                                @focus="$event.target.select()"
                            />
                        </td>

                        <td class="border-b border-default px-3 text-center">
                            <span
                                class="inline-flex min-w-10 items-center justify-center rounded-md px-2 py-1 text-base font-bold tabular-nums"
                                :class="row.final === null ? 'text-dimmed' : row.final < PASS ? 'bg-error/10 text-error' : 'bg-success/10 text-success'"
                                :title="row.final !== null && !row.complete ? 'Faltan notas por cargar' : ''"
                            >
                                {{ twoDigits(row.final) }}
                            </span>
                            <span v-if="row.final !== null && !row.complete" class="ms-1 text-xs text-muted" aria-label="Incompleta">*</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p v-if="rows.length > 0" class="mt-3 text-xs text-muted">
            Enter o ↓ baja al siguiente estudiante · ↑ sube · ← → cambian de columna. Cada celda se guarda sola.
            La nota es el promedio de los referentes más la extra, redondeada, entre 01 y 20; de 01 a 09 es reprobado. * = faltan notas.
        </p>
        <UModal v-model:open="correctionOpen" title="Abrir corrección" description="Permite cargar y corregir notas de este plan fuera de la ventana de carga. Cada cambio queda registrado con el motivo." :dismissible="!correctionForm.processing">
            <template #body>
                <form id="form-correction" class="space-y-5" novalidate @submit.prevent="openCorrection">
                    <UFormField label="Motivo" name="reason" required :error="correctionForm.errors.reason ?? correctionForm.errors.correction">
                        <UTextarea v-model="correctionForm.reason" :maxlength="500" :rows="3" placeholder="Ej.: corrección de notas del R2 solicitada por coordinación" class="w-full" autofocus />
                    </UFormField>
                    <UFormField label="Abierta hasta (inclusive)" name="expires_on" required :error="correctionForm.errors.expires_on">
                        <UInput v-model="correctionForm.expires_on" type="date" class="w-full" />
                    </UFormField>
                </form>
            </template>
            <template #footer>
                <div class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <UButton color="neutral" variant="ghost" class="justify-center" :disabled="correctionForm.processing" @click="correctionOpen = false">Cancelar</UButton>
                    <UButton type="submit" form="form-correction" color="warning" icon="i-lucide-pencil-ruler" class="justify-center" :loading="correctionForm.processing">
                        Abrir corrección
                    </UButton>
                </div>
            </template>
        </UModal>

        <USlideover v-model:open="historyOpen" title="Historial de cambios" :description="`${context.subjectName} · ${context.offerLabel} · ${context.momentName}`">
            <template #body>
                <p v-if="history.loading" class="text-sm text-muted">Cargando…</p>
                <p v-else-if="history.error" class="text-sm text-error">{{ history.error }}</p>
                <p v-else-if="history.changes.length === 0" class="text-sm text-muted">Todavía no hay cambios registrados.</p>
                <ol v-else class="space-y-3">
                    <li v-for="change in history.changes" :key="change.id" class="rounded-lg border border-default p-3 text-sm">
                        <div class="flex items-center justify-between gap-2">
                            <p class="min-w-0 truncate font-semibold text-highlighted">{{ change.student }}</p>
                            <UBadge size="sm" color="neutral" variant="subtle">{{ change.cell }}</UBadge>
                        </div>
                        <p class="mt-1 font-mono tabular-nums">
                            <span class="text-muted">{{ change.old ?? '—' }}</span>
                            <UIcon name="i-lucide-arrow-right" class="mx-1.5 inline size-3.5 text-muted" />
                            <span class="font-semibold text-highlighted">{{ change.new ?? 'borrada' }}</span>
                        </p>
                        <p class="mt-1 text-xs text-muted">
                            {{ formatDateTime(change.at) }} · {{ change.by }}<template v-if="change.role"> ({{ ROLE_LABELS[change.role] ?? change.role }})</template>
                        </p>
                        <p v-if="change.correction !== null" class="mt-1.5 rounded-md bg-warning/10 px-2 py-1 text-xs text-warning">
                            En corrección: {{ change.correction }}
                        </p>
                    </li>
                </ol>
                <p v-if="history.truncated" class="mt-3 text-xs text-muted">Se muestran los 500 cambios más recientes.</p>
            </template>
        </USlideover>
    </DashboardLayout>
</template>
