<?php

namespace Aicl\Tests\Unit\Search;

use Aicl\Jobs\IndexSearchDocumentJob;
use Aicl\Observers\SearchObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SearchObserverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_created_dispatches_index_job_when_configured(): void
    {
        config(['aicl.search.enabled' => true]);
        config(['aicl.search.entities' => [
            TestSearchModel::class => ['fields' => ['name'], 'label' => 'name'],
        ]]);

        $model = new TestSearchModel;
        $model->forceFill(['id' => 1, 'name' => 'Test']);

        $observer = new SearchObserver;
        $observer->created($model);

        Queue::assertPushed(IndexSearchDocumentJob::class, function ($job) {
            return $job->action === 'index' && $job->modelId === '1';
        });
    }

    public function test_deleted_dispatches_delete_job(): void
    {
        config(['aicl.search.enabled' => true]);
        config(['aicl.search.entities' => [
            TestSearchModel::class => ['fields' => ['name'], 'label' => 'name'],
        ]]);

        $model = new TestSearchModel;
        $model->forceFill(['id' => 1, 'name' => 'Test']);

        $observer = new SearchObserver;
        $observer->deleted($model);

        Queue::assertPushed(IndexSearchDocumentJob::class, function ($job) {
            return $job->action === 'delete';
        });
    }

    public function test_skips_when_search_disabled(): void
    {
        config(['aicl.search.enabled' => false]);

        $model = new TestSearchModel;
        $model->forceFill(['id' => 1, 'name' => 'Test']);

        $observer = new SearchObserver;
        $observer->created($model);

        Queue::assertNothingPushed();
    }

    public function test_skips_unconfigured_models(): void
    {
        config(['aicl.search.enabled' => true]);
        config(['aicl.search.entities' => []]); // No entities configured

        $model = new TestSearchModel;
        $model->forceFill(['id' => 1, 'name' => 'Test']);

        $observer = new SearchObserver;
        $observer->created($model);

        Queue::assertNothingPushed();
    }
}

class TestSearchModel extends Model
{
    protected $table = 'users';

    protected $guarded = [];
}
