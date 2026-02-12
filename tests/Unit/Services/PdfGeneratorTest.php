<?php

namespace Aicl\Tests\Unit\Services;

use Aicl\Services\PdfGenerator;
use PHPUnit\Framework\TestCase;

class PdfGeneratorTest extends TestCase
{
    public function test_make_returns_new_instance(): void
    {
        $generator = PdfGenerator::make();

        $this->assertInstanceOf(PdfGenerator::class, $generator);
    }

    public function test_paper_returns_self_for_chaining(): void
    {
        $generator = new PdfGenerator;

        $result = $generator->paper('letter');

        $this->assertSame($generator, $result);
    }

    public function test_orientation_returns_self_for_chaining(): void
    {
        $generator = new PdfGenerator;

        $result = $generator->orientation('landscape');

        $this->assertSame($generator, $result);
    }

    public function test_landscape_returns_self_for_chaining(): void
    {
        $generator = new PdfGenerator;

        $result = $generator->landscape();

        $this->assertSame($generator, $result);
    }

    public function test_portrait_returns_self_for_chaining(): void
    {
        $generator = new PdfGenerator;

        $result = $generator->portrait();

        $this->assertSame($generator, $result);
    }

    public function test_fluent_chaining(): void
    {
        $generator = PdfGenerator::make()
            ->paper('letter')
            ->landscape();

        $this->assertInstanceOf(PdfGenerator::class, $generator);
    }

    public function test_paper_orientation_chain(): void
    {
        $generator = PdfGenerator::make()
            ->paper('a3')
            ->orientation('landscape')
            ->portrait();

        $this->assertInstanceOf(PdfGenerator::class, $generator);
    }
}
