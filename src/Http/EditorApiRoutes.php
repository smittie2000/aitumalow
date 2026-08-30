<?php

declare(strict_types=1);

namespace Aitumalow\Http;

use Aitumalow\Http\Controllers\CapabilityCatalogController;
use Aitumalow\Http\Controllers\FolderController;
use Aitumalow\Http\Controllers\TagController;
use Aitumalow\Http\Controllers\WorkflowController;
use Aitumalow\Http\Controllers\WorkflowEdgeController;
use Aitumalow\Http\Controllers\WorkflowNodeController;
use Aitumalow\Http\Controllers\WorkflowReferenceController;
use Aitumalow\Http\Controllers\WorkflowRevisionController;
use Aitumalow\Http\Controllers\WorkflowRunController;
use Illuminate\Support\Facades\Route;

final class EditorApiRoutes
{
    public static function register(): void
    {
        Route::scopeBindings()->group(function (): void {
            Route::apiResource('workflows', WorkflowController::class);
            Route::post('workflows/{workflow}/activate', [WorkflowController::class, 'activate']);
            Route::post('workflows/{workflow}/deactivate', [WorkflowController::class, 'deactivate']);
            Route::post('workflows/{workflow}/run', [WorkflowController::class, 'run']);
            Route::post('workflows/{workflow}/duplicate', [WorkflowController::class, 'duplicate']);
            Route::post('workflows/{workflow}/validate', [WorkflowController::class, 'validateWorkflow']);
            Route::get('workflows/{workflow}/revisions', [WorkflowRevisionController::class, 'index']);
            Route::get('workflows/{workflow}/revisions/{revision}/compare-draft', [WorkflowRevisionController::class, 'compareDraft']);
            Route::post('workflows/{workflow}/revisions/{revision}/restore-draft', [WorkflowRevisionController::class, 'restoreDraft']);
            Route::post('workflows/{workflow}/test-node', [WorkflowRunController::class, 'testNode']);

            Route::post('workflows/{workflow}/nodes', [WorkflowNodeController::class, 'store']);
            Route::put('workflows/{workflow}/nodes/{node}', [WorkflowNodeController::class, 'update']);
            Route::delete('workflows/{workflow}/nodes/{node}', [WorkflowNodeController::class, 'destroy']);
            Route::patch('workflows/{workflow}/nodes/{node}/position', [WorkflowNodeController::class, 'position']);
            Route::get('workflows/{workflow}/nodes/{node}/variables', [WorkflowNodeController::class, 'availableVariables']);
            Route::get('workflows/{workflow}/references/{source}', [WorkflowReferenceController::class, 'index']);
            Route::post('workflows/{workflow}/nodes/{node}/pin', [WorkflowNodeController::class, 'pin']);
            Route::delete('workflows/{workflow}/nodes/{node}/pin', [WorkflowNodeController::class, 'unpin']);

            Route::post('workflows/{workflow}/edges', [WorkflowEdgeController::class, 'store']);
            Route::delete('workflows/{workflow}/edges/{edge}', [WorkflowEdgeController::class, 'destroy']);

            Route::get('workflows/{workflow}/runs', [WorkflowRunController::class, 'index']);
            Route::get('runs/{run}', [WorkflowRunController::class, 'show']);
            Route::post('runs/{run}/cancel', [WorkflowRunController::class, 'cancel']);
            Route::post('runs/{run}/resume', [WorkflowRunController::class, 'resume']);
            Route::post('runs/{run}/replay', [WorkflowRunController::class, 'replay']);

            Route::get('catalog', [CapabilityCatalogController::class, 'index']);
            Route::get('catalog/editor-scripts', [CapabilityCatalogController::class, 'editorScripts']);

            Route::apiResource('tags', TagController::class)->except(['show']);
            Route::apiResource('folders', FolderController::class)->except(['show']);
        });
    }
}
