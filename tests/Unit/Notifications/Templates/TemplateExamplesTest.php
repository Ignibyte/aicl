<?php

namespace Aicl\Tests\Unit\Notifications\Templates;

use Aicl\Notifications\Templates\TemplateExamples;
use PHPUnit\Framework\TestCase;

class TemplateExamplesTest extends TestCase
{
    public function test_all_returns_non_empty_array(): void
    {
        $examples = TemplateExamples::all();

        $this->assertNotEmpty($examples);
    }

    public function test_each_entry_has_title_and_body_keys(): void
    {
        $examples = TemplateExamples::all();

        foreach ($examples as $key => $template) {
            $this->assertArrayHasKey('title', $template, "Template [{$key}] is missing 'title' key.");
            $this->assertArrayHasKey('body', $template, "Template [{$key}] is missing 'body' key.");
        }
    }

    public function test_all_includes_default_template(): void
    {
        $examples = TemplateExamples::all();

        $this->assertArrayHasKey('_default', $examples);
    }

    public function test_for_returns_template_for_known_class(): void
    {
        $examples = TemplateExamples::all();
        $firstKey = array_key_first($examples);

        /** @phpstan-ignore-next-line */
        $template = TemplateExamples::for($firstKey);

        $this->assertNotNull($template);
        $this->assertArrayHasKey('title', $template);
        $this->assertArrayHasKey('body', $template);
    }

    public function test_for_returns_null_for_unknown_class(): void
    {
        $template = TemplateExamples::for('App\\Notifications\\NonExistent');

        $this->assertNull($template);
    }

    public function test_title_templates_contain_template_syntax(): void
    {
        $examples = TemplateExamples::all();

        $hasTemplateSyntax = false;
        foreach ($examples as $template) {
            if (str_contains($template['title'], '{{')) {
                $hasTemplateSyntax = true;

                break;
            }
        }

        $this->assertTrue($hasTemplateSyntax, 'At least one title should contain {{ template }} syntax.');
    }

    public function test_body_templates_contain_template_syntax(): void
    {
        $examples = TemplateExamples::all();

        $hasTemplateSyntax = false;
        foreach ($examples as $template) {
            if (str_contains($template['body'], '{{')) {
                $hasTemplateSyntax = true;

                break;
            }
        }

        $this->assertTrue($hasTemplateSyntax, 'At least one body should contain {{ template }} syntax.');
    }
}
