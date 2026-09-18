---
name: EDUCATIVO
description: Multi-tenant school management platform — literal Google Workspace UI Kit replica.
colors:
  brand: "#4285f4"
  blue: "#0b57d0"
  blue-hover: "#0842a0"
  blue-light: "#c2e7ff"
  red: "#ea4335"
  yellow: "#fbbc04"
  green: "#34a853"
  surface: "#ffffff"
  canvas: "#f2f2f2"
  on-surface: "#1f1f1f"
  on-surface-variant: "#747775"
  outline: "#dadce0"
  dark: "#131314"
typography:
  display1:
    fontFamily: "Google Sans, Roboto, Arial, ui-sans-serif, system-ui, sans-serif"
    fontSize: "60px"
    fontWeight: 600
    lineHeight: "72px"
  display2:
    fontFamily: "Google Sans, Roboto, Arial, ui-sans-serif, system-ui, sans-serif"
    fontSize: "48px"
    fontWeight: 600
    lineHeight: "56px"
  display3:
    fontFamily: "Google Sans, Roboto, Arial, ui-sans-serif, system-ui, sans-serif"
    fontSize: "36px"
    fontWeight: 600
    lineHeight: "44px"
  headline1:
    fontFamily: "Google Sans, Roboto, Arial, ui-sans-serif, system-ui, sans-serif"
    fontSize: "32px"
    fontWeight: 600
    lineHeight: "40px"
  headline2:
    fontFamily: "Google Sans, Roboto, Arial, ui-sans-serif, system-ui, sans-serif"
    fontSize: "28px"
    fontWeight: 600
    lineHeight: "36px"
  headline3:
    fontFamily: "Google Sans, Roboto, Arial, ui-sans-serif, system-ui, sans-serif"
    fontSize: "24px"
    fontWeight: 600
    lineHeight: "32px"
  title1:
    fontFamily: "Google Sans Text, Roboto, Arial, ui-sans-serif, system-ui, sans-serif"
    fontSize: "20px"
    fontWeight: 500
    lineHeight: "28px"
  title2:
    fontFamily: "Google Sans Text, Roboto, Arial, ui-sans-serif, system-ui, sans-serif"
    fontSize: "16px"
    fontWeight: 500
    lineHeight: "24px"
  body1:
    fontFamily: "Google Sans Text, Roboto, Arial, ui-sans-serif, system-ui, sans-serif"
    fontSize: "16px"
    fontWeight: 400
    lineHeight: "24px"
  body2:
    fontFamily: "Google Sans Text, Roboto, Arial, ui-sans-serif, system-ui, sans-serif"
    fontSize: "14px"
    fontWeight: 400
    lineHeight: "20px"
  caption:
    fontFamily: "Google Sans Text, Roboto, Arial, ui-sans-serif, system-ui, sans-serif"
    fontSize: "12px"
    fontWeight: 400
    lineHeight: "16px"
rounded:
  none: "0px"
  sm: "8px"
  md: "12px"
  lg: "16px"
  xl: "24px"
  pill: "999px"
spacing:
  "1": "4px"
  "2": "8px"
  "3": "16px"
  "4": "24px"
  "5": "32px"
  "6": "48px"
  "7": "64px"
components:
  button-filled:
    backgroundColor: "{colors.blue}"
    textColor: "#ffffff"
    rounded: "{rounded.pill}"
    padding: "10px 24px"
  button-filled-hover:
    backgroundColor: "{colors.blue-hover}"
  button-tonal:
    backgroundColor: "{colors.blue-light}"
    textColor: "{colors.blue}"
    rounded: "{rounded.pill}"
    padding: "10px 24px"
  button-text:
    backgroundColor: "transparent"
    textColor: "{colors.blue}"
    rounded: "{rounded.pill}"
    padding: "10px 16px"
  input-field:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.on-surface}"
    rounded: "{rounded.sm}"
    padding: "15px 13px 7px"
  card:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.on-surface}"
    rounded: "{rounded.md}"
    padding: "24px"
---

# Design System: EDUCATIVO

