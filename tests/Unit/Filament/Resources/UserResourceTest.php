<?php

namespace Aicl\Tests\Unit\Filament\Resources;

use Aicl\Filament\Resources\Users\Pages\CreateUser;
use Aicl\Filament\Resources\Users\Pages\EditUser;
use Aicl\Filament\Resources\Users\Pages\ListUsers;
use Aicl\Filament\Resources\Users\Schemas\UserForm;
use Aicl\Filament\Resources\Users\Tables\UsersTable;
use Aicl\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Resource;
use PHPUnit\Framework\TestCase;

class UserResourceTest extends TestCase
{
    // ─── UserResource ─────────────────────────────────────────

    public function test_extends_resource(): void
    {
        $this->assertTrue((new \ReflectionClass(UserResource::class))->isSubclassOf(Resource::class));
    }

    public function test_model_is_user(): void
    {
        $reflection = new \ReflectionClass(UserResource::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(User::class, $defaults['model']);
    }

    public function test_navigation_group(): void
    {
        $reflection = new \ReflectionClass(UserResource::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('People', $defaults['navigationGroup']);
    }

    public function test_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(UserResource::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(1, $defaults['navigationSort']);
    }

    public function test_defines_pages(): void
    {
        $pages = UserResource::getPages();

        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('create', $pages);
        $this->assertArrayHasKey('edit', $pages);
    }

    public function test_defines_form_method(): void
    {
        $this->assertTrue((new \ReflectionClass(UserResource::class))->hasMethod('form'));
    }

    public function test_defines_table_method(): void
    {
        $this->assertTrue((new \ReflectionClass(UserResource::class))->hasMethod('table'));
    }

    // ─── UserForm Schema ───────────────────────────────────────

    public function test_user_form_exists(): void
    {
        $this->assertTrue(class_exists(UserForm::class));
    }

    public function test_user_form_has_configure_method(): void
    {
        $this->assertTrue((new \ReflectionClass(UserForm::class))->hasMethod('configure'));

        $reflection = new \ReflectionMethod(UserForm::class, 'configure');
        $this->assertTrue($reflection->isStatic());
    }

    // ─── UsersTable ────────────────────────────────────────────

    public function test_users_table_exists(): void
    {
        $this->assertTrue(class_exists(UsersTable::class));
    }

    public function test_users_table_has_configure_method(): void
    {
        $this->assertTrue((new \ReflectionClass(UsersTable::class))->hasMethod('configure'));

        $reflection = new \ReflectionMethod(UsersTable::class, 'configure');
        $this->assertTrue($reflection->isStatic());
    }

    // ─── Pages ─────────────────────────────────────────────────

    public function test_list_users_extends_list_records(): void
    {
        $this->assertTrue((new \ReflectionClass(ListUsers::class))->isSubclassOf(ListRecords::class));
    }

    public function test_list_users_resource(): void
    {
        $reflection = new \ReflectionClass(ListUsers::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(UserResource::class, $defaults['resource']);
    }

    public function test_create_user_extends_create_record(): void
    {
        $this->assertTrue((new \ReflectionClass(CreateUser::class))->isSubclassOf(CreateRecord::class));
    }

    public function test_create_user_resource(): void
    {
        $reflection = new \ReflectionClass(CreateUser::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(UserResource::class, $defaults['resource']);
    }

    public function test_edit_user_extends_edit_record(): void
    {
        $this->assertTrue((new \ReflectionClass(EditUser::class))->isSubclassOf(EditRecord::class));
    }

    public function test_edit_user_resource(): void
    {
        $reflection = new \ReflectionClass(EditUser::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(UserResource::class, $defaults['resource']);
    }
}
