// Date-only values arrive as "YYYY-MM-DD" or as an ISO timestamp at midnight UTC
// (Eloquent `date` casts). Only the calendar day matters, so parse those first
// ten characters as a local date to avoid a timezone shift of one day.

const dayFormatter = new Intl.DateTimeFormat('es', { day: 'numeric', month: 'short', year: 'numeric' });

export function toDay(value) {
    return value ? String(value).slice(0, 10) : '';
}

export function formatDay(value) {
    const day = toDay(value);

    if (!/^\d{4}-\d{2}-\d{2}$/.test(day)) {
        return value ?? '—';
    }

    const [year, month, date] = day.split('-').map(Number);

    return dayFormatter.format(new Date(year, month - 1, date));
}
