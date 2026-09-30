import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/*
 * Sidebar, command palette and quick actions for DashboardLayout.vue.
 *
 * Every entry is filtered by what the current user can actually open, so no
 * link leads to a 403: core entries by role (mirroring the `role:` middleware
 * of their routes) and module availability (the `modules` shared prop);
 * module entries (`moduleNav`) arrive already filtered by the backend.
 *
 * Icon names must stay literal `i-lucide-*` strings: Vite bundles only the
 * names it finds in the source, and anything else is fetched at runtime from
 * api.iconify.design.
 */

const STAFF = 'staff/admin';
const TEACHER = 'teacher';
const SUPER_ADMIN = 'super-admin';

// Sidebar groups, in display order. Groups flagged `separated` render after a
// divider. A group with a single visible entry renders it as a plain link.
const GROUPS = [
    { key: 'inicio', label: 'Inicio' },
    { key: 'plataforma', label: 'Plataforma', icon: 'i-lucide-building-2' },
    { key: 'academico', label: 'Académico', icon: 'i-lucide-graduation-cap' },
    { key: 'comunicacion', label: 'Comunicación', icon: 'i-lucide-megaphone' },
    { key: 'documentos', label: 'Documentos', icon: 'i-lucide-folder-open' },
    { key: 'mas', label: 'Más', icon: 'i-lucide-blocks' },
    { key: 'administracion', label: 'Administración', icon: 'i-lucide-shield-check', separated: true },
];
const DEFAULT_MODULE_GROUP = 'mas';

// `roles` mirrors each route's `role:` middleware (see Modules/*/routes/web.php);
// `module` is the module key the route is gated by.
const TENANT_ITEMS = [
    { key: 'resumen', label: 'Inicio', icon: 'i-lucide-layout-dashboard', route: 'dashboard', group: 'inicio' },
    { key: 'matriculas', label: 'Matrículas', icon: 'i-lucide-list-checks', route: 'academic.matriculas.create', group: 'academico', roles: [STAFF], module: 'academic' },
    { key: 'academico', label: 'Base académica', icon: 'i-lucide-library', route: 'academic.catalogos', group: 'academico', roles: [STAFF], module: 'academic' },
    { key: 'ofertas', label: 'Ofertas académicas', icon: 'i-lucide-book-open', route: 'academic.ofertas.create', group: 'academico', roles: [STAFF], module: 'academic' },
    { key: 'momentos', label: 'Momentos académicos', icon: 'i-lucide-calendar-range', route: 'academic-moments.index', group: 'academico', roles: [STAFF], module: 'academicmoments' },
    { key: 'notas', label: 'Notas', icon: 'i-lucide-clipboard-check', route: 'grades.index', group: 'academico', roles: [TEACHER, STAFF], module: 'grades' },
    { key: 'usuarios', label: 'Usuarios', icon: 'i-lucide-users', route: 'users.index', group: 'administracion', roles: [STAFF, SUPER_ADMIN] },
];

const LANDLORD_ITEMS = [
    { key: 'resumen', label: 'Inicio', icon: 'i-lucide-layout-dashboard', route: 'dashboard', group: 'inicio' },
    { key: 'instituciones', label: 'Instituciones', icon: 'i-lucide-school', route: 'admin.schools.index', group: 'plataforma', roles: [SUPER_ADMIN] },
    { key: 'modulos', label: 'Módulos', icon: 'i-lucide-blocks', route: 'admin.modules.index', group: 'plataforma', roles: [SUPER_ADMIN] },
    { key: 'usuarios', label: 'Usuarios', icon: 'i-lucide-users', route: 'users.index', group: 'administracion', roles: [STAFF, SUPER_ADMIN] },
];

// Create actions for the "Acción rápida" menu and the command palette.
const QUICK_ACTIONS = [
    { label: 'Nuevo anuncio', description: 'Publicar un aviso', icon: 'i-lucide-megaphone', route: 'notifications.create', module: 'notifications', permission: 'notifications.send' },
    { label: 'Subir archivo', description: 'Agregar al repositorio', icon: 'i-lucide-upload', route: 'files.index', module: 'files', permission: 'files.upload' },
    { label: 'Nueva matrícula', description: 'Inscribir a un estudiante', icon: 'i-lucide-list-plus', route: 'academic.matriculas.create', module: 'academic', roles: [STAFF] },
    { label: 'Nueva oferta académica', description: 'Abrir una oferta', icon: 'i-lucide-book-plus', route: 'academic.ofertas.create', module: 'academic', roles: [STAFF] },
    { label: 'Cargar notas', description: 'Planes de evaluación y carga por momento', icon: 'i-lucide-clipboard-pen', route: 'grades.index', module: 'grades', roles: [TEACHER, STAFF] },
    { label: 'Nuevo usuario', description: 'Crear una cuenta', icon: 'i-lucide-user-plus', route: 'users.create', roles: [STAFF, SUPER_ADMIN] },
];

// Module manifests name icons in PascalCase (module.json `navigation[].icon`).
const MODULE_ICONS = {
    Bell: 'i-lucide-bell',
    Blocks: 'i-lucide-blocks',
    BookOpen: 'i-lucide-book-open',
    Calendar: 'i-lucide-calendar',
    CalendarRange: 'i-lucide-calendar-range',
    ClipboardCheck: 'i-lucide-clipboard-check',
    FileText: 'i-lucide-file-text',
    FolderOpen: 'i-lucide-folder-open',
    GraduationCap: 'i-lucide-graduation-cap',
    LayoutDashboard: 'i-lucide-layout-dashboard',
    ListChecks: 'i-lucide-list-checks',
    Megaphone: 'i-lucide-megaphone',
    MessageSquare: 'i-lucide-message-square',
    Receipt: 'i-lucide-receipt',
    School: 'i-lucide-school',
    Search: 'i-lucide-search',
    Settings: 'i-lucide-settings',
    Users: 'i-lucide-users',
};
const FALLBACK_MODULE_ICON = 'i-lucide-blocks';

