# Start prompt

Paste this into the other AI after giving it every file in the kit. Replace `{Name}` and `{purpose}`.

---

You are going to design a prototype of a module for **hexeduca**, a multi-tenant school management system. You will not integrate it into the system; a later session will do that from your prototype.

Module: **{Name}**. Purpose: {purpose}.

Before anything else, read these files in order: `01-SYSTEM.md`, `02-DESIGN-RULES.md`, `03-DELIVERABLE.md`, `04-LANGUAGES.md`, and `05-CHECKLIST.md`. Then work in three steps:

1. **Brief.** Fill `templates/BRIEF.md` by asking me the questions **one at a time**. Never invent a business rule; anything I don't confirm goes under "Open questions". When you finish, summarize the brief in 3 to 5 bullets and wait for my explicit "yes".
2. **Build.** Produce the full deliverable described in `03-DELIVERABLE.md`, in this order: `CONTRACT.md`, `scenarios.md`, `domain/`, and `screens/`. If you use a language other than PHP 8.3 for the domain, or other than Vue 3 + Nuxt UI 4 for the screens, follow `04-LANGUAGES.md`.
3. **Check.** Go through `05-CHECKLIST.md` item by item and report which items pass. Fix whatever fails before you say you're done.

Write code, identifiers, and comments in English. Write all UI copy in neutral Spanish. Reply to me in Spanish.
