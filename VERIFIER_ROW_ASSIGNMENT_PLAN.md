# Verifier Row-wise Assignment — Process & Plan

> Status: **plan only — no code changed yet.**
> Goal: assign verifiers per `row_id` (the same way auditors are assigned), while
> verifiers keep exactly their current view of **old** data.

---

## 1. Goal

| | Today | After the change |
|---|---|---|
| Auditor | Assigned per `row_id` | No change |
| Verifier (new assignments) | Assigned per **project template** — sees every row of the template | Assigned per **`row_id`** — sees only rows assigned to them |
| Verifier (old assignments) | Sees every row of the template | **Same view of old data as today** |
| Template without uploaded data | Verifier sees every row any auditor creates | Verifier gets the rows created by the auditors **linked** to them |

---

## 2. How it works today

### Auditor — `UserController::assignDataToUsers` (`app/Http/Controllers/Auth/UserController.php`)
1. Creates one `data_assigns` row per selected activity (project + template + selector head).
2. For each selector value (e.g. "Outlet 1") finds the matching `row_id`s in
   `project_template_name_values_new`.
3. `assignUserActivityAndAuditRows()` writes:
   - `user_activity_data_assigns` — auditor + template + activity → `common_id`
   - `user_audit_assigns` — one row per `row_id` under that `common_id`
4. "Outlet assigned" repeats this for the child templates' matching rows.
5. Template without data (`with_data = 0`): `AssignDataWithoutTemplateData()` creates
   only the `common_id` (no rows). Rows are added later by the auditor.

### Verifier — same method
- Writes only `verifiers (user_id, project_template_name_id)`.
- Ignores selector values, activity and rows.
- "Outlet assigned" adds an entry for **every** child template of the project.

### Where rows of a "without data" template come from
| Source | Linked to |
|---|---|
| Auditor adds a row from the app — `TaskController::storeTemplateHeaderValues` | The creating auditor only |
| Auditor adds a row from the web form — `ProjectController::add_project_template_data` | The creating auditor only |
| Admin bulk-uploads Excel — `ProjectDataImport2` | Nobody, until assigned via Assign Data |

### Where the `verifiers` table is read
| Place | Use |
|---|---|
| `VerifierController::user_verification` | "My Verifications" list (one entry per template) |
| `VerifierController::activity_verification_data` / `group_verification_data` | Pending queue — currently **all** rows of the template |
| `VerifierController::activity_answer_verify` | Approve / reject — **no assignment check** |
| `ReportController`, `InfiltrationReportController`, `VerifierController::reportPage` | Project dropdowns for the Verifier role |
| `AuditorAssignedDataController` (line ~147), `ProjectTemplateController` (line ~57) | Show verifier names |
| `AuditorAssignedDataController::assignedDestroy` | Delete assignment |

---

## 3. Data model after the change

| Table | New / existing | Purpose |
|---|---|---|
| `verifiers` | existing + **1 new column** | Template-level entry. Still drives the "My Verifications" list and report dropdowns. |
| `verifiers.legacy_max_row_id` | **new column** (nullable) | Marks an **old** assignment. The verifier keeps seeing every row of the template with `id <= legacy_max_row_id` (= all rows that existed at deploy). `NULL` for every assignment made after deploy. |
| `verifier_row_assigns` | **new** | `user_id (verifier), project_template_id, activity_id, row_id` — unique on all four. Verifier equivalent of `user_audit_assigns`. |
| `verifier_auditor_links` | **new** | `verifier_id, auditor_id, project_template_id, activity_id` — unique on all four. "Verifier V checks auditor A's work on template T / activity X." Used to hand over rows the auditor creates later. |

---

## 4. Full flow after the change

### Stage 1 — Admin assigns (Assign Data → Assign Now)

**Case A — template with uploaded data** (e.g. HCCB Infiltration Audit, selector "Outlet 1")
1. `data_assigns` + auditor rows — unchanged.
2. The same `row_id`s are written to `verifier_row_assigns` for every selected verifier.
3. Every selected auditor is linked to every selected verifier (`verifier_auditor_links`),
   so rows the auditor adds later (data add-on) also reach those verifiers.
4. `verifiers` entry created if missing (`legacy_max_row_id = NULL`). An existing old
   entry is reused and **keeps** its `legacy_max_row_id`.

**Case B — template without uploaded data**
1. Auditor gets the activity with no rows — unchanged.
2. Every selected auditor is linked to every selected verifier for that template + activity.
3. Rows that auditor has already created on that template are copied to the linked
   verifiers (open decision 2).
4. `verifiers` entry created if missing — as Case A.

**Case C — "outlet assigned" (master + child templates)**
- Master template → Case A.
- Each child template → Case A or B depending on whether it has data; links and rows are
  made per child template and per child activity.

**Multiple selections:** every selected auditor × every selected verifier is linked
(2 auditors + 2 verifiers = 4 links). For "A only with V1, B only with V2", submit them as
separate assignments.

### Stage 2 — Rows get created

