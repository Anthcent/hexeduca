# Failure report

Copy one block per failure. Keep blocks in the order they happened.

```
### F{n} — {short title}
- Module: {Name}
- Step: {command run, or screen + action, e.g. "POST /billing as teacher"}
- User/school: {role} @ {subdomain}   (or "CLI")
- Expected: {what should happen}
- Actual: {what happened}
- Error (exact, not paraphrased):
  {full message}
  {first 5–10 stack trace lines, or the laravel.log excerpt}
- Reproducible: always | sometimes | once
- Status: open | fixed in {commit}
```

Rules:
- Paste errors verbatim. Never summarize them.
- One failure per block, even if two look related.
- When fixing, reference the F{n} id in the commit body.
