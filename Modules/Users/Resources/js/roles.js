import { ROLE_LABELS } from '@/Layouts/navigation';

// Badge color per Spatie role, shared by the Users pages.
const ROLE_COLORS = {
    'super-admin': 'warning',
    'staff/admin': 'primary',
    teacher: 'info',
    student: 'neutral',
};

export function roleLabel(role) {
    return role ? (ROLE_LABELS[role] ?? role) : 'Sin rol';
}

export function roleColor(role) {
    return ROLE_COLORS[role] ?? 'neutral';
}

// Select items for a list of role names.
export function roleItems(roles) {
    return roles.map((role) => ({ value: role, label: roleLabel(role) }));
}
