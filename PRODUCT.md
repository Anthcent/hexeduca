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

Confiable, clara, familiar (trustworthy, clear, familiar). After three discarded custom directions, the confirmed direction is a deliberate Google Workspace replica (colors, typography, components) — the reasoning is that staff, teachers, and students already know how Gmail/Drive/Classroom work, and for a daily-use internal tool, familiarity beats a distinct visual identity. See DESIGN.md for the full rationale and exact specs.

## Anti-references

- Any custom brand hue outside Google's own blue/red/yellow/green — tried and explicitly rejected twice (a warm terracotta system, then a mixed modern-SaaS palette).
- Any tinted/warm/cream/beige background, even subtly — every draft that had one was rejected specifically for it. Background is `#ffffff` everywhere, no exceptions.
- Gradient hero-metric tiles, colored icon chips, generic identical card grids.
- Anything that reads as a bare Bootstrap/Tailwind default with no system behind it.

## Design Principles

- **Clarity over cleverness**: staff using this daily should never have to guess what an action does; label verbs plainly, keep forms and tables scannable.
- **Warm, not sterile**: color and type carry the "cálida" personality — this is not a cold enterprise tool, without tipping into playful/consumer.
- **Context is always visible**: because the app is multi-tenant (landlord vs. a specific school) and role-gated, the UI must always make it obvious which school/role context the user is currently in.
- **Own the components, don't fight a framework look**: use accessible headless primitives (shadcn-vue) styled with the project's own tokens, not an off-the-shelf themed component kit.
- **Consistency compounds**: every new module (academic, grades, schedule, notifications) reuses the same layout shell, components, and tokens rather than inventing its own patterns.

## Accessibility & Inclusion

WCAG AA baseline: sufficient color contrast (body text ≥4.5:1), full keyboard navigation, legible font sizes, visible focus states. No additional known accommodation requirements at this time.
