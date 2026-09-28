# Prototype kit

Use this kit to design a hexeduca module with any AI tool, outside the repo. The other tool builds a **prototype**: the module's data, rules, use cases, and screens. A later session inside the repo analyzes the prototype and turns it into a real module.

## What's in the kit

| File | For | What it holds |
|---|---|---|
| `00-START-PROMPT.md` | You | The prompt to paste into the other AI |
| `01-SYSTEM.md` | Other AI | What hexeduca is and the data that already exists |
| `02-DESIGN-RULES.md` | Other AI | The rules every prototype follows |
| `03-DELIVERABLE.md` | Other AI | The folder to deliver and the format of each file |
| `04-LANGUAGES.md` | Other AI | Rules for when the tool uses a language or framework other than PHP and Vue |
| `05-CHECKLIST.md` | Other AI and you | The checks to pass before handing the prototype over |
| `06-HANDOFF.md` | You and the repo session | How the prototype enters the repo and how it gets analyzed |
| `templates/` | Other AI | Files to copy and fill in |

## How to use it

1. Give the other AI every file in this folder, including `templates/`.
2. Paste the prompt from `00-START-PROMPT.md` and replace `{Name}` and `{purpose}`.
3. Answer its questions. It must ask one at a time, and it must not invent business rules.
4. Before accepting the prototype, go through `05-CHECKLIST.md`.
5. Copy the prototype folder to `prototypes/{Name}/` in the repo and follow `06-HANDOFF.md`.

## The idea in one line

The prototype decides **what** the module does. The repo session decides **how** it fits the system: a new module, an extension of an existing one, or a replacement. It always adapts the code to the real architecture.