## 1. Overview

**Creative North Star: "Google Workspace UI Kit, applied exactly"**

The person running this project handed over Google's own Workspace UI Kit reference image (`ref.png`) with one instruction: apply it exactly as shown. This supersedes every prior exploration in this file's history (a warm-terracotta product system, a "school registry/ledger" concept, a colorful modern-SaaS treatment, and even an earlier "Google-inspired" pass that got some values wrong — `#1a73e8` instead of the kit's actual `#0B57D0` button blue, and an all-white canvas instead of the kit's real two-surface system). This version corrects those and locks to the reference's literal values.

The reference's own system has two blues, not one: `#4285F4` ("Primario", the brand mark itself) and `#0B57D0` ("Botones", every interactive filled surface). It also has two neutrals, not one: `#F2F2F2` canvas (the page backdrop, behind everything) and `#FFFFFF` surface (cards, panels, the sidebar, dialogs — anything that reads as "content sitting on the canvas"). Getting this two-layer neutral system right is what makes the earlier all-white passes read as flat; the canvas/surface contrast is what gives Drive, Gmail, and Calendar their sense of depth without using color or heavy shadows.

**Key Characteristics:**
- Two-layer neutral system: `#F2F2F2` canvas behind `#FFFFFF` content surfaces — never a single flat white for everything.
- Two-role blue: `#4285F4` brand mark only, `#0B57D0` for every button/interactive filled element.
- Google Sans / Google Sans Text at the kit's exact type scale (Display 1-3, Headline 1-3, Title 1-2, Body 1-2, Caption) — not an invented scale.
- 8px spacing system (4/8/16/24/32/48/64) and the kit's own radius scale (0/8/12/16/24).
- Material Symbols Outlined iconography — simple, single-weight line icons, never filled/duotone/gradient icons.
- A full Drive-style labeled sidebar (icon + label rows, a prominent "+ Nuevo" button at the top) — not a narrow icon-only rail.

## 2. Colors

The kit's official palette, values taken directly from `ref.png`, unmodified.

