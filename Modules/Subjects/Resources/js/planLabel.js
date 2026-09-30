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

// One line for where a plan is used in a period: the whole school wins,
// otherwise the first targets plus how many more.
export function usageSummary(usage) {
    if (!usage || usage.length === 0) return '';
    if (usage.some((u) => u.scope === 'school')) return 'Todo el colegio';

    const labels = usage.map((u) => u.targetLabel);
    const shown = labels.slice(0, 2).join(', ');

    return labels.length > 2 ? `${shown} y ${labels.length - 2} más` : shown;
}

export function pluralize(count, one, many) {
    return `${count} ${count === 1 ? one : many}`;
}

export const SCOPE_LABELS = {
    school: 'Colegio',
    grade_level: 'Año',
    offer: 'Sección',
};
