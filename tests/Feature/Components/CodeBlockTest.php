<?php

namespace Aicl\Tests\Feature\Components;

use Aicl\View\Components\CodeBlock;
use Tests\TestCase;

class CodeBlockTest extends TestCase
{
    public function test_code_block_can_be_instantiated(): void
    {
        $component = new CodeBlock(code: '<x-aicl-spinner />');

        $this->assertEquals('<x-aicl-spinner />', $component->code);
        $this->assertEquals('blade', $component->language);
    }

    public function test_code_block_accepts_custom_language(): void
    {
        $component = new CodeBlock(code: 'echo "hello"', language: 'php');

        $this->assertEquals('php', $component->language);
    }

    public function test_code_block_renders_with_code_content(): void
    {
        $view = $this->blade(
            '<x-aicl-code-block code="<x-aicl-spinner />" />'
        );

        $view->assertSee('Show Code', false);
        $view->assertSee('&lt;x-aicl-spinner /&gt;', false);
    }

    public function test_code_block_has_copy_button(): void
    {
        $view = $this->blade(
            '<x-aicl-code-block code="test code" />'
        );

        $view->assertSee('Copy', false);
    }
}
