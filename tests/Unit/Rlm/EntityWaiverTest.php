<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Models\EntityWaiver;
use Aicl\Rlm\EntityValidator;
use Aicl\Rlm\PatternRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntityWaiverTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id' => 1]);
    }

    // ─── E.1: EntityWaiver model ────────────────────────────────

    public function test_waiver_can_be_created(): void
    {
        $waiver = EntityWaiver::factory()->create([
            'entity_name' => 'Ticket',
            'pattern_id' => 'model.soft_deletes',
            'created_by' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('entity_waivers', [
            'entity_name' => 'Ticket',
            'pattern_id' => 'model.soft_deletes',
        ]);
    }

    public function test_waiver_is_expired(): void
    {
        $expired = EntityWaiver::factory()->expired()->create([
            'created_by' => $this->admin->id,
        ]);

        $this->assertTrue($expired->isExpired());

        $active = EntityWaiver::factory()->permanent()->create([
            'entity_name' => 'Task',
            'pattern_id' => 'model.fillable',
            'created_by' => $this->admin->id,
        ]);

        $this->assertFalse($active->isExpired());
    }

    public function test_waiver_active_scope_excludes_expired(): void
    {
        EntityWaiver::factory()->expired()->create([
            'entity_name' => 'Ticket',
            'pattern_id' => 'model.soft_deletes',
            'created_by' => $this->admin->id,
        ]);

        EntityWaiver::factory()->permanent()->create([
            'entity_name' => 'Ticket',
            'pattern_id' => 'model.fillable',
            'created_by' => $this->admin->id,
        ]);

        $active = EntityWaiver::query()->active()->get();
        $this->assertCount(1, $active);
        $this->assertSame('model.fillable', $active->first()->pattern_id);
    }

    public function test_waiver_for_entity_scope(): void
    {
        EntityWaiver::factory()->permanent()->create([
            'entity_name' => 'Ticket',
            'pattern_id' => 'model.soft_deletes',
            'created_by' => $this->admin->id,
        ]);

        EntityWaiver::factory()->permanent()->create([
            'entity_name' => 'Task',
            'pattern_id' => 'model.fillable',
            'created_by' => $this->admin->id,
        ]);

        $ticketWaivers = EntityWaiver::query()->forEntity('Ticket')->get();
        $this->assertCount(1, $ticketWaivers);
    }

    public function test_waiver_belongs_to_creator(): void
    {
        $waiver = EntityWaiver::factory()->create([
            'created_by' => $this->admin->id,
        ]);

        $this->assertSame($this->admin->id, $waiver->creator->id);
    }

    // ─── E.2: EntityValidator waiver integration ────────────────

    public function test_validator_respects_active_waivers(): void
    {
        EntityWaiver::factory()->permanent()->create([
            'entity_name' => 'Ticket',
            'pattern_id' => 'model.soft_deletes',
            'reason' => 'Domain requirement: no soft deletes',
            'created_by' => $this->admin->id,
        ]);

        $validator = new EntityValidator('Ticket', PatternRegistry::VERSION);

        // Create a temp file that fails the soft_deletes check
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, '<?php namespace App\Models; use Illuminate\Database\Eloquent\Model; class Ticket extends Model { use HasFactory; protected $fillable = ["name"]; protected function casts(): array { return []; } }');
        $validator->addFile('model', $tmpFile);
        $validator->validate();

        // Find the soft_deletes result — it should be waived (passed)
        $results = $validator->results();
        $softDeleteResult = null;
        foreach ($results as $result) {
            if ($result->pattern->name === 'model.soft_deletes') {
                $softDeleteResult = $result;
                break;
            }
        }

        $this->assertNotNull($softDeleteResult);
        $this->assertTrue($softDeleteResult->passed);
        $this->assertTrue($softDeleteResult->waived);
        $this->assertStringContainsString('WAIVED', $softDeleteResult->message);

        @unlink($tmpFile);
    }

    public function test_validator_ignores_expired_waivers(): void
    {
        EntityWaiver::factory()->expired()->create([
            'entity_name' => 'Ticket',
            'pattern_id' => 'model.soft_deletes',
            'created_by' => $this->admin->id,
        ]);

        $validator = new EntityValidator('Ticket', PatternRegistry::VERSION);

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, '<?php namespace App\Models; class Ticket extends Model { }');
        $validator->addFile('model', $tmpFile);
        $validator->validate();

        // Expired waiver should not count
        $this->assertSame(0, $validator->waivedCount());

        @unlink($tmpFile);
    }

    public function test_validator_tracks_waiver_budget(): void
    {
        EntityWaiver::factory()->permanent()->create([
            'entity_name' => 'Ticket',
            'pattern_id' => 'model.soft_deletes',
            'created_by' => $this->admin->id,
        ]);

        $validator = new EntityValidator('Ticket', PatternRegistry::VERSION);
        $validator->validate();

        $this->assertSame(1, $validator->waivedCount());
        $this->assertGreaterThan(0, $validator->waivedWeight());
        $this->assertLessThan((float) config('aicl.rlm.waiver_budget', 5.0), $validator->waivedWeight());
    }

    public function test_validator_remaining_budget(): void
    {
        $budget = (float) config('aicl.rlm.waiver_budget', 5.0);

        $validator = new EntityValidator('Ticket', PatternRegistry::VERSION);
        $validator->validate();

        // No waivers, full budget remaining
        $this->assertSame($budget, $validator->remainingBudget());
    }

    // ─── E.3: RlmCommand waiver subcommands ─────────────────────

    public function test_waiver_add_creates_waiver(): void
    {
        $this->artisan('aicl:rlm', [
            'action' => 'waiver',
            'query' => 'add',
            '--entity' => 'Ticket',
            '--pattern' => 'model.soft_deletes',
            '--reason' => 'Test reason',
            '--justification' => 'Test scope justification',
        ])->assertSuccessful();

        $this->assertDatabaseHas('entity_waivers', [
            'entity_name' => 'Ticket',
            'pattern_id' => 'model.soft_deletes',
        ]);
    }

    public function test_waiver_add_requires_all_fields(): void
    {
        $this->artisan('aicl:rlm', [
            'action' => 'waiver',
            'query' => 'add',
            '--entity' => 'Ticket',
            // Missing --pattern, --reason, --justification
        ])->assertFailed();
    }

    public function test_waiver_list_shows_waivers(): void
    {
        EntityWaiver::factory()->permanent()->create([
            'entity_name' => 'Ticket',
            'pattern_id' => 'model.soft_deletes',
            'reason' => 'Domain requirement',
            'created_by' => $this->admin->id,
        ]);

        $this->artisan('aicl:rlm', [
            'action' => 'waiver',
            'query' => 'list',
            '--entity' => 'Ticket',
        ])->assertSuccessful();
    }

    public function test_waiver_remove_deletes_waiver(): void
    {
        EntityWaiver::factory()->permanent()->create([
            'entity_name' => 'Ticket',
            'pattern_id' => 'model.soft_deletes',
            'created_by' => $this->admin->id,
        ]);

        $this->artisan('aicl:rlm', [
            'action' => 'waiver',
            'query' => 'remove',
            '--entity' => 'Ticket',
            '--pattern' => 'model.soft_deletes',
        ])->assertSuccessful();

        $this->assertDatabaseMissing('entity_waivers', [
            'entity_name' => 'Ticket',
            'pattern_id' => 'model.soft_deletes',
            'deleted_at' => null,
        ]);
    }

    public function test_waiver_add_respects_budget(): void
    {
        $budget = (float) config('aicl.rlm.waiver_budget', 5.0);

        // Create waivers up to the budget
        // model.extends has weight 2.0, model.fillable has weight 2.0
        // That's 4.0 used of 5.0 budget
        EntityWaiver::factory()->permanent()->create([
            'entity_name' => 'Ticket',
            'pattern_id' => 'model.extends',
            'created_by' => $this->admin->id,
        ]);
        EntityWaiver::factory()->permanent()->create([
            'entity_name' => 'Ticket',
            'pattern_id' => 'model.fillable',
            'created_by' => $this->admin->id,
        ]);

        // model.has_factory weight is 2.0, total would be 6.0 > 5.0 budget
        $this->artisan('aicl:rlm', [
            'action' => 'waiver',
            'query' => 'add',
            '--entity' => 'Ticket',
            '--pattern' => 'model.has_factory',
            '--reason' => 'Over budget',
            '--justification' => 'Should fail',
        ])->assertFailed();
    }
}
