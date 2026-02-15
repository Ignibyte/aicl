<?php

namespace Aicl\Console\Generators;

use Aicl\Console\Support\FieldDefinition;
use Illuminate\Support\Str;

/**
 * Generates enum classes for entity fields with enum types.
 */
class EnumGenerator extends BaseGenerator
{
    public function label(): string
    {
        return "Creating enums for: {$this->ctx->name}";
    }

    public function generate(): array
    {
        $files = [];

        if ($this->ctx->fields === null) {
            return $files;
        }

        foreach ($this->ctx->fields as $field) {
            if ($field->isEnum()) {
                $files[] = $this->generateEnum($field);
            }
        }

        return $files;
    }

    protected function generateEnum(FieldDefinition $field): string
    {
        $enumName = $field->typeArgument;

        // Check for rich enum data from spec file
        if (! empty($this->ctx->specEnums[$enumName])) {
            return $this->generateEnumFromSpec($enumName, $this->ctx->specEnums[$enumName]);
        }

        $content = <<<PHP
<?php

namespace App\\Enums;

enum {$enumName}: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return match (\$this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
        };
    }

    public function color(): string
    {
        return match (\$this) {
            self::Low => 'gray',
            self::Medium => 'warning',
            self::High => 'danger',
        };
    }
}
PHP;

        $dir = app_path('Enums');
        $this->ensureDirectoryExists($dir);
        file_put_contents("{$dir}/{$enumName}.php", $content);

        return "app/Enums/{$enumName}.php";
    }

    /**
     * Generate an enum class from rich spec file data.
     *
     * @param  array<int, array{case: string, label: string, color?: string, icon?: string}>  $cases
     */
    protected function generateEnumFromSpec(string $enumName, array $cases): string
    {
        $caseLines = [];
        $labelLines = [];
        $colorLines = [];
        $hasColor = false;
        $iconLines = [];
        $hasIcon = false;

        foreach ($cases as $entry) {
            $caseName = Str::studly($entry['case']);
            $caseValue = Str::snake($entry['case']);
            $label = $entry['label'];

            $caseLines[] = "    case {$caseName} = '{$caseValue}';";
            $labelLines[] = "            self::{$caseName} => '{$label}',";

            if (isset($entry['color']) && $entry['color'] !== '') {
                $hasColor = true;
                $colorLines[] = "            self::{$caseName} => '{$entry['color']}',";
            }

            if (isset($entry['icon']) && $entry['icon'] !== '') {
                $hasIcon = true;
                $iconLines[] = "            self::{$caseName} => '{$entry['icon']}',";
            }
        }

        $casesStr = implode("\n", $caseLines);
        $labelMatchStr = implode("\n", $labelLines);

        $methods = "    public function label(): string\n";
        $methods .= "    {\n";
        $methods .= "        return match (\$this) {\n";
        $methods .= $labelMatchStr."\n";
        $methods .= "        };\n";
        $methods .= '    }';

        if ($hasColor) {
            $colorMatchStr = implode("\n", $colorLines);
            $methods .= "\n\n    public function color(): string\n";
            $methods .= "    {\n";
            $methods .= "        return match (\$this) {\n";
            $methods .= $colorMatchStr."\n";
            $methods .= "        };\n";
            $methods .= '    }';
        }

        if ($hasIcon) {
            $iconMatchStr = implode("\n", $iconLines);
            $methods .= "\n\n    public function icon(): string\n";
            $methods .= "    {\n";
            $methods .= "        return match (\$this) {\n";
            $methods .= $iconMatchStr."\n";
            $methods .= "        };\n";
            $methods .= '    }';
        }

        $content = "<?php\n\nnamespace App\\Enums;\n\nenum {$enumName}: string\n{\n{$casesStr}\n\n{$methods}\n}\n";

        $dir = app_path('Enums');
        $this->ensureDirectoryExists($dir);
        file_put_contents("{$dir}/{$enumName}.php", $content);

        return "app/Enums/{$enumName}.php";
    }
}
