---
name: EDUCATIVO
description: Multi-tenant school management platform — Nova UI Kit (emerald, Vue 3 + Inertia + Tailwind).
colors:
  brand-50: "#edfdf6"
  brand-100: "#d4f8e8"
  brand-200: "#acf0d4"
  brand-300: "#76e2b9"
  brand-400: "#3ccb98"
  brand-500: "#18a879"
  brand-600: "#0f8561"
  brand-700: "#0b6b50"
  brand-800: "#0a5541"
  brand-900: "#094637"
  brand-950: "#04271f"
  ink: "#0c1814"
  lime: "#b7e35b"
  amber: "#f0a63b"
  sky: "#4ba6c8"
  canvas: "rgb(var(--canvas))"
  surface: "rgb(var(--surface))"
  surface-muted: "rgb(var(--surface-muted))"
  line: "rgb(var(--line))"
  text: "rgb(var(--text))"
  muted: "rgb(var(--muted))"
typography:
  display:
    fontFamily: "Manrope, Inter, ui-sans-serif, system-ui, sans-serif"
    fontWeight: 800
  body:
    fontFamily: "Inter, ui-sans-serif, system-ui, sans-serif"
    fontWeight: 400
rounded:
  xl: "0.75rem"
  "2xl": "1.25rem"
  "3xl": "1.75rem"
  pill: "999px"
components:
  button-primary:
    backgroundColor: "{colors.brand-500}"
    textColor: "#ffffff"
    rounded: "{rounded.xl}"
  card:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.text}"
    rounded: "{rounded.[24px]}"
---

# Design System: EDUCATIVO

## 1. Overview

**Creative North Star: "Nova" — the school's own control room**

This project's frontend is built directly on **Nova UI Kit** (`Nova_UI_Kit_Vue_Inertia_Tailwind`), a purpose-built Vue 3 + Inertia + Tailwind component library for school management systems, adopted wholesale per explicit direction: dark emerald identity, light/dark theme, persistent academic context (period/moment/year/section), collapsible sidebar, and a full component set (buttons, badges, modals, toasts, tables, timelines, roster, wizard).

This is the **fourth and final** direction after three superseded explorations (a custom warm-terracotta system, a "school registry/ledger" concept, and a literal Google Workspace clone) — all three are now fully replaced, not layered underneath. Nova's own token system (CSS custom properties for canvas/surface/line/text/muted, Tailwind `brand` color scale) is the single source of truth going forward.

**Key Characteristics:**
- Deep emerald (`brand-950` `#04271f` → `brand-500` `#18a879`) as the identity color — used for the auth panel, the "period" hero card, primary buttons, active nav state.
- Two-layer neutral system via CSS vars: `--canvas` (page backdrop) vs `--surface` (cards/panels) vs `--surface-muted` (nested/hover backgrounds) — each with a full light/dark pair.
- Manrope (display, 700-800 weight) for headings/titles; Inter (400-700) for everything else — loaded via Google Fonts in `resources/views/app.blade.php`.
- Persistent academic **context dock** (period / moment / year+section) docked under the top bar on every tenant-scoped screen, collapsible without navigating away.
- Icons from `lucide-vue-next`.
- Component library lives in `resources/js/Components/` (`Ui*.vue` base primitives) and `resources/js/Components/school/` (domain components: `SchoolContextBar`, `ResponsiveRoster`, `TrackingTimeline`, `AcademicWizard`, `SchoolModules`).

## 2. Colors

### Primary
- **Brand scale** (`brand-50` `#edfdf6` through `brand-950` `#04271f`): the identity. `brand-500` (`#18a879`) is the default interactive color (buttons, links, focus rings); `brand-950` is the "deep emerald" used for hero panels, the auth side panel, and the context dock's period card.

### Accent
- **Lime** (`#b7e35b`), **Amber** (`#f0a63b`), **Sky** (`#4ba6c8`): supporting accents for specific semantic buttons (warning/info) per `UiButton`'s variant set. Not used for brand identity.
- Standard Tailwind `emerald`/`red`/`amber`/`sky`/`violet`/`rose` scales back the rest of `UiButton`'s semantic variants (success/danger/warning/info/violet/coral) and `UiBadge`'s tones.

### Neutral (CSS custom properties, `resources/css/app.css`)
- `--canvas` — page backdrop. Light: `rgb(244 248 246)`. Dark: `rgb(5 18 14)`.
- `--surface` — cards, panels, the sidebar, dialogs. Light: `rgb(255 255 255)`. Dark: `rgb(11 31 24)`.
- `--surface-muted` — nested backgrounds, hover states, table headers. Light: `rgb(237 244 240)`. Dark: `rgb(16 45 34)`.
- `--line` — borders/dividers. Light: `rgb(216 228 222)`. Dark: `rgb(35 69 56)`.
- `--text` / `--muted` — primary/secondary text, each with a light/dark pair.

### Named Rules
**The Canvas/Surface Rule.** Never style the outermost app background directly with a Tailwind neutral; use `bg-[rgb(var(--canvas))]` (already the `body` default) and let `.surface` / `.section-card` carry every content layer on top. This is what makes light/dark mode swap correctly everywhere with zero per-component dark: overrides.

**The One Context Rule.** Every tenant-scoped screen keeps the academic context (period/moment/section) visible via `SchoolContextBar`, docked and collapsible — never hidden entirely, matching the "context is always visible" principle from earlier design work, now realized as a real, reusable component instead of a bespoke badge.

## 3. Typography

**Display Font:** Manrope (`font-display` Tailwind utility), 600-800 weight — page titles, card headings, the auth panel's headline.
**Body Font:** Inter (default `font-sans`), 400-700 weight — everything else: body text, table cells, form labels, button text.

