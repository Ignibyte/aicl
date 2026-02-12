<?php

// PATTERN: Create page is minimal — just extends CreateRecord and points to resource.

namespace Aicl\Filament\Resources\Projects\Pages;

use Aicl\Filament\Resources\Projects\ProjectResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProject extends CreateRecord
{
    protected static string $resource = ProjectResource::class;
}
