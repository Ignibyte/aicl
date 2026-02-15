<?php

namespace Aicl\Database\Seeders;

use Aicl\Models\PreventionRule;
use Aicl\Models\RlmFailure;
use App\Models\User;
use Illuminate\Database\Seeder;

class PreventionRuleSeeder extends Seeder
{
    public function run(): void
    {
        $ownerId = User::first()?->id ?? 1;

        foreach ($this->curatedRules() as $rule) {
            $failureId = null;

            if (! empty($rule['failure_code'])) {
                $failureId = RlmFailure::query()
                    ->where('failure_code', $rule['failure_code'])
                    ->value('id');
            }

            PreventionRule::query()->updateOrCreate(
                ['rule_text' => $rule['rule_text']],
                [
                    'rlm_failure_id' => $failureId,
                    'rule_text' => $rule['rule_text'],
                    'trigger_context' => $rule['trigger_context'] ?? null,
                    'confidence' => $rule['confidence'],
                    'priority' => $rule['priority'],
                    'is_active' => true,
                    'applied_count' => 0,
                    'owner_id' => $ownerId,
                ]
            );
        }
    }

    /**
     * Curated prevention rules linked to base failures.
     *
     * @return array<int, array<string, mixed>>
     */
    private function curatedRules(): array
    {
        return [
            [
                'failure_code' => 'BF-001',
                'rule_text' => 'When using HasStandardScopes, ALWAYS override searchableColumns() to list only columns that exist on the entity\'s table.',
                'trigger_context' => ['trait' => 'HasStandardScopes'],
                'confidence' => 1.0,
                'priority' => 90,
            ],
            [
                'failure_code' => 'BF-002',
                'rule_text' => 'Entity registration (policy binding, observer binding, API routes, Filament resource discovery) MUST happen in Phase 5, AFTER Phase 4 validation passes.',
                'trigger_context' => ['phase' => 'registration'],
                'confidence' => 1.0,
                'priority' => 100,
            ],
            [
                'failure_code' => 'BF-003',
                'rule_text' => 'Scaffolding MUST include CreateAction on List page, ExportAction in table headerActions, ViewAction + EditAction in recordActions, and ExportBulkAction + DeleteBulkAction in toolbarActions.',
                'trigger_context' => ['component' => 'filament-resource'],
                'confidence' => 1.0,
                'priority' => 85,
            ],
            [
                'failure_code' => 'BF-004',
                'rule_text' => 'After ANY context continuation, re-read all active pipeline documents before resuming work. Pipeline document is the source of truth, not conversational memory.',
                'trigger_context' => ['event' => 'context-continuation'],
                'confidence' => 1.0,
                'priority' => 100,
            ],
            [
                'failure_code' => 'BF-006',
                'rule_text' => 'In Dusk tests, use form.{field} for #id selectors and data.{field} for wire:model selectors. Filament v4 changed the HTML id prefix.',
                'trigger_context' => ['component' => 'dusk-test'],
                'confidence' => 0.95,
                'priority' => 70,
            ],
            [
                'failure_code' => 'BF-007',
                'rule_text' => 'Seed permissions AND roles on BOTH web and api guards. Assign roles on both guards when testing API endpoints with actingAs.',
                'trigger_context' => ['component' => 'api-test', 'trait' => 'Spatie\Permission'],
                'confidence' => 1.0,
                'priority' => 85,
            ],
            [
                'failure_code' => 'BF-008',
                'rule_text' => 'Never use SerializesModels in delete event classes. Store needed attributes as primitive properties instead.',
                'trigger_context' => ['event' => 'entity-deleted'],
                'confidence' => 1.0,
                'priority' => 80,
            ],
            [
                'failure_code' => 'BF-009',
                'rule_text' => 'Set properties via ->set() to trigger Livewire lifecycle hooks. Never call lifecycle hooks directly via ->call().',
                'trigger_context' => ['component' => 'livewire-test'],
                'confidence' => 0.95,
                'priority' => 70,
            ],
            [
                'failure_code' => 'BF-010',
                'rule_text' => 'Call app("router")->getRoutes()->refreshNameLookups() after registering routes in test setUp().',
                'trigger_context' => ['component' => 'route-test'],
                'confidence' => 0.90,
                'priority' => 65,
            ],
            [
                'failure_code' => 'BF-012',
                'rule_text' => 'Use Filament\Schemas\Components for Section and Grid. Use Filament\Forms\Components ONLY for form inputs (TextInput, Select, Toggle).',
                'trigger_context' => ['component' => 'filament-form'],
                'confidence' => 1.0,
                'priority' => 95,
            ],
            [
                'failure_code' => 'BF-013',
                'rule_text' => 'Use explicit match expressions that return complete Tailwind class strings. Never interpolate class fragments dynamically.',
                'trigger_context' => ['component' => 'blade-template'],
                'confidence' => 1.0,
                'priority' => 75,
            ],
            [
                'failure_code' => 'BF-014',
                'rule_text' => 'Always set BROADCAST_CONNECTION=log in test environment config (phpunit.xml or .env.testing) to prevent broadcast driver failures.',
                'trigger_context' => ['component' => 'phpunit-config'],
                'confidence' => 1.0,
                'priority' => 80,
            ],
            [
                'failure_code' => 'BF-015',
                'rule_text' => 'NEVER modify a published migration after it has been tagged in a release. Always create a new migration for schema changes.',
                'trigger_context' => ['event' => 'migration-change'],
                'confidence' => 1.0,
                'priority' => 90,
            ],
        ];
    }
}
