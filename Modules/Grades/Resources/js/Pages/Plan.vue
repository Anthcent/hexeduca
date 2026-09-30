<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

const props = defineProps({
    context: { type: Object, required: true },
    plan: { type: Object, default: null },
    locked: { type: Boolean, default: false },
    periodOpen: { type: Boolean, default: true },
    limits: { type: Object, required: true },
});

const POINTS = props.limits.points;
const letter = (index) => String.fromCharCode(65 + index);

function blankReferent() {
    return {
        topic: '',
        technique: '',
        indicators: [
            { description: '', maxPoints: 7 },
            { description: '', maxPoints: 7 },
            { description: '', maxPoints: 6 },
        ],
    };
}

const form = useForm({
    offer_id: props.context.offerId,
    subject_id: props.context.subjectId,
    moment_id: props.context.momentId,
    referents: props.plan
        ? props.plan.referents.map((r) => ({
            topic: r.topic,
            technique: r.technique ?? '',
            indicators: r.indicators.map((i) => ({ description: i.description, maxPoints: i.maxPoints })),
        }))
        : [blankReferent()],
});

const readOnly = computed(() => !props.periodOpen);
// With grades loaded, only texts can change.
const shapeLocked = computed(() => props.locked || readOnly.value);

function sum(referent) {
    return referent.indicators.reduce((total, i) => total + (Number(i.maxPoints) || 0), 0);
}

function sumState(referent) {
    const total = sum(referent);

    if (total === POINTS) return { color: 'success', text: 'text-success', label: 'Completo' };
    if (total > POINTS) return { color: 'error', text: 'text-error', label: `Sobran ${total - POINTS}` };

    return { color: 'warning', text: 'text-warning', label: `Faltan ${POINTS - total}` };
}

const allComplete = computed(() => form.referents.every((r) => sum(r) === POINTS));

function addReferent() {
    form.referents.push(blankReferent());
}

function removeReferent(index) {
    form.referents.splice(index, 1);
}

function addIndicator(referent) {
    referent.indicators.push({ description: '', maxPoints: Math.max(1, Math.min(POINTS, POINTS - sum(referent))) || 1 });
}

function removeIndicator(referent, index) {
    referent.indicators.splice(index, 1);
}

// Splits the 20 points as evenly as possible: 20 in 3 → 7, 7, 6.
function distribute(referent) {
    const count = referent.indicators.length;
    const base = Math.floor(POINTS / count);
    const rest = POINTS % count;

    referent.indicators.forEach((indicator, i) => {
        indicator.maxPoints = base + (i < rest ? 1 : 0);
    });
}

const saveError = computed(() => form.errors.referents ?? Object.values(form.errors)[0] ?? null);

