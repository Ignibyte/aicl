<?php

namespace Aicl\Tests\Unit\Console\Support;

use Aicl\Console\Commands\MakeEntityCommand;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class MakeEntityCleanupTest extends TestCase
{
    public function test_cleanup_option_exists_in_signature(): void
    {
        $command = new ReflectionClass(MakeEntityCommand::class);
        $signature = $command->getProperty('signature');
        $signature->setAccessible(true);

        $signatureValue = $signature->getDefaultValue();

        $this->assertStringContainsString('--cleanup', $signatureValue);
    }

    public function test_run_cleanup_method_exists(): void
    {
        $this->assertTrue(
            method_exists(MakeEntityCommand::class, 'runCleanup'),
            'MakeEntityCommand should have a runCleanup method.'
        );
    }

    public function test_run_cleanup_method_is_protected(): void
    {
        $method = new ReflectionMethod(MakeEntityCommand::class, 'runCleanup');

        $this->assertTrue($method->isProtected());
    }

    public function test_run_cleanup_accepts_file_array_parameter(): void
    {
        $method = new ReflectionMethod(MakeEntityCommand::class, 'runCleanup');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('files', $params[0]->getName());
        $this->assertSame('array', $params[0]->getType()->getName());
    }
}
