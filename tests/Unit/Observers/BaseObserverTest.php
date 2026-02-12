<?php

namespace Aicl\Tests\Unit\Observers;

use Aicl\Observers\BaseObserver;
use PHPUnit\Framework\TestCase;

class BaseObserverTest extends TestCase
{
    public function test_is_abstract_class(): void
    {
        $reflection = new \ReflectionClass(BaseObserver::class);

        $this->assertTrue($reflection->isAbstract());
    }

    public function test_defines_creating_method(): void
    {
        $this->assertTrue(method_exists(BaseObserver::class, 'creating'));
    }

    public function test_defines_created_method(): void
    {
        $this->assertTrue(method_exists(BaseObserver::class, 'created'));
    }

    public function test_defines_updating_method(): void
    {
        $this->assertTrue(method_exists(BaseObserver::class, 'updating'));
    }

    public function test_defines_updated_method(): void
    {
        $this->assertTrue(method_exists(BaseObserver::class, 'updated'));
    }

    public function test_defines_saving_method(): void
    {
        $this->assertTrue(method_exists(BaseObserver::class, 'saving'));
    }

    public function test_defines_saved_method(): void
    {
        $this->assertTrue(method_exists(BaseObserver::class, 'saved'));
    }

    public function test_defines_deleting_method(): void
    {
        $this->assertTrue(method_exists(BaseObserver::class, 'deleting'));
    }

    public function test_defines_deleted_method(): void
    {
        $this->assertTrue(method_exists(BaseObserver::class, 'deleted'));
    }

    public function test_defines_restoring_method(): void
    {
        $this->assertTrue(method_exists(BaseObserver::class, 'restoring'));
    }

    public function test_defines_restored_method(): void
    {
        $this->assertTrue(method_exists(BaseObserver::class, 'restored'));
    }

    public function test_defines_force_deleting_method(): void
    {
        $this->assertTrue(method_exists(BaseObserver::class, 'forceDeleting'));
    }

    public function test_defines_force_deleted_method(): void
    {
        $this->assertTrue(method_exists(BaseObserver::class, 'forceDeleted'));
    }
}
