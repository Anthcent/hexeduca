# Deliverable

One folder named after the module in PascalCase (for example `Attendance/`), with this structure:

```
{Name}/
  BRIEF.md          from templates/BRIEF.md
  CONTRACT.md       from templates/CONTRACT.md
  scenarios.md      from templates/scenarios.md
  domain/
    Entities/
    ValueObjects/
    Services/
    Exceptions/
  screens/
    {Screen}.vue    one per page
  NOTES.md          optional: language used, decisions, anything the repo session should know
```

If the parts disagree, this order decides which one is right:

1. `scenarios.md`
2. `CONTRACT.md`
3. `domain/`
4. `screens/`

Keep them consistent: the same names for use cases, rules, and errors everywhere.

## `BRIEF.md`

Use `templates/BRIEF.md`. It holds the purpose, roles, entities, rules, scope, dependencies, events, screens, navigation, overlap with existing modules, out of scope, and open questions.

## `CONTRACT.md`

Use `templates/CONTRACT.md`:

- **Data model:** each table with its columns, types, nullability, and references.
- **Use cases:** one block per action, with who can run it, its input, the rules it applies, its result, its errors, and its event.
- **Queries:** the data each screen needs, with the exact shape.

## `scenarios.md`

Use `templates/scenarios.md`. Write Given/When/Then examples: at least one per rule, plus the mandatory ones listed in the template. The scenarios become the module's automated tests, so each one must be concrete: real values, one action, and one expected result.

## `domain/`

The business logic, without a framework (see `templates/domain-example.php`):

- **Entities:** classes whose methods enforce the rules. For example, `markAbsent()` fails if the sheet is closed.
- **Value objects:** immutable, validated when created.
- **Services:** only for rules that involve several entities.
- **Exceptions:** one per failure, named after what went wrong (`SheetLocked`, `DuplicateSheet`).
- **Rule IDs:** each class or method names the rule IDs it enforces in a short comment.
- **No I/O:** no database, HTTP, files, clock, or randomness inside the rules. Pass the current date and similar values in as parameters.

The preferred language is **PHP 8.3** with `declare(strict_types=1);`. For any other language, follow `04-LANGUAGES.md`.

## `screens/`

One component per page (see `templates/screen-example.vue`):

- **Data:** the page receives its data as **props**, with the exact shape of its query in `CONTRACT.md`. Put a realistic sample of that data in a comment.
- **Actions:** functions named after the use cases, with stub bodies (`console.log` or a TODO). The real version sends a request.
- **Header:** the page opens with a header that has a small label, a title, a short description, and the main action. The system wraps the page in its own layout, so don't build a sidebar, top bar, or login.
- **States:** include the empty state, error messages, and confirmation before archiving or deleting.
- **Roles:** hide or disable what a role can't do. This is only cosmetic; the domain still enforces the rule.
- **Copy:** UI copy is in neutral Spanish.

The preferred stack is **Vue 3 `<script setup>` + Nuxt UI 4 + Tailwind**. For any other stack, follow `04-LANGUAGES.md`.

### Nuxt UI rules

- Use these components: `UButton`, `UInput`, `UTextarea`, `USelect`, `UCheckbox`, `USwitch`, `UFormField`, `UTable`, `UCard`, `UModal`, `UBadge`, `UAlert`, `UTabs`, and `UDropdownMenu`.
- Icons use static Lucide names only, such as `icon="i-lucide-check"`. Never bind them dynamically (`:icon="..."`).
- Don't change the theme, the radius, or the colors. Use the semantic colors `primary`, `neutral`, `error`, `warning`, and `success`.
