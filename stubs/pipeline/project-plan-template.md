# Project Plan: {Project Name}

| Field | Value |
|-------|-------|
| **Status** | In Progress |
| **Created** | {date} |
| **Last Updated** | {date} |
| **Source** | {path to project spec or description} |
| **Work Items Total** | {N} |
| **Work Items Complete** | 0 |
| **Current Item** | — |

---

## Work Queue

Process ONE AT A TIME. Do NOT start Item N+1 until Item N completes its final phase.

Entity items use `PIPELINE-{Name}.md`. Work items use `WORK-{Title}.md`.

| # | Name | Type | Complexity | Status | Pipeline Doc | Notes |
|---|------|------|-----------|--------|-------------|-------|
| 1 | {Name} | Entity | Simple / Medium / Complex | Not Started | — | |
| 2 | {Name} | Entity | Simple / Medium / Complex | Not Started | — | Depends on Item 1 |
| 3 | {Title} | Feature | Simple / Medium / Complex | Not Started | — | |
| 4 | {Title} | Integration | Simple / Medium / Complex | Not Started | — | Depends on Items 1+2 |

---

## Cross-Cutting Work

Tasks that fall outside individual pipelines but are part of the overall project.

| # | Task | Status | Depends On | Notes |
|---|------|--------|-----------|-------|
| 1 | {e.g., Pivot migration for many-to-many} | Not Started | Items 1+2 complete | |
| 2 | {e.g., Master seeder} | Not Started | All items complete | |
| 3 | {e.g., Navigation groups & theming} | Not Started | All items complete | |

---

## Human Checkpoints

Between each work item and cross-cutting task:

### Item Checkpoints
- [ ] Item 1 complete — human confirms proceed to Item 2
- [ ] Item 2 complete — human confirms proceed to Item 3

### Cross-Cutting Checkpoints
- [ ] All items complete — human confirms proceed to cross-cutting work
- [ ] Cross-cutting work complete — human confirms project done

---

## Completion

| Metric | Value |
|--------|-------|
| All items complete | {date or pending} |
| Cross-cutting work complete | {date or pending} |
| Final full test suite | Pass / Fail / Not Run |
| Final RLM validation (all entities) | Pass / Fail / Not Run / N/A |
| Project plan archived | {date or pending} |
