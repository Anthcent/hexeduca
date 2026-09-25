<script setup>
// The one page header recipe: a decorated banner with eyebrow, title,
// description and secondary actions. The page's primary action goes in the
// `notch` slot: the banner's bottom-right corner folds inward and the button
// sits in that notch, completing the rectangle with a small gap.
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useSlots } from 'vue';

const props = defineProps({
    eyebrow: { type: String, default: null },
    title: { type: String, required: true },
    description: { type: String, default: null },
    // Static `i-lucide-*` name drawn as a large decorative silhouette.
    icon: { type: String, default: 'i-lucide-graduation-cap' },
});

const slots = useSlots();
const hasNotch = computed(() => Boolean(slots.notch));

const RADIUS = 12;
const GAP = 8;

const banner = ref(null);
const notch = ref(null);
const clipPath = ref('none');
const notchSize = ref({ width: 0, height: 0 });

let observer = null;

function roundedRect(w, h, r) {
    return `M ${r},0 H ${w - r} A ${r} ${r} 0 0 1 ${w},${r} V ${h - r} A ${r} ${r} 0 0 1 ${w - r},${h} `
        + `H ${r} A ${r} ${r} 0 0 1 0,${h - r} V ${r} A ${r} ${r} 0 0 1 ${r},0 Z`;
}

// Outline of the banner with the bottom-right notch: convex corners where the
// banner meets the notch, and a concave corner inside it.
function notchedRect(w, h, nw, nh, r) {
    return `M ${r},0 H ${w - r} A ${r} ${r} 0 0 1 ${w},${r} `
        + `V ${h - nh - r} A ${r} ${r} 0 0 1 ${w - r},${h - nh} `
        + `H ${w - nw + r} A ${r} ${r} 0 0 0 ${w - nw},${h - nh + r} `
        + `V ${h - r} A ${r} ${r} 0 0 1 ${w - nw - r},${h} `
        + `H ${r} A ${r} ${r} 0 0 1 0,${h - r} V ${r} A ${r} ${r} 0 0 1 ${r},0 Z`;
}

function updateShape() {
    const el = banner.value;
    if (!el) {
        return;
    }

    const { width: w, height: h } = el.getBoundingClientRect();
    if (!hasNotch.value || !notch.value) {
        clipPath.value = `path('${roundedRect(w, h, RADIUS)}')`;
        return;
    }

    const button = notch.value.getBoundingClientRect();
    const nw = Math.min(button.width + GAP, w - 3 * RADIUS);
    const nh = Math.min(button.height + GAP, h - 3 * RADIUS);
    notchSize.value = { width: nw, height: nh };
    clipPath.value = `path('${notchedRect(w, h, nw, nh, RADIUS)}')`;
}

onMounted(async () => {
    await nextTick();
    updateShape();
    observer = new ResizeObserver(updateShape);
    observer.observe(banner.value);
    if (notch.value) {
        observer.observe(notch.value);
    }
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <header class="mb-6">
        <div v-if="$slots.leading" class="mb-3">
            <slot name="leading" />
        </div>

        <div class="page-banner-wrap relative">
            <div
                ref="banner"
                class="page-banner relative overflow-hidden px-6 py-4 text-white sm:px-8 sm:py-5"
                :style="{ clipPath }"
            >
                <!-- Decoration: glow, dotted texture, and the module silhouette. -->
                <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                    <div class="absolute -top-24 -right-16 size-72 rounded-full bg-brand-400/25 blur-3xl" />
                    <div class="absolute -bottom-28 left-1/3 size-64 rounded-full bg-amber/15 blur-3xl" />
                    <div class="page-banner-dots absolute inset-0 opacity-[.12]" />
                    <UIcon
                        :name="props.icon"
                        class="absolute -right-4 top-1/2 size-40 -translate-y-1/2 -rotate-12 text-white/10 sm:right-12"
                    />
                </div>

                <div class="relative flex min-h-full flex-col gap-3">
                    <div class="min-w-0 max-w-3xl">
                        <p v-if="eyebrow" class="mb-1.5 flex items-center gap-2 text-xs font-bold uppercase tracking-[.14em] text-brand-100">
                            <span class="h-[3px] w-5 shrink-0 rounded-full bg-amber" aria-hidden="true" />
                            {{ eyebrow }}
                        </p>
                        <h1 class="text-xl font-bold tracking-tight text-pretty sm:text-2xl sm:leading-tight">
                            {{ title }}
                        </h1>
                        <p v-if="description || $slots.description" class="mt-1 max-w-[65ch] text-sm text-brand-50/85 text-pretty">
                            <slot name="description">{{ description }}</slot>
                        </p>
                    </div>

                    <!-- Secondary actions or info, kept clear of the notch. -->
                    <div
                        v-if="$slots.actions"
                        class="page-banner-actions flex flex-wrap items-center gap-2"
                        :style="hasNotch ? { marginRight: `${notchSize.width}px`, minHeight: `${Math.max(notchSize.height - GAP, 0)}px` } : null"
                    >
                        <slot name="actions" />
                    </div>
                    <!-- On wide screens the text column sits beside the notch; on
                         narrow ones it needs its own row so it isn't covered. -->
                    <div v-else-if="hasNotch" class="sm:hidden" :style="{ height: `${Math.max(notchSize.height - GAP, 0)}px` }" />
                </div>
            </div>

            <div v-if="hasNotch" ref="notch" class="page-banner-notch absolute right-0 bottom-0">
                <slot name="notch" />
            </div>
        </div>
    </header>
</template>

<style scoped>
.page-banner {
    background: linear-gradient(135deg, var(--color-brand-950) 0%, var(--color-brand-800) 55%, var(--color-brand-600) 100%);
}

.page-banner-wrap {
    filter: drop-shadow(0 10px 24px rgb(2 44 34 / 0.18));
}

.page-banner-dots {
    background-image: radial-gradient(circle, white 1px, transparent 1.4px);
    background-size: 18px 18px;
    mask-image: linear-gradient(100deg, transparent 20%, black 70%);
}

/* The notch button fills its slot, shares the banner's corner radius, and
   is dark green with an amber icon. Unlayered CSS beats Tailwind utilities. */
.page-banner-notch :deep(> *) {
    border-radius: 12px;
    min-height: 2.75rem;
    padding-inline: 1.35rem;
    font-size: 0.9rem;
    font-weight: 700;
    background-color: var(--color-brand-950);
    color: white;
    box-shadow: inset 0 0 0 1px rgb(255 255 255 / 0.08);
}

.page-banner-notch :deep(> *:hover:not(:disabled)) {
    background-color: var(--color-brand-900, #06382c);
}

.page-banner-notch :deep(> *:disabled) {
    opacity: 0.55;
}

.page-banner-notch :deep(> * .iconify),
.page-banner-notch :deep(> * svg) {
    color: var(--color-amber);
}
</style>
