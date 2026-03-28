<?php

declare(strict_types=1);

namespace Aicl\Console\Generators;

/**
 * Generates the entity policy class.
 */
class PolicyGenerator extends BaseGenerator
{
    public function label(): string
    {
        return "Creating policy: {$this->ctx->name}Policy";
    }

    public function generate(): array
    {
        $name = $this->ctx->name;

        $content = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Policies;

use Aicl\Policies\BasePolicy;
use App\Models\__NAME__;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;

/**
 * Policy for __NAME__ entity.
 *
 * - Owner always has access to their own records
 * - Falls back to Shield permission checks via BasePolicy
 */
class __NAME__Policy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return '__NAME__';
    }

    public function view(User $user, Model $record): bool
    {
        /** @var __NAME__ $record */
        if ($record->owner_id === $user->getKey()) {
            return true;
        }

        return parent::view($user, $record);
    }

    public function update(User $user, Model $record): bool
    {
        /** @var __NAME__ $record */
        if ($record->owner_id === $user->getKey()) {
            return true;
        }

        return parent::update($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        /** @var __NAME__ $record */
        if ($record->owner_id === $user->getKey()) {
            return true;
        }

        return parent::delete($user, $record);
    }
}
PHP;

        $content = str_replace('__NAME__', $name, $content);

        $path = app_path("Policies/{$name}Policy.php");
        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $content);

        return ["app/Policies/{$name}Policy.php"];
    }
}
