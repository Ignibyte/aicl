# Project Plan: {Project Name}

| Field | Value |
|-------|-------|
| **Status** | In Progress |
| **Created** | {date} |
| **Last Updated** | {date} |
| **Source** | {path to project spec or description} |
| **Entities Total** | {N} |
| **Entities Complete** | 0 |
| **Current Entity** | — |

---

## Entity Queue

Process ONE AT A TIME. Do NOT start Entity N+1 until Entity N completes Phase 8.

| # | Entity | Complexity | Status | Pipeline Doc | Notes |
|---|--------|-----------|--------|-------------|-------|
| 1 | {Name} | Simple / Medium / Complex | Not Started | — | |
| 2 | {Name} | Simple / Medium / Complex | Not Started | — | Depends on Entity 1 |

---

## Cross-Entity Work

Tasks that fall outside individual entity pipelines but are part of the overall project.

| # | Task | Status | Depends On | Notes |
|---|------|--------|-----------|-------|
| 1 | {e.g., Pivot migration for many-to-many} | Not Started | Entities 1+2 complete | |
| 2 | {e.g., Master seeder} | Not Started | All entities complete | |
| 3 | {e.g., Navigation groups & theming} | Not Started | All entities complete | |

---

## Human Checkpoints

Between each entity and cross-entity task:

### Entity Checkpoints
- [ ] Entity 1 Phase 8 complete — human confirms proceed to Entity 2
- [ ] Entity 2 Phase 8 complete — human confirms proceed to Entity 3

### Cross-Entity Checkpoints
- [ ] All entities complete — human confirms proceed to cross-entity work
- [ ] Cross-entity work complete — human confirms project done

---

## Completion

| Metric | Value |
|--------|-------|
| All entities complete | {date or pending} |
| Cross-entity work complete | {date or pending} |
| Final full test suite | Pass / Fail / Not Run |
| Final RLM validation (all entities) | Pass / Fail / Not Run |
| Project plan archived | {date or pending} |
