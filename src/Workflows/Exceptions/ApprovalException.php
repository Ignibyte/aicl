<?php

declare(strict_types=1);

namespace Aicl\Workflows\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * @codeCoverageIgnore Workflow infrastructure
 */
class ApprovalException extends RuntimeException
{
    public static function alreadyPending(Model $model): self
    {
        $type = class_basename($model);

        return new self("{$type} is already pending approval.");
    }

    public static function alreadyApproved(Model $model): self
    {
        $type = class_basename($model);

        return new self("{$type} is already approved.");
    }

    public static function notPending(Model $model): self
    {
        $type = class_basename($model);

        return new self("{$type} is not pending approval.");
    }

    public static function notApproved(Model $model): self
    {
        $type = class_basename($model);

        return new self("{$type} is not approved.");
    }
}