### Primary
- **Brand Blue** (#4285F4): the brand mark itself (the "E" app-icon tile) and nothing else. Not used for buttons or text.
- **Button Blue** (#0B57D0): every filled button, active nav-item background wash source color, link color, input focus border, checkbox/radio/switch "on" color.
- **Button Blue Hover** (#0842A0): hover/pressed state for Button Blue.
- **Primary Light** (#C2E7FF): selected-surface tint — active nav item background, focused-field backdrop, tonal button fill.

### Status (not brand — used only for state, never decoration)
- **Green** (#34A853): success, confirmed, "Éxito" per the kit.
- **Yellow** (#FBBC04): warning, "Advertencia" per the kit.
- **Red** (#EA4335): error, "Error / Acento" per the kit.

### Neutral
- **Canvas** (#F2F2F2): the page backdrop. Everything sits on top of this, nothing is styled directly against it except the outermost app shell.
- **Surface** (#FFFFFF): every content layer — sidebar, top bar, cards, tables, dialogs, tooltips-on-light. This is the "paper" the canvas holds.
- **On Surface** (#1F1F1F): primary text.
- **On Surface Variant** (#747775): secondary text, placeholders, helper text, icons at rest.
- **Outline** (#DADCE0): borders, dividers, input borders at rest.
- **Dark** (#131314): reserved for dark-theme text-on-light-surface inversions per the kit; this project's dark mode inverts canvas/surface rather than using this token directly (see implementation for the exact dark-mode ramp).

### Named Rules
**The Two-Layer Neutral Rule.** The app shell background is Canvas (#F2F2F2). Every piece of actual content — sidebar, cards, tables, dialogs — sits on a Surface (#FFFFFF) layer above it. Never skip the canvas layer and make the whole page pure white; that was tried and is explicitly wrong per the reference.

**The Two-Blue Rule.** Brand Blue (#4285F4) is reserved for the app's own mark/logo. Button Blue (#0B57D0) is reserved for everything interactive. Never use Brand Blue on a button, and never use Button Blue on the logo mark.

## 3. Typography

**Display Font:** Google Sans (headlines/display)
**Body Font:** Google Sans Text (titles, body, captions — the kit's own secondary family for UI text)
**Practical substitution:** neither Google Sans nor Google Sans Text is publicly licensed for web embedding. This project uses **Roboto** (Google's own, freely embeddable, and the historical basis of the Google Sans family) for both roles, at the kit's exact sizes/weights/line-heights below.

### Hierarchy (exact kit scale)
- **Display 1** (600, 60px/72px): reserved for marketing-style hero moments; unlikely to be used inside the authenticated app itself.
- **Display 2** (600, 48px/56px): same as above, smaller.
- **Display 3** (600, 36px/44px): largest heading this app is likely to actually use, for a rare full-bleed moment.
- **Headline 1** (600, 32px/40px): top-level page title on the largest screens.
- **Headline 2** (600, 28px/36px): standard page title (what "Resumen", "Catálogos académicos" use).
- **Headline 3** (600, 24px/32px): section headers within a page, dialog titles.
- **Title 1** (500, 20px/28px): card titles, prominent labels.
- **Title 2** (500, 16px/24px): table column group headers, form section labels.
- **Body 1** (400, 16px/24px): primary reading text, longer descriptions.
- **Body 2** (400, 14px/20px): the default UI text size — table cells, form values, most of the interface.
- **Caption** (400, 12px/16px): helper text, timestamps, field hints.

### Named Rules
**The Exact Scale Rule.** Use these eleven sizes and nothing in between. If a screen seems to need a size that doesn't exist in this list, it's a sign the hierarchy is being over-engineered — pick the nearest existing step.

## 4. Elevation

Flat-by-default with the kit's five-step E0-E4 elevation scale. At rest, most surfaces are E0 (no shadow, separated by the canvas/surface color contrast or a 1px Outline border). Shadow increases only for things that are temporarily "lifted" above the layout.

### Shadow Vocabulary
- **E0** (`box-shadow: none`): cards and panels at rest — separated from Canvas by being Surface-colored, optionally with a 1px Outline border.
- **E1** (`0 1px 2px 0 rgba(31,31,31,.15), 0 1px 3px 1px rgba(31,31,31,.10)`): interactive card/button hover.
- **E2** (`0 1px 2px 0 rgba(31,31,31,.20), 0 2px 6px 2px rgba(31,31,31,.12)`): the FAB at rest, raised toolbars.
- **E3** (`0 4px 8px 3px rgba(31,31,31,.12), 0 1px 3px rgba(31,31,31,.20)`): open menus/dropdowns.
- **E4** (`0 6px 10px 4px rgba(31,31,31,.12), 0 2px 3px rgba(31,31,31,.24)`): dialogs/modals, the topmost floating layer.

### Named Rules
**The Canvas-Does-The-Separating Rule.** Prefer letting the Canvas/Surface color contrast do the visual separation over adding a shadow. Reach for E1+ only when something is genuinely floating above the normal document flow (menu, dialog, dragged item, FAB).

## 5. Components

Build these with shadcn-vue primitives (Reka UI) restyled to the exact specs below — the interaction/accessibility layer is shadcn-vue's, the visual layer is the kit's.

### Buttons
- **Shape:** fully rounded (pill).
- **Filled:** Button Blue background, white text, 10px/24px padding — the single primary action per view ("Comenzar" in the kit's own example).
- **Tonal:** Primary Light (#C2E7FF) background, Button Blue text — a secondary-but-still-prominent action ("Contactar ventas").
- **Text:** transparent background, Button Blue text, no border — the lowest-emphasis action ("Más información", "Cancelar").
- **Icon button:** circular, transparent, On Surface Variant icon color, Canvas-tinted background on hover.
- **FAB:** large circle (56px), Button Blue background, white icon, E2 shadow at rest — reserved for the single most important creation action on a screen ("+ Nueva matrícula").

### Inputs / Fields
- **Style:** outlined text field with a floating label, exactly as before — Outline border at rest, 2px Button Blue border + floated label on focus.
- **Select / date / search:** same outlined shell; search fields get a leading magnifier icon and full pill radius instead of the 8px field radius.

### Chips
- **Shape:** fully rounded pill, with an optional leading icon/avatar and an optional trailing dismiss "×" (per the kit's "Etiqueta ×" example).
- **Style:** flat tint fill (status color's light tint) with matching text color, or a plain Surface + Outline border for a neutral/person/project chip.

### Cards
- **Corner Style:** 12px radius (rounded.md).
- **Background:** always Surface (#FFFFFF), sitting on Canvas.
- **Border:** 1px Outline at rest, no shadow (E0) unless the whole card is a single click target, in which case add E1 on hover.
- **Structure:** a leading icon (in Primary Light circle, colored Button Blue) or an app-logo-style square icon, a title, one line of supporting text, and one or two text-button links at the bottom — not a boxed "stat tile."

### Alerts / Notifications
- **Style:** full-width tinted row (status color's light tint), a leading filled circular status icon, title + body text, and a trailing dismiss "×". No border — the tint alone separates it from Canvas/Surface.

### Tooltips, Loaders & Progress
- **Tooltip:** small dark pill (`Dark` #131314 background, white text), appears on hover, positioned above/below the target.
- **Spinner:** circular indeterminate spinner in Button Blue.
- **Progress bar:** thin (4-6px) rounded track in Outline color, filled portion in Button Blue, with an optional percentage label.

### Navigation
- **Sidebar (Drive-style):** a full labeled sidebar (not an icon-only rail) — a prominent "+ Nuevo" filled/tonal button at the top, then a vertical list of icon+label rows. The active item gets a Primary Light pill background behind the full row (icon + label together), not just behind the icon.
- **Top bar:** Surface background, 1px bottom Outline border (or E0/no border if the sidebar already separates it), app mark + name left, search/utility icons + tenant/context chip + avatar right.
- **Tabs:** underline style — active tab gets a 2px Button Blue bottom border and Button Blue text, inactive tabs are On Surface Variant text.
- **Breadcrumb:** On Surface Variant text with `>` chevron separators, current page in On Surface, bold.

### Tables
- **Style:** Surface background, leading checkbox column for row selection, sortable column headers (small sort-direction arrow icon), a type/status icon in the first content column where relevant, and a trailing "⋮" overflow-menu button per row (not always-visible inline edit/delete icons).
- **Row hover:** Primary Light tint wash, revealing the "⋮" button.

### Empty States
- **Style:** centered, a large friendly icon/illustration inside a circle (Primary Light background, Button Blue icon), a Headline 3 message, one line of Body 2 supporting text, and one or two buttons (one filled, one tonal/outlined) — matching the kit's "Aún no hay archivos aquí" pattern exactly.

## 6. Do's and Don'ts

### Do:
- **Do** use the two-layer neutral system: Canvas (#F2F2F2) behind, Surface (#FFFFFF) for every content layer on top.
- **Do** keep Brand Blue (#4285F4) exclusive to the logo mark and Button Blue (#0B57D0) exclusive to interactive elements.
- **Do** use the kit's exact eleven-step type scale — no invented sizes.
- **Do** use the Drive-style labeled sidebar with a prominent "+ Nuevo" action, not a narrow icon-only rail.
- **Do** use a "⋮" overflow menu for row actions in tables, with a leading checkbox column and sortable headers.
- **Do** use shadcn-vue primitives restyled to these exact specs for anything interactive.

### Don't:
- **Don't** make the entire page pure white with no canvas layer — that was tried and explicitly corrected against this reference.
- **Don't** use Brand Blue (#4285F4) on a button, or Button Blue (#0B57D0) on the logo mark — they are not interchangeable.
- **Don't** introduce any custom brand hue outside this exact palette.
- **Don't** invent typography sizes outside the eleven-step scale.
- **Don't** replicate Google's logo/wordmark itself — only the color system, typography, and component shapes. The app's own name and mark stay "Educativo."