| How | Verifier side |
|---|---|
| Auditor adds a row from the **app** | After the row is assigned to the auditor, it is also assigned to every verifier linked to that auditor for that template + activity. (The insert must return the new id — switch `insert` → `insertGetId`.) |
| Auditor adds a row from the **web form** | Same as the app. |
| Admin **uploads Excel** | Not assigned to anyone until Assign Data is run → Case A. |

### Stage 3 — Auditor answers and submits
No change (`status = 1`, waits for verification).

### Stage 4 — Verifier opens the queue
1. **My Verifications list** — no change (from `verifiers`).
2. **Pending rows for a template + activity** = union of:
   - **Old data:** rows of the template with `id <= legacy_max_row_id`, if the verifier has an
     old entry for that template;
   - **New data:** rows in `verifier_row_assigns` for that verifier + template + activity.
3. Existing filters still apply on top (status 0/1, not yet verified, add-on "instance closed").

| Verifier's situation on a template | Rows shown |
|---|---|
| Super Admin | All pending rows |
| Old assignment only | All rows that existed at deploy (same as today) + new rows from auditors linked at deploy (see §5) |
| New assignment only | Only rows assigned to them |
| Old + new assignment on the same template | Union of both — the old view is never narrowed |
| New assignment, linked auditor hasn't created data yet | Nothing yet |

### Stage 5 — Verify / approve / reject
- Approve/reject logic unchanged.
- A row with two verifiers: whoever approves first clears it from both queues (queue already
  excludes rows with `verified_by` set).
- Optional (open decision 3): block opening/approving a row that isn't in the verifier's
  allowed rows via a typed URL. Today there is no check.

### Stage 6 — Reports
No change — report dropdowns still use `verifiers`.

### Stage 7 — Changing / deleting assignments
- **Add another verifier later:** additive; existing verifiers keep their rows and links.
- **Change an auditor's verifier:** old link stays, so new rows go to both. To replace,
  delete the assignment and reassign (open decision 4).
- **Delete assignment** (`assignedDestroy`): also deletes `verifier_row_assigns` and
  `verifier_auditor_links` for that template + activity.

### Stage 8 — Existing data at deploy (one-time migration)
1. Create the two new tables and the `legacy_max_row_id` column.
2. For each existing `verifiers` entry: `legacy_max_row_id = MAX(id)` of
   `project_template_name_values_new` for its template (`0` if the template has no rows).
   → the verifier keeps seeing every row that exists today, exactly as now.
3. For each existing `verifiers` entry: link the verifier to every auditor currently assigned
   on that template (each `user_id` + `activity_id` in `user_activity_data_assigns`).
   → rows those auditors create **after** deploy still reach the old verifier, as they do today.
4. Anything assigned **after** deploy follows the row-wise flow only.

Size on the local DB: 345 `verifiers` entries, 53 verifiers, 160 templates, ~84k rows in
those templates, 8 of them without data. The cutoff approach writes **one number per entry**
plus a small number of links, instead of ~98k+ row copies. (Production numbers will differ.)

---

## 5. Worked examples

**Template with data** — assign "Outlet 1" to auditor A + V1; separately "Outlet 2" to B + V2.
- `verifier_row_assigns`: V1 → row 101, V2 → row 102.
- V1 sees only Outlet 1; V2 sees only Outlet 2.

**Template without data** — assign A + V1; separately B + V2.
- `verifier_auditor_links`: A–V1, B–V2. No rows yet → both queues empty.
- A adds "Shop X" from the app (row 201) → assigned to A and V1. Only V1 sees it.
- B adds "Shop Y" (row 202) → assigned to B and V2. Only V2 sees it.

**Both verifiers selected together** — A, B with V1, V2 in one submission → 4 links;
rows 201 and 202 go to both verifiers; first approval clears it for both.

**Old assignment** — V0 was assigned to template T before deploy; T had rows up to id 5000.
- After deploy V0 still sees rows ≤ 5000 (all old data), as today.
- Auditor A (assigned on T before deploy) adds row 5001 → V0 gets it via the backfilled link.
- Admin uploads rows 5002–5100 and assigns them to C + V9 → only V9 sees them; V0 does not.

---

## 6. Files that would change

| File | Change |
|---|---|
| `database/migrations/…_create_verifier_row_assignment_tables.php` (new) | New tables, new column, Stage 8 backfill |
| `app/Models/VerifierRowAssign.php`, `app/Models/VerifierAuditorLink.php` (new) | Models |
| `app/Models/Verifier.php` | Add `legacy_max_row_id` to `$fillable` |
| `app/Services/VerifierAssignment.php` (new) | Shared logic: assign rows, link auditors, hand over auditor-created rows, allowed-rows lookup, cleanup |
| `app/Http/Controllers/Auth/UserController.php` | `assignDataToUsers` + `assignUserActivityAndAuditRows` + `AssignDataWithoutTemplateData` pass the selected verifiers |
| `app/Http/Controllers/API/TaskController.php` | `storeTemplateHeaderValues`: `insertGetId`, then hand the row to linked verifiers |
| `app/Http/Controllers/Masters/ProjectController.php` | `add_project_template_data`: hand the row to linked verifiers |
| `app/Http/Controllers/Masters/VerifierController.php` | Queue filter in `activity_verification_data` / `group_verification_data` (+ optional URL check) |
| `app/Http/Controllers/Masters/AuditorAssignedDataController.php` | `assignedDestroy` cleanup |

