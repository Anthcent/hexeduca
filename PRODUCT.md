# Product

## Register

product

## Users

Administrative staff, teachers, and students at a school using EDUCATIVO day to day, on desktop primarily (admin/teacher back-office work) with mobile as a secondary context for students. Two distinct operating contexts:

- **Landlord (super-admin)**: platform operator managing schools (tenants) across the whole system, not scoped to any one school.
- **Tenant staff/admin, teachers, students**: work entirely inside their own school's subdomain, scoped to that school's data (grades, enrollment, schedule, etc.).

## Product Purpose

A multi-tenant school management system: enrollment, academic offerings, grading, scheduling, and role-based administration for one or many schools sharing the same platform. Success looks like staff completing routine tasks (enroll a student, assign a grade, manage a class roster) quickly and without confusion about which tenant/role context they're in.

## Brand Personality

Confiable, cálida, con carácter propio (trustworthy, warm, distinctly its own). After three discarded custom directions (warm-terracotta system, "school registry/ledger" concept, then a literal Google Workspace clone), the final and current direction is **Nova UI Kit** — a purpose-built Vue 3 + Inertia + Tailwind component library for school management, provided as a complete reference implementation and adopted wholesale. Deep emerald identity, persistent academic context, a real component library (not a from-scratch design system this project has to invent and maintain alone). See DESIGN.md for the full rationale and exact specs.

## Anti-references

- Any of the three prior discarded directions (terracotta product system, ledger/registry concept, Google Workspace blue/white clone) — all fully superseded, not layered underneath.
- Hand-rolled modals/toasts/switches/dialogs when the Nova kit's `Ui*` components already solve them.
- Per-component `dark:` override sprawl — the CSS-custom-property token system (`--canvas`/`--surface`/`--text`/`--muted`) already handles light/dark automatically; don't bypass it.
- A flat, single-layer neutral background — Nova's canvas/surface distinction is what gives the UI depth without heavy shadows.

## Design Principles

- **Clarity over cleverness**: staff using this daily should never have to guess what an action does; label verbs plainly, keep forms and tables scannable.
- **Context is always visible**: because the app is multi-tenant (landlord vs. a specific school) and role-gated, the persistent `SchoolContextBar` (period/moment/section) and role-aware navigation always make it obvious which school/role context the user is currently in.
- **Don't reinvent the kit**: Nova UI Kit is the source of truth for components and tokens. Extend it (as `UiButton`'s `type` prop was extended for Inertia form integration) rather than working around it or building parallel one-off components.
- **Consistency compounds**: every new module (academic, grades, schedule, notifications) reuses the same `DashboardLayout` shell, `Ui*`/`school/*` components, and CSS-var tokens rather than inventing its own patterns.

## Accessibility & Inclusion

WCAG AA baseline: sufficient color contrast (body text ≥4.5:1), full keyboard navigation, legible font sizes, visible focus states. No additional known accommodation requirements at this time.
