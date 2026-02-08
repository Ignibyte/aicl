<?php

namespace Aicl\Traits;

use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use TomatoPHP\FilamentMediaManager\Traits\InteractsWithMediaManager;

/**
 * Provides default media collections for entities.
 *
 * Wraps spatie/laravel-medialibrary with standard collections:
 * - default — general-purpose file uploads
 * - avatar — single image for entity representation
 * - documents — file attachments (PDF, DOCX, etc.)
 *
 * Override registerMediaCollections() in your model to add
 * entity-specific collections while keeping the defaults.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasMediaCollections
{
    use InteractsWithMedia;
    use InteractsWithMediaManager;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('default');

        $this->addMediaCollection('avatar')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml']);

        $this->addMediaCollection('documents')
            ->acceptsMimeTypes([
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/csv',
            ]);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(150)
            ->height(150)
            ->sharpen(10)
            ->nonQueued();

        $this->addMediaConversion('preview')
            ->width(600)
            ->height(600)
            ->nonQueued();
    }
}
