# Project Entity Extraction — Future Task

**Status:** Deferred — covered in `.claude/planning/framework/architect/v1-architect-tasks.md` (Task 5)
**Related:** Golden example patterns now served via Forge MCP `search-patterns`

---

## Summary

The Project entity (model, migration, factory, seeder, observer, states, notifications, Filament resource, API controller, widgets) currently lives in the package (`packages/aicl/`). It is NOT framework infrastructure — it's a demo/reference entity that shows how a full CRUD entity stack looks.

The Project entity should be **removed from the package entirely**. Golden example code is now served exclusively via the **Forge MCP `search-patterns` tool** — local golden example files have been removed from the repository.

## Rationale

- The package should be pure framework — auth, RBAC, base traits, components, utilities
- Project is a concrete entity, not infrastructure
- Shipping it in the package means every client install gets a "Projects" menu item they don't need
- Golden example patterns are served via Forge MCP `search-patterns` — that's where the AI reads patterns from
- After removal, `aicl:make-entity Project` can regenerate it into `app/` to prove the system works

## What to Remove from Package

- `packages/aicl/src/Models/Project.php`
- `packages/aicl/src/Models/States/` (ProjectState, Draft, Active, OnHold, Completed, Cancelled)
- `packages/aicl/src/Filament/Resources/Projects/` (Resource, Form, Table, Pages)
- `packages/aicl/src/Filament/Widgets/ProjectStatsOverview.php`
- `packages/aicl/src/Filament/Widgets/ProjectsByStatusChart.php`
- `packages/aicl/src/Filament/Widgets/UpcomingDeadlinesWidget.php`
- `packages/aicl/src/Http/Controllers/Api/ProjectController.php`
- `packages/aicl/src/Http/Requests/StoreProjectRequest.php`
- `packages/aicl/src/Http/Requests/UpdateProjectRequest.php`
- `packages/aicl/src/Http/Resources/ProjectResource.php`
- `packages/aicl/src/Observers/ProjectObserver.php`
- `packages/aicl/src/Notifications/ProjectAssignedNotification.php`
- `packages/aicl/src/Notifications/ProjectStatusChangedNotification.php`
- `packages/aicl/src/Policies/ProjectPolicy.php`
- `packages/aicl/database/factories/ProjectFactory.php`
- `packages/aicl/database/seeders/ProjectSeeder.php`
- `packages/aicl/database/migrations/*_create_projects_table.php`
- Related API routes in `packages/aicl/routes/api.php`
- Resource/widget registrations in `AiclPlugin`
- All Project-specific tests

## After Removal

- Package installs clean — no demo entity cluttering the admin panel
- Golden example patterns served via Forge MCP `search-patterns` for AI reference
- `aicl:make-entity Project` can scaffold it fresh into `app/` as a validation test