function save() {
    form.put(route('grades.plan.save', undefined, false), { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Plan · ${context.subjectName}`" />

    <DashboardLayout active="notas">
        <PageHeader :eyebrow="`Plan de evaluación · ${context.momentName}`" :title="context.subjectName" :description="context.offerLabel" icon="i-lucide-list-tree">
            <template #leading>
                <UButton :to="route('grades.index', undefined, false)" color="neutral" variant="link" icon="i-lucide-arrow-left" class="px-0">
                    Volver a notas
                </UButton>
            </template>
            <template v-if="plan" #actions>
                <UButton
                    :to="route('grades.sheet', plan.id, false)"
                    color="neutral"
                    variant="outline"
                    icon="i-lucide-table-2"
                    class="bg-white/10 text-white ring-white/25 hover:bg-white/20"
                >
                    Ir a la carga de notas
                </UButton>
            </template>
            <template v-if="!readOnly" #notch>
                <UButton icon="i-lucide-check" size="lg" :loading="form.processing" :disabled="!allComplete" @click="save">
                    {{ plan ? 'Guardar plan' : 'Crear plan' }}
                </UButton>
            </template>
        </PageHeader>

        <UAlert
            v-if="readOnly"
            class="mb-5"
            color="neutral"
            variant="subtle"
            icon="i-lucide-lock"
            title="Periodo cerrado: solo lectura"
            description="El plan de un periodo cerrado se conserva como historial."
        />
        <UAlert
            v-else-if="locked"
            class="mb-5"
            color="info"
            variant="subtle"
            icon="i-lucide-info"
            title="El plan ya tiene notas cargadas"
            description="Puedes corregir temas, técnicas y descripciones. Los referentes, indicadores y puntos quedan fijos para no alterar las notas."
        />
        <UAlert v-if="saveError" class="mb-5" color="error" variant="subtle" icon="i-lucide-circle-alert" :title="saveError" />

        <p class="mb-5 max-w-3xl text-sm text-muted">
            Cada referente vale {{ POINTS }} puntos, repartidos entre sus indicadores. La nota del momento es el promedio de los referentes más la nota extra, con tope en 20.
        </p>

        <div class="space-y-5">
            <section
                v-for="(referent, r) in form.referents"
                :key="r"
                class="overflow-hidden rounded-xl border bg-default shadow-card"
                :class="sum(referent) === POINTS ? 'border-default' : 'border-warning/50'"
            >
                <header class="flex flex-wrap items-center gap-3 border-b border-default bg-elevated/50 px-5 py-3">
                    <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary text-sm font-bold text-inverted">R{{ r + 1 }}</span>
                    <div class="grid min-w-0 flex-1 gap-2 sm:grid-cols-2">
                        <UInput v-model="referent.topic" :disabled="readOnly" :maxlength="200" placeholder="Tema del referente" :aria-label="`Tema del referente ${r + 1}`" />
                        <UInput v-model="referent.technique" :disabled="readOnly" :maxlength="200" placeholder="Técnica (opcional)" :aria-label="`Técnica del referente ${r + 1}`" />
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-bold tabular-nums" :class="sumState(referent).text">{{ sum(referent) }} / {{ POINTS }}</span>
                        <UBadge size="sm" :color="sumState(referent).color" variant="subtle">{{ sumState(referent).label }}</UBadge>
                        <UButton
                            v-if="!shapeLocked && form.referents.length > 1"
                            size="sm"
                            color="neutral"
                            variant="ghost"
                            icon="i-lucide-trash-2"
                            :aria-label="`Quitar el referente ${r + 1}`"
                            @click="removeReferent(r)"
                        />
                    </div>
                </header>

                <ul class="divide-y divide-default">
                    <li v-for="(indicator, i) in referent.indicators" :key="i" class="flex items-center gap-3 px-5 py-2.5">
                        <span class="grid size-7 shrink-0 place-items-center rounded-md bg-elevated text-xs font-bold text-highlighted">{{ letter(i) }}</span>
                        <UInput
                            v-model="indicator.description"
                            :disabled="readOnly"
                            :maxlength="300"
                            placeholder="Qué se evalúa"
                            class="min-w-0 flex-1"
                            :aria-label="`Descripción del indicador ${letter(i)} del referente ${r + 1}`"
                        />
                        <UInputNumber
                            v-model="indicator.maxPoints"
                            :min="1"
                            :max="POINTS"
                            :disabled="shapeLocked"
                            class="w-28"
                            :aria-label="`Puntos del indicador ${letter(i)}`"
                        />
                        <span class="hidden text-xs text-muted sm:inline">pts</span>
                        <UButton
                            v-if="!shapeLocked && referent.indicators.length > 1"
                            size="sm"
                            color="neutral"
                            variant="ghost"
                            icon="i-lucide-x"
                            :aria-label="`Quitar el indicador ${letter(i)}`"
                            @click="removeIndicator(referent, i)"
                        />
                    </li>
                </ul>

                <footer v-if="!shapeLocked" class="flex flex-wrap gap-2 border-t border-default px-5 py-2.5">
                    <UButton
                        size="sm"
                        color="neutral"
                        variant="ghost"
                        icon="i-lucide-plus"
                        :disabled="referent.indicators.length >= limits.indicators"
                        @click="addIndicator(referent)"
                    >
                        Agregar indicador
                    </UButton>
                    <UButton size="sm" color="neutral" variant="ghost" icon="i-lucide-scale" @click="distribute(referent)">
                        Repartir {{ POINTS }} en partes iguales
                    </UButton>
                </footer>
            </section>

            <UButton
                v-if="!shapeLocked"
                color="neutral"
                variant="outline"
                icon="i-lucide-plus"
                block
                :disabled="form.referents.length >= limits.referents"
                @click="addReferent"
            >
                Agregar referente
            </UButton>
        </div>
    </DashboardLayout>
</template>
