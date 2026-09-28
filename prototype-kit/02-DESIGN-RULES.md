# Design rules

Every prototype follows these rules. Each rule exists because the real system enforces it; a prototype that breaks one has to be redesigned during integration.

## Data

1. **Every record belongs to one school.** Don't add a `school_id` field, a school selector, or a school filter. The system takes the school from the subdomain. Just state in the data model which entities are per school (almost all of them).
2. **Say whether each entity depends on the period.** It either belongs to one academic period, or it holds across years.
3. **Reference other modules' data by ID only.** Store `student_id` or `academic_offer_id`. Never copy a name, email, or other field into your own tables; names are read at display time.
4. **Only read the fields listed in `01-SYSTEM.md`.** Anything else goes under "Open questions".
5. **Don't add technical columns.** No `school_id`, `created_at`, or `updated_at`; the system adds them.

## Behavior

6. **Never invent business rules.** Everything in the logic must have been confirmed by the user. The rest goes under "Open questions".
7. **Give every rule an ID** (`R1`, `R2`, ...). Use cases, domain code, and scenarios refer to rules by that ID.
8. **Archive, don't delete, anything with history.** A record that other data depends on gets archived. Only a record nothing points to can be deleted. Reactivating an archived record first shows its impact and asks for confirmation.
9. **Don't impose uniqueness silently.** When a value "should" be unique (a code, a name, one per day), ask whether it's a hard rule or a warning the user can accept.
10. **Put the rules in the domain, not the screen.** A screen may help (disable a button, warn early), but the rule must also hold in the domain code.

## Access

11. **Name who can run each use case.** Use the roles from `01-SYSTEM.md`.
12. **Know the two error cases.** A user without the role gets **forbidden**. A record from another school, or one that doesn't exist, gets **not found**; never reveal that a record exists in another school.
13. **Say whether some users see only their own records** (for example, a teacher sees only their own offers, a student only their own data). That is a business rule, with an ID.

## Integration with other modules

14. **Don't write to other modules.** If something here must affect something elsewhere, describe an **event**: a stable name (`attendance.sheet_closed`) and a small payload of IDs. Other modules react to it on their own.
15. **You may react to other modules' events** (for example, "when a student is enrolled, create their record here"). List them in the brief.

## Scope

16. **Keep it simple.** Don't build speculative features, generic engines, plugin systems, or settings nobody asked for.
17. **Write down what's left out.** Everything intentionally left out goes under "Out of scope".
18. **Don't build infrastructure.** No login, users, roles, layout, sidebar, database, migrations, routes, controllers, or API. The repo session builds all of that.
