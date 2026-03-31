<?php

declare(strict_types=1);

namespace Aicl\Console\Generators;

use Illuminate\Support\Str;

/**
 * Generates state machine classes: abstract state + concrete state classes.
 */
class StateMachineGenerator extends BaseGenerator
{
    public function label(): string
    {
        return "Creating state machine: {$this->ctx->name}State";
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function generate(): array
    {
        $name = $this->ctx->name;
        $states = $this->ctx->states;
        $files = [];
        $colors = ['gray', 'success', 'warning', 'info', 'danger'];
        $icons = ['pencil-square', 'play', 'pause', 'check-circle', 'archive-box'];

        $files = array_merge($files, $this->generateAbstractState($name, $states));
        $files = array_merge($files, $this->generateConcreteStates($name, $states, $colors, $icons));

        return $files;
    }

    /**
     * Generate the abstract state class.
     *
     * @param array<int, string> $states
     *
     * @return array<int, string>
     */
    protected function generateAbstractState(string $name, array $states): array
    {
        $transitionLines = [];
        $stateCount = count($states);
        for ($i = 0; $i < $stateCount - 1; $i++) {
            $fromClass = Str::studly($states[$i]);
            $toClass = Str::studly($states[$i + 1]);
            $transitionLines[] = "                    {$fromClass}::class => [{$toClass}::class],";
        }
        $transitionsStr = implode("\n", $transitionLines);

        $stateImports = [];
        foreach ($states as $state) {
            $className = Str::studly($state);
            $stateImports[] = "use App\\States\\{$name}\\{$className};";
        }
        $stateImportsStr = implode("\n", $stateImports);

        $defaultState = Str::studly($states[0]);

        $abstractContent = <<<PHP
<?php

declare(strict_types=1);

namespace App\\States;

{$stateImportsStr}
use Spatie\\ModelStates\\State;
use Spatie\\ModelStates\\StateConfig;

abstract class {$name}State extends State
{
    abstract public function label(): string;

    abstract public function color(): string;

    abstract public function icon(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default({$defaultState}::class)
            ->allowTransitions([
{$transitionsStr}
                ]);
    }
}
PHP;

        $dir = app_path('States');
        $this->ensureDirectoryExists($dir);
        file_put_contents("{$dir}/{$name}State.php", $abstractContent);

        return ["app/States/{$name}State.php"];
    }

    /**
     * Generate concrete state classes.
     *
     * @param array<int, string> $states
     * @param array<int, string> $colors
     * @param array<int, string> $icons
     *
     * @return array<int, string>
     */
    protected function generateConcreteStates(string $name, array $states, array $colors, array $icons): array
    {
        $files = [];
        $stateDir = app_path("States/{$name}");
        $this->ensureDirectoryExists($stateDir);
        $colorCount = count($colors);
        $iconCount = count($icons);

        foreach ($states as $index => $state) {
            $className = Str::studly($state);
            $label = Str::title(str_replace('_', ' ', $state));
            $color = $colors[$index % $colorCount];
            $icon = $icons[$index % $iconCount];

            $concreteContent = <<<PHP
<?php

declare(strict_types=1);

namespace App\\States\\{$name};

use App\\States\\{$name}State;

class {$className} extends {$name}State
{
    public function label(): string
    {
        return '{$label}';
    }

    public function color(): string
    {
        return '{$color}';
    }

    public function icon(): string
    {
        return 'heroicon-o-{$icon}';
    }
}
PHP;

            file_put_contents("{$stateDir}/{$className}.php", $concreteContent);
            $files[] = "app/States/{$name}/{$className}.php";
        }

        return $files;
    }
}
