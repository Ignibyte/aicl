<?php

namespace Aicl\Filament\Resources\Users\Pages;

use Aicl\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
