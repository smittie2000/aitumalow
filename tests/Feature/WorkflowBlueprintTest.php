<?php

declare(strict_types=1);

use Aitumalow\DTOs\WorkflowBlueprint;
use Aitumalow\Facades\Workflow;

it('installs a stable graph blueprint idempotently without host node IDs', function (): void {
    $blueprint = new WorkflowBlueprint(
        key: 'host_blueprint',
        name: 'Host blueprint',
        nodes: [
            ['id' => 'start', 'capability' => 'core.manual'],
            ['id' => 'set', 'capability' => 'core.set_fields', 'config' => ['fields' => ['installed' => true]]],
        ],
        edges: [['from' => 'start', 'to' => 'set']],
    );

    $first = Workflow::installWorkflow($blueprint);
    $same = Workflow::installWorkflow($blueprint);

    expect($same->is($first))->toBeTrue()
        ->and($first->workflow->nodes()->count())->toBe(2)
        ->and($first->workflow->edges()->count())->toBe(1);
});
