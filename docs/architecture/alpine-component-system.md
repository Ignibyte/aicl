# Alpine Component System — @aicl/ui

**Version:** 1.0
**Date:** 2026-02-15
**Status:** Architecture Decision

---

## Overview

AICL's frontend is built entirely on the TALL stack (Tailwind, Alpine.js, Livewire, Laravel). The component library (`@aicl/ui`) is a collection of Blade components with Alpine.js interactivity, registered as `<x-aicl-*>`. No React, Vue, or other JS framework is used.

Alpine.js is load-bearing infrastructure — it ships bundled inside Livewire, which powers Filament v4. It cannot be removed or replaced. Every additional Alpine component has zero framework overhead because the runtime is already loaded.

---

## Component Architecture

### Three-Tier Component Model

```
Tier 1: Static Blade Components (no JS)
  └── Pure HTML + Tailwind. Props via PHP component class.
  └── Examples: StatCard, StatusBadge, Divider, EmptyState, MetadataList

Tier 2: Alpine-Enhanced Blade Components (x-data)
  └── Blade structure + Alpine for client-side interactivity.
  └── Examples: Tabs, Accordion, Modal, DataTable, CommandPalette

Tier 3: Livewire Components (server-state)
  └── Full server-client bridge. Real-time data, polling, server actions.
  └── Examples: ActivityFeed, PageBuilder (CMS), AI Chat
```

**Decision rule:** Use the lowest tier that satisfies the requirement.
- Need to display data? → Tier 1 (Blade only)
- Need client-side interaction without server calls? → Tier 2 (Alpine)
- Need server state, real-time updates, or persistence? → Tier 3 (Livewire)

### Component Anatomy

Every `<x-aicl-*>` component has up to three files:

```
packages/aicl/
├── src/View/Components/{Name}.php           # PHP class (props, logic, computed)
├── resources/views/components/{name}.blade.php  # Blade template (HTML + Tailwind + Alpine)
└── resources/js/components/{name}.js        # Alpine component (Tier 2+ only, optional)
```

**Tier 1 example (StatCard):**
```php
// PHP: Handles props, computed color classes
class StatCard extends Component {
    public function __construct(
        public string $label,
        public string|int $value,
        public ?string $icon = null,
        public string $color = 'gray',
    ) {}

    public function iconBgClass(): string {
        return match ($this->color) { ... };
    }
}
```
```blade
{{-- Blade: Pure HTML + Tailwind. No Alpine needed. --}}
<div {{ $attributes->merge(['class' => 'rounded-xl border ...']) }}>
    <span>{{ $value }}</span>
</div>
```

**Tier 2 example (Tabs):**
```blade
{{-- Blade + Alpine: x-data manages active tab state client-side --}}
<div x-data="{ activeTab: '{{ $defaultTab }}', tabs: [] }">
    <template x-for="tab in tabs">
        <button @click="activeTab = tab.name" x-text="tab.label"></button>
    </template>
    {{ $slot }}
</div>
```

**Tier 3 example (ActivityFeed):**
```php
// Livewire: Server polls for new activity, renders with Blade
class ActivityFeed extends Component {
    public function poll(): void { $this->activities = Activity::latest()->limit($this->limit)->get(); }
    public function render() { return view('aicl::livewire.activity-feed'); }
}
```

---

## React-to-Alpine Transformation Process

### Why This Works

React and Alpine.js share the same fundamental model: **declarative UI driven by reactive state**. The syntax differs, but the concepts map 1:1:

| React Concept | Alpine.js Equivalent | Notes |
|---|---|---|
| `useState(value)` | `x-data="{ value }"` | Both are reactive state containers |
| `useEffect(() => {}, [])` | `init() {}` in x-data | Runs once on mount |
| `useEffect(() => {}, [dep])` | `x-effect` or `$watch` | Watches dependencies |
| `useRef(el)` | `$refs.name` + `x-ref="name"` | Direct DOM access |
| `onClick={handler}` | `@click="handler"` | Event binding |
| `onChange={handler}` | `@change="handler"` | Event binding |
| `children` | `{{ $slot }}` | Default slot |
| `<Slot name="x">` | `<x-slot:name>` | Named slots |
| `className={cn(...)}` | `$attributes->merge(['class' => ...])` | Class merging |
| `{condition && <El>}` | `x-show="condition"` or `x-if` | Conditional render |
| `{items.map(i => <El>)}` | `@foreach($items as $item)` or `x-for` | List rendering |
| `createContext` / `useContext` | `Alpine.store('name')` | Global state |
| `createPortal` | `x-teleport` | Render elsewhere in DOM |
| `<Transition>` | `x-transition` | Enter/leave animations |
| `useMemo` | Computed method on x-data | Cached derived values |
| `useCallback` | Method on x-data object | Stable function reference |
| `forwardRef` | Not needed (Blade handles DOM) | N/A |

### Transformation Steps

When the `/replit-design` agent receives a React component:

**Step 1: Analyze the React component**
- Extract props interface (names, types, defaults)
- Identify state variables (`useState` calls)
- Identify effects and lifecycle (`useEffect`, cleanup functions)
- Identify event handlers
- Identify conditional rendering logic
- Extract all Tailwind classes (these transfer 1:1)

**Step 2: Classify the component tier**
- No `useState`, no effects, no handlers → **Tier 1** (Blade only)
- Has `useState` and/or handlers but no server calls → **Tier 2** (Blade + Alpine)
- Has data fetching, server mutations, or real-time needs → **Tier 3** (Livewire)

