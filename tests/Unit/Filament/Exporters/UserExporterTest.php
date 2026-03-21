<?php

namespace Aicl\Tests\Unit\Filament\Exporters;

use Aicl\Filament\Exporters\UserExporter;
use App\Models\User;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use PHPUnit\Framework\TestCase;

class UserExporterTest extends TestCase
{
    public function test_extends_exporter(): void
    {
        $this->assertTrue((new \ReflectionClass(UserExporter::class))->isSubclassOf(Exporter::class));
    }

    public function test_model_is_user(): void
    {
        $reflection = new \ReflectionClass(UserExporter::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(User::class, $defaults['model']);
    }

    public function test_has_columns(): void
    {
        $columns = UserExporter::getColumns();

        $this->assertNotEmpty($columns);
    }

    public function test_columns_include_expected_fields(): void
    {
        $columns = UserExporter::getColumns();
        $names = array_map(fn ($col) => $col->getName(), $columns);

        $this->assertContains('id', $names);
        $this->assertContains('name', $names);
        $this->assertContains('email', $names);
        $this->assertContains('created_at', $names);
    }

    public function test_completed_notification_body(): void
    {
        $export = new Export;
        $export->successful_rows = 42;

        $body = UserExporter::getCompletedNotificationBody($export);

        $this->assertStringContainsString('42', $body);
        $this->assertStringContainsString('user export', $body);
    }
}
