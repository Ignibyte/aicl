<?php

declare(strict_types=1);

namespace Aicl\Console\Generators;

use Illuminate\Support\Str;

/**
 * Generates broadcast event classes (Created, Updated, Deleted) extending BaseBroadcastEvent.
 */
class BroadcastEventGenerator extends BaseGenerator
{
    public function label(): string
    {
        return "Creating broadcast events for {$this->ctx->name}";
    }

    public function generate(): array
    {
        $name = $this->ctx->name;
        $snakeName = Str::snake($name);
        $files = [];

        $actions = [
            'Created' => 'created',
            'Updated' => 'updated',
            'Deleted' => 'deleted',
        ];

        foreach ($actions as $suffix => $action) {
            $className = "{$name}{$suffix}";
            $content = $this->buildBroadcastEventContent($name, $className, $snakeName, $action);

            $path = app_path("Events/{$className}.php");
            $this->ensureDirectoryExists(dirname($path));
            file_put_contents($path, $content);

            $files[] = "app/Events/{$className}.php";
        }

        return $files;
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function buildBroadcastEventContent(string $name, string $className, string $snakeName, string $action): string
    {
        if ($action === 'deleted') {
            return $this->buildDeletedEventContent($className, $snakeName);
        }

        return $this->buildMutationEventContent($name, $className, $snakeName, $action);
    }

    protected function buildDeletedEventContent(string $className, string $snakeName): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Events;

use Aicl\Broadcasting\BaseBroadcastEvent;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Database\Eloquent\Model;

class {$className} extends BaseBroadcastEvent
{
    public int|string \$entityId;

    public string \$entityType;

    public function __construct(Model \$entity)
    {
        parent::__construct();

        \$this->entityId = \$entity->getKey();
        \$this->entityType = class_basename(\$entity);
    }

    public static function eventType(): string
    {
        return '{$snakeName}.deleted';
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'id' => \$this->entityId,
            'type' => \$this->entityType,
            'action' => 'deleted',
        ];
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        \$type = strtolower(\$this->entityType);

        return [
            new PrivateChannel('dashboard'),
            new PrivateChannel("{\$type}s.{\$this->entityId}"),
        ];
    }
}
PHP;
    }

    protected function buildMutationEventContent(string $name, string $className, string $snakeName, string $action): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Events;

use Aicl\Broadcasting\BaseBroadcastEvent;
use App\Models\\{$name};
use Illuminate\Database\Eloquent\Model;

class {$className} extends BaseBroadcastEvent
{
    public function __construct(
        public {$name} \$entity,
    ) {
        parent::__construct();
    }

    public static function eventType(): string
    {
        return '{$snakeName}.{$action}';
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'id' => \$this->entity->getKey(),
            'type' => class_basename(\$this->entity),
            'action' => '{$action}',
        ];
    }

    public function getEntity(): ?Model
    {
        return \$this->entity;
    }
}
PHP;
    }
}
