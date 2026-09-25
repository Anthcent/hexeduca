// Plans may share an official code, so a plan always reads as its code plus
// its observation (when it has one), then its name.
export function planCode(plan) {
    if (!plan) return '';

    return plan.observation ? `${plan.code} · ${plan.observation}` : plan.code;
}

export function planLabel(plan) {
    if (!plan) return '';

    return `${planCode(plan)} — ${plan.name}`;
}

export const SCOPE_LABELS = {
    school: 'Colegio',
    grade_level: 'Año',
    offer: 'Sección',
};
