# Manual test checklist

Replace `billing` / `Billing` with the module alias and name.

1. `modules:list` shows the module as `mature` and active.
2. As super-admin, open `/modulos`: the module appears active, and the toggle and entitlement switches work.
3. Entitle a test school (`modules:entitle billing {subdomain}` or the admin page).
4. As a user of that school WITH the required role: the sidebar item appears, the page loads (200), and each create/edit/delete flow works.
5. As a user of that school WITHOUT the role: the sidebar item is hidden, and the route returns 403 (or the documented denial).
6. As a user of a NON-entitled school: the sidebar item is hidden, and the route returns 404.
7. Break each business rule from the brief (invalid data, forbidden transition): a clear validation error appears, and nothing is saved.
8. If the module publishes events: after a write, check the `integration_outbox_events` row and the consumer's effect.

Log every failure with `failure-report.md`.
