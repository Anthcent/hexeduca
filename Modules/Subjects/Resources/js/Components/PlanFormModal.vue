<script setup>
// Create or edit a study plan. A repeated code is allowed: the form warns
// (without blocking) and then asks for an observation to tell the plans apart.
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

const open = defineModel('open', { type: Boolean, default: false });

const props = defineProps({
    // null to create a plan.
    plan: { type: Object, default: null },
    // [{ id, code }] of every plan of the school, active and archived.
    planCodes: { type: Array, default: () => [] },
});

const form = useForm({ code: '', name: '', observation: '' });

watch(open, (isOpen) => {
    if (!isOpen) return;

    form.defaults({
        code: props.plan?.code ?? '',
        name: props.plan?.name ?? '',
        observation: props.plan?.observation ?? '',
    });
    form.reset();
    form.clearErrors();
});

const normalized = (code) => (code ?? '').trim().toLocaleLowerCase('es');

const duplicateCode = computed(() => {
    const code = normalized(form.code);

    return code !== '' && props.planCodes.some((item) => item.id !== props.plan?.id && normalized(item.code) === code);
});

const isEdit = computed(() => props.plan !== null);

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    };

    if (isEdit.value) {
        form.put(route('subjects.plans.update', props.plan.id), options);
    } else {
        form.post(route('subjects.plans.store'), options);
    }
}
</script>

<template>
    <UModal v-model:open="open" :title="isEdit ? 'Editar plan de estudio' : 'Nuevo plan de estudio'" :dismissible="!form.processing">
        <template #body>
            <form id="form-plan" class="space-y-5" novalidate @submit.prevent="submit">
                <UFormField label="Código oficial" name="code" required :error="form.errors.code" hint="Ej.: 31060">
                    <UInput v-model="form.code" :maxlength="50" size="lg" class="w-full" autofocus />
                </UFormField>

                <UAlert
                    v-if="duplicateCode"
                    color="warning"
                    variant="subtle"
                    icon="i-lucide-triangle-alert"
                    :title="`Ya existe un plan con el código ${form.code.trim()}`"
                    description="Puedes continuar. Agrega una observación para diferenciarlos."
                />

                <UFormField label="Nombre" name="name" required :error="form.errors.name">
                    <UInput v-model="form.name" :maxlength="150" size="lg" placeholder="Ej.: Bachillerato en Ciencias" class="w-full" />
                </UFormField>

                <UFormField
                    label="Observación"
                    name="observation"
                    :required="duplicateCode"
                    :error="form.errors.observation"
                    :hint="duplicateCode ? 'Obligatoria: el código se repite' : 'Opcional'"
                >
                    <UTextarea
                        v-model="form.observation"
                        :maxlength="500"
                        :rows="2"
                        autoresize
                        placeholder="Ej.: Versión 2024, jornada nocturna"
                        class="w-full"
                    />
                </UFormField>
            </form>
        </template>
        <template #footer>
            <div class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <UButton color="neutral" variant="ghost" class="justify-center" :disabled="form.processing" @click="open = false">Cancelar</UButton>
                <UButton type="submit" form="form-plan" icon="i-lucide-check" class="justify-center" :loading="form.processing">
                    {{ isEdit ? 'Guardar cambios' : 'Crear plan' }}
                </UButton>
            </div>
        </template>
    </UModal>
</template>