export const ROLE_LABELS = {
    [STAFF]: 'Administración',
    [TEACHER]: 'Docente',
    student: 'Estudiante',
    [SUPER_ADMIN]: 'Superadministrador',
};

// Keeps links relative so Nuxt UI treats them as Inertia visits, not external links.
export function toPath(href) {
    try {
        const url = new URL(href, window.location.origin);

        return url.origin === window.location.origin ? `${url.pathname}${url.search}` : href;
    } catch {
        return href;
    }
}

function routePath(name) {
    return route().has(name) ? route(name, undefined, false) : null;
}

function currentPathname(pageUrl) {
    return (pageUrl ?? '').split('?')[0].replace(/\/+$/, '') || '/';
}

// "/notifications/create" belongs to "/notifications"; "/" only matches itself.
function pathMatches(itemPath, current) {
    const base = itemPath.split('?')[0].replace(/\/+$/, '') || '/';

    return base === current || (base !== '/' && current.startsWith(`${base}/`));
}

export function useShellNavigation(activeKey) {
    const page = usePage();

    const user = computed(() => page.props.auth?.user ?? null);
    const role = computed(() => user.value?.role ?? null);
    const permissions = computed(() => user.value?.permissions ?? []);
    const modules = computed(() => page.props.modules ?? []);
    const isLandlord = computed(() => role.value === SUPER_ADMIN);

    function allowed(entry) {
        if (entry.roles && !entry.roles.includes(role.value)) return false;
        if (entry.module && !modules.value.includes(entry.module)) return false;
        if (entry.permission && !permissions.value.includes(entry.permission)) return false;

        return true;
    }

    const entries = computed(() => {
        const core = (isLandlord.value ? LANDLORD_ITEMS : TENANT_ITEMS)
            .filter(allowed)
            .map((item) => ({ ...item, to: routePath(item.route) }))
            .filter((item) => item.to !== null);

        const fromModules = (page.props.moduleNav ?? []).map((item) => ({
            key: `module:${item.key}`,
            label: item.label,
            icon: MODULE_ICONS[item.icon] ?? FALLBACK_MODULE_ICON,
            group: GROUPS.some((group) => group.key === item.group) ? item.group : DEFAULT_MODULE_GROUP,
            to: toPath(item.href),
        }));

        const groupIndex = (entry) => GROUPS.findIndex((group) => group.key === entry.group);

        // Stable sort: keeps declaration order inside each group.
        return [...core, ...fromModules].sort((a, b) => groupIndex(a) - groupIndex(b));
    });

    // The current URL decides the active entry; the page's `active` key is the
    // fallback for pages outside any entry (e.g. Welcome.vue).
    const activeEntryKey = computed(() => {
        const current = currentPathname(page.url);
        const byUrl = entries.value
            .filter((entry) => pathMatches(entry.to, current))
            .sort((a, b) => b.to.length - a.to.length)[0];

        return byUrl?.key ?? activeKey.value;
    });

    const activeClass = 'after:absolute after:start-0 after:inset-y-2 after:w-[3px] after:rounded-full after:bg-amber';

    function navLink(entry) {
        const active = entry.key === activeEntryKey.value;

        return { label: entry.label, icon: entry.icon, to: entry.to, active, class: active ? activeClass : undefined };
    }

    // UNavigationMenu items: a list per divider-separated block.
    function navigationLists(collapsed) {
        const blocks = [[], []];

        for (const group of GROUPS) {
            const items = entries.value.filter((entry) => entry.group === group.key).map(navLink);

            if (items.length === 0) continue;

            const block = blocks[group.separated ? 1 : 0];

            if (items.length === 1 || group.key === 'inicio') {
                block.push(...items);
                continue;
            }

            const hasActive = items.some((item) => item.active);

            block.push({
                label: group.label,
                icon: group.icon,
                defaultOpen: hasActive,
                // Expanded, the open group already shows its active child.
                active: collapsed && hasActive,
                class: collapsed && hasActive ? activeClass : undefined,
                children: items,
            });
        }

        return blocks.filter((block) => block.length > 0);
    }

    const quickActions = computed(() => QUICK_ACTIONS
        .filter(allowed)
        .map((action) => ({ label: action.label, description: action.description, icon: action.icon, to: routePath(action.route) }))
        .filter((action) => action.to !== null));

    const searchGroups = computed(() => {
        const groupLabel = (key) => GROUPS.find((group) => group.key === key)?.label;
        const groups = [{
            id: 'navigation',
            label: 'Ir a',
            items: entries.value.map((entry) => ({
                label: entry.label,
                icon: entry.icon,
                to: entry.to,
                suffix: entry.group === 'inicio' ? undefined : groupLabel(entry.group),
            })),
        }];

        if (quickActions.value.length > 0) {
            groups.push({ id: 'actions', label: 'Acciones', items: quickActions.value });
        }

        return groups;
    });

    return { user, role, isLandlord, navigationLists, quickActions, searchGroups };
}
