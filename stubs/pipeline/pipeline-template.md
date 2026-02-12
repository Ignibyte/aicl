# Pipeline: {Entity Name}

| Field | Value |
|-------|-------|
| **Status** | Phase 1: Plan |
| **Tier** | 1 (Entity) |
| **Created** | {date} |
| **Last Updated** | {date} |
| **Last Agent** | /pm |
| **Next Step** | Human review spec, then `/solutions design {Name}` |
| **Blocked** | No |
| **Designer Phase** | Included / Skipped — {reason} |

---

## Phase 1: Plan
**Agent:** /pm
**Status:** PASS | FAIL | Not Started
**Started:** {timestamp}
**Completed:** {timestamp}

### Entity Spec
- **Name:** {PascalCase}
- **Table:** {snake_plural}
- **Fields:**
  - `id` — bigIncrements (PK)
  - `owner_id` — foreignId (users)
  - {field} — {type}
  - `created_at` / `updated_at` — timestamps
  - `deleted_at` — softDeletes
- **Relationships:**
  - {method}(): {Type} → {RelatedModel} ({foreign_key})
- **States:** {if applicable — list with transitions, or "None"}
- **Traits:** {list from HasEntityEvents, HasAuditTrail, HasStandardScopes, HasTagging, HasSearchableFields}
- **Contracts:** {list — derived from traits}
- **Widgets:** {list — per world-model.md decision rules}
- **Notifications:** {list — assignment, status change}

### Human Confirmed
- [ ] Spec reviewed and confirmed

### Issues from failures.md
- {any applicable known pitfalls, or "None found"}

---

## Phase 2: Design
**Agent:** /solutions
**Status:** PASS | FAIL | BLOCKED | Not Started
**Started:** {timestamp}
**Completed:** {timestamp}

### Design Blueprint

**Relationships:**
- {method}(): {Type} → {RelatedModel} (foreign key: {column})

**State Machine:**
- States: {list with label, color, icon}
- Transitions: {from → to}
- Default: {state}

**Business Rules:**
- {validation constraints, computed fields, side effects, scopes}

**Widget Selection:**
- {widget type}: {what it displays}

**Notification Triggers:**
- {event} → {recipients} via {channels}

**Architectural Decisions:**
- {any deviations from golden pattern, or "Standard golden pattern"}

### Deferred Items
- {anything that couldn't be decided — if present, Status MUST be BLOCKED}

### Issues Found
- {any concerns or risks}

### Human Confirmed
- [ ] Design reviewed and confirmed

---

## Phase 3: Generate
**Agent:** /architect
**Status:** PASS | FAIL | Not Started
**Started:** {timestamp}
**Completed:** {timestamp}

### Files Created
| File | Path |
|------|------|
| Model | `app/Models/{Name}.php` |
| Migration | `database/migrations/{timestamp}_create_{table}_table.php` |
| Factory | `database/factories/{Name}Factory.php` |
| Seeder | `database/seeders/{Name}Seeder.php` |
| Policy | `app/Policies/{Name}Policy.php` |
| Observer | `app/Observers/{Name}Observer.php` |
| Filament Resource | `app/Filament/Resources/{Plural}/{Name}Resource.php` |
| API Controller | `app/Http/Controllers/Api/{Name}Controller.php` |
| Store Request | `app/Http/Requests/Store{Name}Request.php` |
| Update Request | `app/Http/Requests/Update{Name}Request.php` |
| API Resource | `app/Http/Resources/{Name}Resource.php` |
| Exporter | `app/Filament/Exporters/{Name}Exporter.php` |
| Widgets | `app/Filament/Widgets/{Name}*.php` |
| Notifications | `app/Notifications/{Name}*.php` |
| Test | `tests/Feature/Entities/{Name}Test.php` |

### Pint
- **Status:** Pass | Fail
- **Issues Fixed:** {count or "Clean"}

### Package Check
- **Status:** CLEAN | VIOLATION
- **Details:** {confirm no files written to packages/aicl/}

### Notes
- {any deviations from design blueprint, or "Followed design exactly"}

---

## Phase 3.5: Style (Conditional)
**Agent:** /designer
**Status:** PASS | BLOCKED | SKIPPED | Not Started
**Started:** {timestamp}
**Completed:** {timestamp}

*This phase is included when the entity has widgets, PDF templates, or custom UI. Skipped for simple CRUD entities.*

### Files Modified
| File | Change |
|------|--------|
| {file} | {what was improved} |

### Pint
- **Status:** Pass | Fail

### Review Summary
- **Form Layout:** {improvements made or "Adequate"}
- **Table Columns:** {improvements made or "Adequate"}
- **Widget Styling:** {improvements made or "N/A"}
- **PDF Templates:** {improvements made or "N/A"}
- **Component Reuse:** {components introduced or "None needed"}
- **Token Compliance:** {hardcoded values replaced or "All tokens correct"}
- **Dark Mode:** {verified or issues found}

### Issues Found
- {any concerns or recommendations}

---

## Phase 4: Validate (Pre-Registration)

### RLM Validation
**Agent:** /rlm
**Status:** PASS | FAIL | Not Started
**Started:** {timestamp}
**Completed:** {timestamp}
- **Score:** {N}/40 ({percentage}%)
- **Failing Patterns:** {list with pattern names and fixes, or "None"}
- **Retry Count:** 0

### Tester Validation
**Agent:** /tester
**Status:** PASS | FAIL | Not Started
**Started:** {timestamp}
**Completed:** {timestamp}
- **Test Count:** {N tests, N assertions}
- **Failing Tests:** {list with failure reasons, or "None"}
- **Retry Count:** 0

---

## Phase 5: Register
**Agent:** /architect
**Status:** PASS | FAIL | Not Started
**Started:** {timestamp}
**Completed:** {timestamp}

### Modified Files
| File | Change |
|------|--------|
| `AppServiceProvider.php` | Added `Gate::policy()` and `Model::observe()` |
| `routes/api.php` | Added API resource routes |

### Pint
- **Status:** Pass | Fail

### Verification
- [ ] Policy bound in AppServiceProvider
- [ ] Observer bound in AppServiceProvider
- [ ] API routes added
- [ ] Filament resource auto-discovered

---

## Phase 6: Re-Validate (Post-Registration)

### RLM Re-Validation
**Agent:** /rlm
**Status:** PASS | FAIL | Not Started
**Started:** {timestamp}
**Completed:** {timestamp}
- **Score:** {N}/40 ({percentage}%)
- **Regressions from Phase 4:** {list or "None"}
- **Retry Count:** 0

### Tester Re-Validation
**Agent:** /tester
**Status:** PASS | FAIL | Not Started
**Started:** {timestamp}
**Completed:** {timestamp}
- **Test Count:** {N tests, N assertions}
- **Regressions from Phase 4:** {list or "None"}
- **Retry Count:** 0

---

## Phase 7: Verify (Full Suite)
**Agent:** /tester
**Status:** PASS | FAIL | Not Started
**Started:** {timestamp}
**Completed:** {timestamp}

- **Full Suite:** Pass | Fail
- **Test Count:** {N tests, N assertions}
- **Regressions:** {none or list of regressed tests}

---

## Phase 8: Complete
**Agent:** /docs
**Status:** PASS | Not Started
**Started:** {timestamp}
**Completed:** {timestamp}

- **Entity Doc:** `docs/entities/{name}.md`
- **API Doc Updated:** Yes | No
- **Changelog Updated:** Yes | No
- **Pipeline Doc Deleted:** Yes | No
- **Octane Reloaded:** Yes | No
- **Frontend Rebuilt:** Yes | No
