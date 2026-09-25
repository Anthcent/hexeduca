<script setup>
import { computed } from 'vue';
import { parseDate } from '@internationalized/date';

// Date field bound to a plain "YYYY-MM-DD" string (what the backend expects),
// built on UInputDate with a calendar popover.
const model = defineModel({ type: String, default: '' });

defineProps({
    size: { type: String, default: 'lg' },
    disabled: { type: Boolean, default: false },
});

const value = computed({
    get() {
        if (!model.value) {
            return null;
        }

        try {
            return parseDate(String(model.value).slice(0, 10));
        } catch {
            return null;
        }
    },
    set(date) {
        model.value = date ? date.toString() : '';
    },
});
</script>

<template>
    <UInputDate v-model="value" :size="size" :disabled="disabled" class="w-full">
        <template #trailing>
            <UPopover :content="{ align: 'end' }">
                <UButton
                    color="neutral"
                    variant="link"
                    size="sm"
                    icon="i-lucide-calendar"
                    aria-label="Elegir fecha en el calendario"
                    class="px-0"
                    :disabled="disabled"
                />
                <template #content>
                    <UCalendar v-model="value" class="p-2" />
                </template>
            </UPopover>
        </template>
    </UInputDate>
</template>