**Step 3: Create the PHP component class**
- React props → PHP constructor parameters with types
- React computed values → PHP methods (e.g., `colorClass()`)
- React conditional class logic → PHP `match` expressions (Tailwind v4 safe)

**Step 4: Create the Blade template**
- JSX structure → Blade HTML (1:1 structural mapping)
- Tailwind classes → copy directly (same framework)
- React props `{prop}` → Blade `{{ $prop }}`
- React `{children}` → Blade `{{ $slot }}`
- React `map()` → Blade `@foreach`
- React ternary `{x ? a : b}` → Blade `@if($x) ... @else ... @endif`

**Step 5: Add Alpine if Tier 2+**
- React `useState` → Alpine `x-data` properties
- React event handlers → Alpine `@click`, `@change`, etc.
- React `useEffect` → Alpine `init()` or `x-effect`
- React refs → Alpine `$refs`

**Step 6: Validate**
- Visual output matches (same Tailwind → same pixels)
- All props are accessible
- Interactive behavior matches
- Responsive breakpoints match
- Dark mode works (`dark:` classes transfer 1:1)

### What Doesn't Transfer Cleanly

| React Pattern | Challenge | Alpine Solution |
|---|---|---|
| Virtual scrolling (`react-virtual`) | Alpine has no virtualization | Livewire pagination (server-side) |
| React DnD (complex drag-and-drop) | Alpine sort is simpler | `@alpinejs/sort` for lists, HTML5 DnD API for complex cases |
| Rich text editing (ProseMirror/TipTap) | TipTap has Alpine support | Use `@tiptap/core` directly (framework-agnostic) |
| Complex form state (react-hook-form) | No equivalent library | Livewire form objects or Alpine x-data with validation |
| Suspense / lazy loading | No equivalent | Livewire `wire:init` lazy loading |
| Server components | N/A | Livewire IS server components |

---

## Current Component Library (25 components)

### Blade Components (21 `<x-aicl-*>`)

| Category | Components |
|---|---|
| **Layout** | SplitLayout, CardGrid, StatsRow, EmptyState, AuthSplitLayout |
| **Metrics** | StatCard, KpiCard, TrendCard, ProgressCard |
| **Data** | MetadataList, InfoCard, StatusBadge, Timeline |
| **Actions** | ActionBar, QuickAction, AlertBanner, Divider |
| **Utility** | Spinner, Tabs, TabPanel |
| **Brand** | IgnibyteLogo, FaviconMeta, VersionBadge |
| **Navigation** | NavigationSwitcherInit, NavigationSwitcherToggle |

### Alpine Components (4 `window.*`)

| Component | File | Purpose |
|---|---|---|
| `pollingWidget` | `aicl-widgets.js` | Auto-refresh with pause-when-hidden |
| `aiChat` | `aicl-widgets.js` | WebSocket streaming chat |
| `navigationSwitcher` | `aicl-widgets.js` | Sidebar ↔ topbar toggle |
| `presenceIndicator` | `aicl-widgets.js` | Live user presence via Echo |

### Livewire Components (1)

| Component | Purpose |
|---|---|
| `ActivityFeed` | Real-time activity stream with polling |

---

## Planned Component Additions

Components to build by studying React ecosystem patterns (shadcn/ui, Radix, Headless UI):

| Priority | Component | Tier | React Inspiration | Purpose |
|---|---|---|---|---|
| P0 | DataTable | 2 | shadcn `<DataTable>` | Sortable, filterable, paginated table |
| P0 | Modal | 2 | shadcn `<Dialog>` | Accessible dialog overlay |
| P0 | Dropdown | 2 | shadcn `<DropdownMenu>` | Action menu |
| P1 | Drawer | 2 | shadcn `<Sheet>` | Slide-out panel |
| P1 | Combobox | 2 | shadcn `<Combobox>` | Searchable select |
| P1 | Accordion | 2 | shadcn `<Accordion>` | Collapsible sections |
| P1 | CommandPalette | 2 | shadcn `<Command>` | Keyboard-driven search |
| P2 | Tooltip | 2 | shadcn `<Tooltip>` | Hover info (Floating UI) |
| P2 | Avatar | 1 | shadcn `<Avatar>` | User avatar with fallback |
| P2 | Toast | 2 | shadcn `<Toast>` | Notification toasts |
| P2 | Breadcrumbs | 1 | — | Navigation breadcrumbs |
| P2 | Pagination | 1 | — | Page navigation |

---

## Scaffolder Integration

When the entity scaffolder runs with `--views` (or `views: true` in spec), it generates public Blade views that compose from the component library:

```
resources/views/{entities}/
├── index.blade.php          # Uses: DataTable, Filters, Pagination, EmptyState
├── show.blade.php           # Uses: SplitLayout, InfoCard, MetadataList, Timeline, StatusBadge
└── components/
    ├── {entity}-card.blade.php   # Uses: StatusBadge, formatted fields
    └── {entity}-filters.blade.php # Uses: Combobox, select inputs
```

The RLM validates that generated views use library components correctly (see component decision tree in `design-component-decision-tree.md`).

---

## Related Documents

- [Component Library Reference](component-library.md) — Existing component documentation
- [Filament UI](filament-ui.md) — Filament's built-in components
