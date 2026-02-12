<?php

use Aicl\Http\Controllers\Api\FailureReportController;
use Aicl\Http\Controllers\Api\GenerationTraceController;
use Aicl\Http\Controllers\Api\PreventionRuleController;
use Aicl\Http\Controllers\Api\RlmFailureController;
use Aicl\Http\Controllers\Api\RlmKnowledgeController;
use Aicl\Http\Controllers\Api\RlmLessonController;
use Aicl\Http\Controllers\Api\RlmPatternController;
use Aicl\Http\Controllers\Api\RlmScoreController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| RLM Hub API Routes
|--------------------------------------------------------------------------
|
| API routes for the RLM Hub entities and knowledge search endpoints.
| Registered by the package service provider for the central RLM
| knowledge management system.
|
*/

Route::middleware(['api', 'auth:api'])->prefix('api/v1')->group(function (): void {
    // Knowledge search (ES-backed with deterministic fallback)
    Route::get('rlm/search', [RlmKnowledgeController::class, 'search']);
    Route::get('rlm/recall', [RlmKnowledgeController::class, 'recall']);
    Route::get('rlm/failure/{failureCode}', [RlmKnowledgeController::class, 'getFailure']);
    Route::post('rlm/embed', [RlmKnowledgeController::class, 'embed']);

    Route::apiResource('rlm_patterns', RlmPatternController::class)
        ->parameters(['rlm_patterns' => 'record']);

    // RlmFailure: CRUD + upsert + top-N
    Route::post('rlm_failures/upsert', [RlmFailureController::class, 'upsert']);
    Route::get('rlm_failures/top', [RlmFailureController::class, 'top']);
    Route::apiResource('rlm_failures', RlmFailureController::class)
        ->parameters(['rlm_failures' => 'record']);

    Route::apiResource('failure_reports', FailureReportController::class)
        ->parameters(['failure_reports' => 'record']);

    // RlmLesson: CRUD + FTS search
    Route::get('rlm_lessons/search', [RlmLessonController::class, 'search']);
    Route::apiResource('rlm_lessons', RlmLessonController::class)
        ->parameters(['rlm_lessons' => 'record']);

    Route::apiResource('generation_traces', GenerationTraceController::class)
        ->parameters(['generation_traces' => 'record']);

    // PreventionRule: CRUD + contextual query
    Route::get('prevention_rules/for-entity', [PreventionRuleController::class, 'forEntity']);
    Route::apiResource('prevention_rules', PreventionRuleController::class)
        ->parameters(['prevention_rules' => 'record']);

    // RlmScore: CRUD
    Route::apiResource('rlm_scores', RlmScoreController::class)
        ->parameters(['rlm_scores' => 'record']);
});
