# Contract: {Name}

## Data model

The system adds `id`, `school_id`, `created_at`, and `updated_at` itself; don't list them. List `id` only when another column references it.

### `{table_name}`

- **Per period:** yes / no
- **Can be archived:** yes / no

| Column | Type | Null | References | Notes |
|---|---|---|---|---|
| academic_offer_id | int | no | AcademicOffers | example |
| status | string | no | | `active` / `archived` |

## Use cases

Write one block per action, with a verb-first name.

```
{UseCaseName}
  Who: staff/admin, teacher
  Input:
    field (type, required|optional, limits)
  Rules: R1, R2
  Result: {what it returns}
  Errors: {ErrorName} (R1), {ErrorName} (R2)
  Event: none | {module.event_name} { id, otherId }
```

## Queries (data per screen)

Write one block per screen, giving the exact shape that the screen receives as props.

```
{Screen}
  Who: staff/admin
  Props:
    items: [
      { id: 1, offerName: "1er grado A", date: "2026-10-01", present: 24, absent: 2, status: "open" }
    ]
    canCreate: true
  Filters: {period, offer, ...}
```

## Errors

| Error | Rule | Message shown (neutral Spanish) |
|---|---|---|
| | | |
