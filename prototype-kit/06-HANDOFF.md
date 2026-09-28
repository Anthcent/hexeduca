# Handoff: from prototype to module

This file is for you and for the repo session that integrates the prototype.

## 1. Bring the prototype in

Copy the prototype folder to `prototypes/{Name}/` in the repo. Nothing in `prototypes/` runs or ships; it's input only.

## 2. Start the repo session

> Read `NEW-MODULE.md` and every file in `prototype-kit/`. Analyze the prototype in `prototypes/{Name}/`, following `prototype-kit/06-HANDOFF.md`. Don't write code until I approve the analysis.

## 3. Analysis (in the repo, before any code)

The repo session reads the prototype against the real system and produces a short report with these parts.

1. **Checklist.** Rerun `05-CHECKLIST.md`. List every failure.
2. **Overlap.** Compare the prototype with the existing modules and choose one path per part:

   | Path | When |
   |---|---|
   | **New module** | No existing module covers it |
   | **Extend** an existing module | It adds to something that exists (new rules, screens, or fields) |
   | **Replace** existing behavior | It does the same thing better. This needs a migration plan for the existing data. |
   | **Reuse** | The existing module already does it; the prototype only confirms it |

3. **Mapping to the real architecture:**
   - which data it reads through which existing readers, and which readers are missing;
   - the tables, and what they reference;
   - the use cases and their permissions;
   - the events it records and the events it listens to;
   - the sidebar entry and its permission.
4. **Port.** What moves almost unchanged, and what gets rewritten. If the prototype isn't PHP or Vue, the domain is ported to PHP following the contract, and the screens are rebuilt in Vue + Nuxt UI following the mapping.
5. **Clashes.** Each place where the prototype contradicts the system (a field that doesn't exist, a missing role, a rule that breaks tenant isolation), with a proposed fix.
6. **Open questions.** Everything from the prototype's list plus anything the analysis found. These go to the user **one at a time**.

The user approves the analysis before any code is written.

## 4. Build and close

After approval, the work follows `NEW-MODULE.md`:

- `BRIEF.md` stands in for Phase A. It's confirmed in 3 to 5 bullets, not asked again.
- Phase B builds the module. The prototype's domain code goes to the module's `Domain/`, and the screens go to its `Resources/js/Pages`.
- Phase C closes the module. The prototype's `scenarios.md` becomes the Feature and Unit tests. After that come the quality gates, the PostgreSQL run, the reviews, and green CI.

## What a prototype saves, and what it doesn't

**Saves:** the brief conversation, the design of rules and screens, and most of the domain code and test cases.

**Doesn't save:** the integration into the architecture, tenant isolation, permissions, migrations, tests on the real system, or the reviews. Those always happen in the repo.
