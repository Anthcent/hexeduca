// Shared UTable styling so every list reads the same: a tinted header row,
// 12px uppercase column labels and 14px cells.
export const tableUi = {
    thead: 'bg-elevated/60',
    th: 'px-5 py-3 text-xs font-semibold uppercase tracking-wide text-muted first:rounded-none',
    td: 'px-5 py-3.5 text-sm text-default',
    separator: 'bg-(--ui-border)',
    empty: 'p-0',
};
