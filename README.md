# ⚠️ DEPRECATED — DEAD CODE, DO NOT USE

This repository contains **`mod_craftpilot`**, which is **retired and unmaintained**.

### 👉 Active repository: [Somekindofa/moodle-local-craftpilot](https://github.com/Somekindofa/moodle-local-craftpilot)

All development happens there. Do not install, deploy, fork, or file issues here.

---

## Why this was replaced

CraftPilot was originally a Moodle **activity module** (`mod/craftpilot`),
instantiated per course. It was re-architected into a **local plugin**
(`local/craftpilot`) because the assistant answers questions across the entire
site rather than within one course — a per-course activity was the wrong
container.

This could not be done as a rename. Moodle binds the component name to the
directory path, so `mod/craftpilot` → `local/craftpilot` changes the DB table
prefix, the language string namespace, the capability names, and the web service
method names simultaneously. The replacement is a separate codebase.

## Evidence this code is dead

Verified on 2026-08-03 against the production host:

- **0 rows** in `course_modules` for the `craftpilot` module — the activity was
  never added to a single course.
- **All `mdl_craftpilot_*` tables empty.** Live conversation data belongs to
  `local_craftpilot_conv` / `local_craftpilot_msg`.
- **No HTTP traffic since the week of 2026-03-08.** Access logs show the
  changeover: `mod` traffic stops, `local` begins and continues.
- **Last functional commit `9ac0ba5` (2026-03-02).**

## Repository contents

Final commit `efc9b34` is a work-in-progress snapshot that was never deployed,
committed only to preserve provenance before archiving.

⚠️ The `.gitignore` here is an unmodified Next.js template and is wrong for a
Moodle plugin — it excludes `/amd/build` (which Moodle serves directly at
runtime) and `test*`. **Do not copy it into any other project.**

---

*Retained for historical reference only.*
