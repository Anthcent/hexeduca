<script setup>
defineProps({ modelValue: Boolean, disabled: Boolean })
defineEmits(['update:modelValue'])
</script>
<template>
  <button
    type="button"
    role="switch"
    :aria-checked="modelValue"
    :disabled="disabled"
    class="relative h-7 w-12 rounded-full p-1 transition disabled:opacity-50"
    :class="modelValue ? 'bg-brand-500' : 'bg-slate-300 dark:bg-slate-600'"
    @click="$emit('update:modelValue', !modelValue)"
  >
    <span class="block size-5 rounded-full bg-white shadow-xs transition-transform" :class="modelValue && 'translate-x-5'" />
  </button>
</template>
