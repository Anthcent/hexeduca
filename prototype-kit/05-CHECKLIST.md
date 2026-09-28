# Checklist before handoff

The other AI goes through this list and reports every item as pass or fail. You check it again before copying the prototype into the repo. A single fail means the prototype isn't ready.

## Brief

- [ ] Every section of `BRIEF.md` is answered, or explicitly marked out of scope.
- [ ] Every rule has an ID and was confirmed by the user.
- [ ] Unconfirmed items are under "Open questions", not in the logic.
- [ ] "Overlap with existing modules" is filled in, even if the answer is "none".

## Data

- [ ] No `school_id`, school selector, or school filter anywhere.
- [ ] Every entity says whether it is per period or across periods.
- [ ] Other modules' data is referenced by ID only, and no names or emails are copied.
- [ ] Only the fields listed in `01-SYSTEM.md` are read from other modules.
- [ ] Records with history get archived, not deleted.

## Contract

- [ ] Every use case lists who can run it, its input with types, the rules it applies, its result, its errors, and its event.
- [ ] Every screen has its query with the exact data shape.
- [ ] Every event has a stable name and a payload of IDs.

## Logic

- [ ] Every rule in the brief is enforced in `domain/`, not only in a screen.
- [ ] `domain/` has no framework, database, HTTP, or hidden clock.
- [ ] Every failure is a named error type.
- [ ] If the domain isn't in PHP, `NOTES.md` names the language, and the code follows `04-LANGUAGES.md`.

## Scenarios

- [ ] There is at least one scenario per rule.
- [ ] Every use case has a happy-path scenario.
- [ ] There is a scenario for the wrong role (forbidden).
- [ ] There is a scenario for another school's record (not found).
- [ ] There is a scenario for an archived record rejecting changes, if the module has archiving.
- [ ] There are scenarios for the validation boundaries (empty, too long, out of range).
- [ ] Every scenario uses concrete values.

## Screens

- [ ] Every page gets its data through props and never fetches anything.
- [ ] Actions are stubs named after the use cases.
- [ ] Every page has a header, an empty state, error messages, and a confirmation before archiving or deleting.
- [ ] There is no layout, sidebar, top bar, or login.
- [ ] Icons use static Lucide names.
- [ ] UI copy is in neutral Spanish.
- [ ] If the screens aren't in Vue + Nuxt UI, `NOTES.md` has the component mapping.

## Consistency

- [ ] Use cases, rules, and errors have the same names in the contract, the scenarios, the domain, and the screens.
