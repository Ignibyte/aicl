<?php

declare(strict_types=1);

namespace Aicl\Filament\Resources\Users\Pages;

use Aicl\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * CreateUser.
 */
class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
