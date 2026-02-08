<?php

namespace Aicl\Traits;

use Spatie\Tags\HasTags;

/**
 * Adds tagging support to a model.
 *
 * Wraps spatie/laravel-tags. Provides tag management via:
 * - $model->attachTag('tag-name')
 * - $model->attachTags(['tag-one', 'tag-two'])
 * - $model->detachTag('tag-name')
 * - $model->syncTags(['tag-one', 'tag-two'])
 * - Model::withAnyTags(['tag-one'])->get()
 * - Model::withAllTags(['tag-one', 'tag-two'])->get()
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasTagging
{
    use HasTags;
}
