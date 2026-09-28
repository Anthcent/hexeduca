# Scenarios: {Name}

Each scenario uses concrete values, one action, and one expected result. The scenarios become the module's tests.

## Rules

```
R1 - {rule}
  Given {state, with real values}
  When {role} {action}
  Then {result, or ErrorName}
```

## Mandatory scenarios

Write one per use case where it applies:

```
{UseCase} - happy path
  Given ...
  When staff/admin ...
  Then ...

{UseCase} - wrong role
  Given a student
  When they try {action}
  Then forbidden

{UseCase} - other school
  Given a record that belongs to another school
  When staff/admin tries {action} on it
  Then not found

{UseCase} - archived record
  Given an archived {entity}
  When staff/admin tries to edit it
  Then {RecordArchived}

{UseCase} - validation limits
  Given ...
  When {field} is empty / longer than N / out of range
  Then a validation error on {field}
```