Both loaded via Google Fonts (`resources/views/app.blade.php`); no substitution needed, unlike the prior Google Sans attempt — Inter and Manrope are both freely embeddable.

### Named Rules
**The Display/Body Split Rule.** `font-display` (Manrope) is reserved for headings and card titles (`<h1>`, `<h2>`, stat values). Never use it for body copy, table data, or form values — that's Inter's job.

## 4. Elevation

- **`.surface`** (`border + shadow-soft`): the baseline for any content layer at rest — `shadow-soft` is a diffuse, low-contrast shadow (`0 10px 30px rgba(7,62,45,.08)`), not a hard drop shadow.
- **`shadow-lift`**: hover states on interactive cards, and modals/drawers when open (`0 22px 55px rgba(5,45,34,.16)`).
- **`shadow-focus`**: focus rings on custom-styled interactive elements (`0 0 0 4px rgba(24,168,121,.18)`) — most controls instead use the `.control` class's built-in `focus:ring-4 focus:ring-brand-500/15`.

### Named Rules
**The Soft-By-Default Rule.** Every shadow in this system is diffuse and emerald-tinted (never a neutral/black drop shadow) — shadows carry the brand hue even at rest, which is part of what makes the kit read as a cohesive, non-generic system.

## 5. Components

All components live under `resources/js/Components/`, copied directly from Nova UI Kit and adapted only where Laravel/Inertia integration required it (e.g. `UiButton` gained a `type` prop so it can be used as a form submit button inside `UiModal`'s footer slot via the native `form="<id>"` attribute).

### Buttons (`UiButton.vue`)
- Props: `variant` (primary/secondary/soft/danger/success/warning/info/violet/coral/gradient/ghost/dark), `size` (sm/md/lg), `type`, `loading`, `iconOnly`, `disabled`.
- Shape: `rounded-xl` (sm: `rounded-lg`), heights 36/44/48px.
- `primary` is `bg-brand-500` with a matching emerald-tinted shadow — reserved for the one primary action per view.

### Badges (`UiBadge.vue`)
- Prop `tone` (neutral/brand/success/warning/danger/violet), optional `dot`. Fully rounded pill, tint background + matching text color, light/dark pair per tone.

### Modal (`UiModal.vue`)
- Teleported to `body`, backdrop blur, `role="dialog"` + `aria-modal`. Slots: default (body), `#description`, `#footer`. Closes on backdrop click or the `×` button, emits `close`.

### Switch (`UiSwitch.vue`)
- `v-model` boolean toggle, `role="switch"`, `aria-checked`, brand-500 when on.

### Toast (`UiToast.vue`)
- Teleported, bottom-right, deep-emerald (`bg-brand-950`) background — "popups tipo burbuja en verde profundo" per the kit's own README, not a generic white/gray toast.

### School domain components (`Components/school/`)
- **`SchoolContextBar.vue`**: the persistent period/moment/section switcher — three pill buttons, the first (period) styled as a deep-emerald "hero" pill.
- **`ResponsiveRoster.vue`**: student table on desktop, card list on mobile (`md:` breakpoint) — the canonical pattern for any tenant-scoped list that needs a mobile fallback.
- **`TrackingTimeline.vue`**: vertical process timeline with done/current/pending states, used for matrícula/document tracking.
- **`AcademicWizard.vue`** / **`SchoolModules.vue`**: multi-step enrollment wizard and a module-picker grid — available for future enrollment/onboarding screens, not yet wired into a real page.

### App shell (`resources/js/Layouts/DashboardLayout.vue`)
Adapted from the kit's `App.vue` demo shell into a real Inertia layout: fixed top bar (logo, search, dark-mode toggle, notifications, user chip), a collapsible desktop sidebar with **role-aware navigation** (landlord sees "Instituciones", tenant staff see "Matrículas"/"Base académica" — computed off `auth.user.role`), a mobile drawer + bottom tab bar, and the `SchoolContextBar` docked under the top bar (hidden entirely for the landlord, who has no tenant context).

### Auth shell (`resources/js/Layouts/AuthLayout.vue`)
Split-screen: a deep-emerald gradient panel with the brand mark and a one-line pitch on the left (hidden on mobile), the actual form in a `.section-card` on a `--canvas` background on the right — matches the login screenshot in the kit's own visual language.

## 6. Do's and Don'ts

### Do:
- **Do** use `.surface` / `.section-card` for every content layer, never style raw `bg-white`/`bg-gray-*` directly.
- **Do** use the `brand` color scale for identity; reach for `UiButton`'s semantic variants (success/danger/warning/info) for status actions, not ad-hoc colors.
- **Do** keep the academic context dock visible on every tenant screen; only the landlord view omits it (no tenant context to show).
- **Do** use `font-display` (Manrope) for headings only, `font-sans` (Inter, the default) for everything else.
- **Do** reuse `Ui*` components and `school/*` components before writing new markup — most CRUD screens (tables + create/edit dialog) map directly onto `section-card` + `UiModal` + `UiButton`.

### Don't:
- **Don't** reintroduce the prior Google Workspace blue/white tokens (`--blue`, `--canvas: #f2f2f2` flat gray, etc.) — fully superseded.
- **Don't** style dark mode per-component with `dark:` overrides when a CSS-var-backed utility (`bg-[rgb(var(--surface))]`) already handles both themes automatically.
- **Don't** use a neutral/black shadow — every shadow in this system is emerald-tinted and diffuse (`shadow-soft`/`shadow-lift`), never a hard black drop shadow.
- **Don't** hand-roll a modal/toast/switch when `UiModal`/`UiToast`/`UiSwitch` already exist.