---

## 7. Implementation timeline

Estimates assume one developer, working days, and that the open decisions (§9) are answered
before Day 1. Effort includes self-testing of that step on local data.

| # | Step | Covers | Effort | Day |
|---|---|---|---|---|
| 0 | Confirm open decisions (§9) | Pairing, old-row handover, URL check, verifier replacement, old rows re-assigned | — | Before Day 1 |
| 1 | Migration + models | New tables, `legacy_max_row_id` column, Stage 8 backfill (cutoff + auditor links), `Verifier` fillable | 0.5 day | Day 1 |
| 2 | `VerifierAssignment` service | Assign rows, link auditors, hand over auditor-created rows, allowed-rows lookup, cleanup | 0.5 day | Day 1 |
| 3 | Assign Data changes (`UserController`) | Case A (with data), Case B (without data), Case C (outlet assigned / child templates), group activities | 1 day | Day 2 |
| 4 | Row-creation hooks | App `storeTemplateHeaderValues` (`insertGetId` + handover), web `add_project_template_data` | 0.5 day | Day 3 |
| 5 | Verifier queue filter | `activity_verification_data`, `group_verification_data` (old cutoff ∪ new rows, Super Admin bypass) | 0.5 day | Day 3 |
| 6 | Delete assignment cleanup | `assignedDestroy` removes links + row entries | 0.25 day | Day 4 |
| 7 | Direct-URL check *(optional — decision 3)* | Verify page + `activity_answer_verify` | 0.5 day | Day 4 |
| 8 | End-to-end testing on local | Full test checklist (§8) against real data | 1 day | Day 4–5 |
| 9 | Production deploy + check | Back up `verifiers`, deploy code, run migration, spot-check old and new verifier queues | 0.5 day | Day 5 |
| 10 | Monitor after deploy | Watch verifier queues / feedback for issues | 2–3 days (light) | Day 6–8 |

**Total build-to-deploy:** ~4.75 working days without step 7, ~5.25 days with it.

### Milestones
| Milestone | Reached after | What can be checked |
|---|---|---|
| Data layer ready | Day 1 | Migration runs locally; old entries have a cutoff and auditor links |
| Assignments write row-wise | Day 2 | Assign Data creates `verifier_row_assigns` / `verifier_auditor_links` |
| Auto-assignment of new rows | Day 3 | Row added from app/web reaches the linked verifier |
| Queue is row-wise | Day 3 | Verifier queues show only their rows; old verifiers unchanged |
| Ready for production | Day 5 | All checklist items pass on local |

---

## 8. Test checklist

- [ ] Old verifier, old template: queue identical before and after deploy.
- [ ] Old verifier, existing auditor adds a row after deploy → old verifier sees it.
- [ ] Template with data: Outlet 1 → V1, Outlet 2 → V2 → each sees only their outlet.
- [ ] Template without data: A–V1, B–V2; A adds a row from the app → only V1 sees it; same for B/V2.
- [ ] Same from the web form.
- [ ] Group activity: queue filtered per activity inside the group.
- [ ] Outlet assigned: child template rows reach the right verifier.
- [ ] Two verifiers on one row: approve by one → gone from both.
- [ ] Delete assignment → links and row entries removed; queue empty.
- [ ] Super Admin sees everything.
- [ ] Report dropdowns for the Verifier role unchanged.

---

## 9. Open decisions

1. **Pairing:** OK that every selected auditor is linked to every selected verifier (pairs
   need separate submissions), or build a pairing UI?
2. **Rows created before a new link:** when an auditor is newly linked on a template without
   data, hand over the rows they already created (proposed), or only rows created from now on?
3. **Direct-URL check:** block a verifier from opening/approving rows not assigned to them?
4. **Changing a verifier:** keep "delete and reassign", or should a new assignment replace the
   auditor's old links?
5. **Old rows re-assigned:** a row that existed at deploy and is newly assigned to V9 is still
   visible to the old verifier (old data stays as today). Confirm that's acceptable.

---

## 10. Existing issues noticed (not part of this change unless asked)

- `assignedDestroy` deletes `verifiers` rows using the **auditor** IDs, so verifier entries are
  never removed when an assignment is deleted.
- The revoke page has a verifier field the controller ignores.
- The app path (`storeTemplateHeaderValues`) creates a new `data_assigns` row for every row an
  auditor adds, unlike the web path which reuses the existing one.
- `UserController` ~line 537 passes an extra argument to `AssignDataWithoutTemplateData`
  (the child template id lands in the activity-group parameter).
