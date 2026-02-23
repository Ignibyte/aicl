# Work Pipeline: {Title}

| Field | Value |
|-------|-------|
| **Pipeline Type** | Work |
| **Work Type** | Feature / Integration / Infrastructure / Refactor |
| **Status** | Phase 1: Plan |
| **Created** | {date} |
| **Last Updated** | {date} |
| **Last Agent** | /pm |
| **Next Step** | Human review spec, then `/solutions design-work {Title}` |
| **Blocked** | No |
| **Designer Phase** | Included / Skipped — {reason} |

---

## Phase 1: Plan
**Agent:** /pm
**Status:** PASS | FAIL | Not Started
**Started:** {timestamp}
**Completed:** {timestamp}

### Work Spec
- **Title:** {descriptive title}
- **Type:** Feature | Integration | Infrastructure | Refactor
- **Scope:** {1-2 sentence summary of what this work accomplishes}
- **Files Expected:** {estimated count and locations}
- **Dependencies:** {packages, services, or entities this depends on}
- **Risks:** {what could go wrong, or "Low risk"}
- **Acceptance Criteria:**
  - {criterion 1}
  - {criterion 2}
  - {criterion 3}

### Human Confirmed
- [ ] Spec reviewed and confirmed

### Known Pitfalls (from RLM)
- {run `aicl:rlm recall --agent=pm --phase=1` — list applicable pitfalls, or "None found"}

---

<!-- ═══════════════════════════════════════════════════════════════════
     FORGE MCP HOOK (NOT YET ACTIVE)

     When Forge ships, this is where institutional knowledge injection
     happens. Between Phase 1 (Plan) and Phase 2 (Design), the PM will
     call Forge's MCP endpoint to:

     1. Query lessons by concept tags matching this work type
     2. Retrieve prevention rules applicable to the file patterns
     3. Surface architecture decisions from similar past work
     4. Inject a "Forge Briefing" section into Phase 2 context

     Integration surface:
       forge:query-knowledge --concepts="{work-type-tags}" --file-patterns="{expected-files}"

     Until Forge is available, agents rely on local `aicl:rlm recall`
     for institutional knowledge.
     ═══════════════════════════════════════════════════════════════════ -->

## Phase 2: Design
**Agent:** /solutions
**Status:** PASS | FAIL | BLOCKED | Not Started
**Started:** {timestamp}
**Completed:** {timestamp}

### Architecture

**Approach:**
- {high-level description of the solution approach}

**File Manifest:**
| # | File | Action | Purpose |
|---|------|--------|---------|
| 1 | {path} | Create / Modify | {what it does} |
| 2 | {path} | Create / Modify | {what it does} |

**Business Rules:**
- {validation constraints, side effects, edge cases}

**Testing Strategy:**
- {what tests to write, what to cover, edge cases}

**Architectural Decisions:**
- {any deviations from standard patterns, or "Standard patterns"}

### Deferred Items
- {anything that couldn't be decided — if present, Status MUST be BLOCKED}

### Issues Found
- {any concerns or risks}

### Human Confirmed
- [ ] Design reviewed and confirmed

---

## Phase 3: Implement
**Agent:** /architect
**Status:** PASS | FAIL | Not Started
**Started:** {timestamp}
**Completed:** {timestamp}

### Files Created
| File | Path |
|------|------|
| {description} | {path} |

### Files Modified
| File | Change |
|------|--------|
| {path} | {what was changed} |

### Migrations
- {list of migrations run, or "None"}

### Routes Added
- {list of routes, or "None"}

### Providers/Config Updated
- {list of provider or config changes, or "None"}

### Pint
- **Status:** Pass | Fail
- **Issues Fixed:** {count or "Clean"}

### Package Check
- **Status:** CLEAN | VIOLATION
- **Details:** {confirm no files written to vendor/}

### Notes
- {any deviations from design, or "Followed design exactly"}

---

## Phase 3.5: Style (Conditional)
**Agent:** /designer
**Status:** PASS | BLOCKED | SKIPPED | Not Started
**Started:** {timestamp}
**Completed:** {timestamp}

*This phase is included when the work has UI components, views, or frontend changes. Skipped for backend-only work.*

### Files Modified
| File | Change |
|------|--------|
| {file} | {what was improved} |

### Pint
- **Status:** Pass | Fail

### Review Summary
- **UI Quality:** {improvements made or "N/A"}
- **Component Reuse:** {components introduced or "None needed"}
- **Token Compliance:** {hardcoded values replaced or "All tokens correct"}
- **Dark Mode:** {verified or "N/A"}

### Issues Found
- {any concerns or recommendations}

---

## Phase 4: Validate
**Agent:** /tester
**Status:** PASS | FAIL | Not Started
**Started:** {timestamp}
**Completed:** {timestamp}

### Code Review
- **Standards Compliance:** Pass | Fail — {details}
- **Package Boundary:** CLEAN | VIOLATION
- **Security Review:** Pass | Fail — {details}

### Test Results
- **Test Count:** {N tests, N assertions}
- **Failing Tests:** {list with failure reasons, or "None"}
- **Retry Count:** 0

### Notes
- {code quality observations, or "Clean"}

---

## Phase 5: Verify (Full Suite)
**Agent:** /tester
**Status:** PASS | FAIL | Not Started
**Started:** {timestamp}
**Completed:** {timestamp}

- **Full Suite:** Pass | Fail
- **Test Count:** {N tests, N assertions}
- **Regressions:** {none or list of regressed tests}

---

## Phase 6: Complete
**Agent:** /docs
**Status:** PASS | Not Started
**Started:** {timestamp}
**Completed:** {timestamp}

- **Documentation Updated:** {list of docs updated, or "None needed"}
- **Changelog Updated:** Yes | No
- **Pipeline Doc Deleted:** Yes | No
- **Octane Reloaded:** Yes | No
- **Frontend Rebuilt:** Yes | No
