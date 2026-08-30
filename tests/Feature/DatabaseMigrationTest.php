<?php

declare(strict_types=1);

use Aitumalow\AitumalowServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

it('ships a create-only migration baseline that remains optionally publishable', function (): void {
    $publishablePaths = ServiceProvider::pathsToPublish(
        AitumalowServiceProvider::class,
        'aitumalow-migrations',
    );

    $migrationPaths = glob(dirname(__DIR__, 2).'/database/migrations/*.php');

    if (! is_array($migrationPaths)) {
        throw new RuntimeException('Unable to read the package migration directory.');
    }

    $migrationNames = array_map(basename(...), $migrationPaths);

    expect($publishablePaths)->toHaveCount(1)
        ->and(array_keys($publishablePaths)[0])->toEndWith('/database/migrations')
        ->and(array_values($publishablePaths)[0])->toEndWith('/database/migrations')
        ->and($migrationNames)
        ->toHaveCount(9)
        ->each->toContain('_create_');
});

it('creates the complete workflow schema from the squashed baseline', function (): void {
    expect(Schema::hasTable(config('aitumalow.tables.folders')))->toBeTrue()
        ->and(Schema::hasTable(config('aitumalow.tables.workflows')))->toBeTrue()
        ->and(Schema::hasTable(config('aitumalow.tables.nodes')))->toBeTrue()
        ->and(Schema::hasTable(config('aitumalow.tables.edges')))->toBeTrue()
        ->and(Schema::hasTable(config('aitumalow.tables.revisions')))->toBeTrue()
        ->and(Schema::hasTable(config('aitumalow.tables.runs')))->toBeTrue()
        ->and(Schema::hasTable(config('aitumalow.tables.commands')))->toBeTrue()
        ->and(Schema::hasTable(config('aitumalow.tables.node_runs')))->toBeTrue()
        ->and(Schema::hasTable(config('aitumalow.tables.tags')))->toBeTrue()
        ->and(Schema::hasTable(config('aitumalow.tables.tag_pivot')))->toBeTrue()
        ->and(Schema::hasColumns(config('aitumalow.tables.workflows'), [
            'key',
            'created_via',
            'folder_id',
            'active_revision_id',
        ]))->toBeTrue()
        ->and(Schema::hasColumn(config('aitumalow.tables.nodes'), 'pinned_data'))->toBeTrue()
        ->and(Schema::hasColumns(config('aitumalow.tables.runs'), [
            'durable_workflow_id',
            'durable_run_id',
            'workflow_revision_id',
            'waiting_node_id',
            'waiting_state',
            'execution_scope',
        ]))->toBeTrue()
        ->and(Schema::hasColumn(config('aitumalow.tables.node_runs'), 'durable_activity_id'))->toBeTrue()
        ->and(Schema::hasTable('workflow_runs'))->toBeTrue()
        ->and(Schema::hasTable('workflow_tasks'))->toBeTrue()
        ->and(Schema::hasTable('workflow_credentials'))->toBeFalse();
});
