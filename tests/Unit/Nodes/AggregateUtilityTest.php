<?php

use Aitumalow\DTOs\ExecutionContext;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\Nodes\Utilities\AggregateUtility;

beforeEach(function () {
    $this->node = new AggregateUtility;
    $this->context = new ExecutionContext(workflowRunId: 1, workflowId: 1);
});

it('aggregates without grouping', function () {
    $input = new NodeInput(
        items: [
            ['amount' => 100],
            ['amount' => 200],
            ['amount' => 300],
        ],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'operations' => [
            ['field' => 'amount', 'function' => 'sum', 'alias' => 'total'],
            ['field' => 'amount', 'function' => 'count', 'alias' => 'count'],
            ['field' => 'amount', 'function' => 'avg', 'alias' => 'average'],
        ],
    ]);

    expect($output->items())->toHaveCount(1)
        ->and($output->items()[0]['total'])->toBe(600)
        ->and($output->items()[0]['count'])->toBe(3)
        ->and($output->items()[0]['average'])->toBe(200);
});

it('aggregates with group_by', function () {
    $input = new NodeInput(
        items: [
            ['category' => 'A', 'amount' => 100],
            ['category' => 'B', 'amount' => 200],
            ['category' => 'A', 'amount' => 300],
        ],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'group_by' => 'category',
        'operations' => [
            ['field' => 'amount', 'function' => 'sum', 'alias' => 'total'],
        ],
    ]);

    expect($output->items())->toHaveCount(2);

    $groupA = collect($output->items())->firstWhere('category', 'A');
    $groupB = collect($output->items())->firstWhere('category', 'B');

    expect($groupA['total'])->toBe(400)
        ->and($groupB['total'])->toBe(200);
});

it('handles min and max operations', function () {
    $input = new NodeInput(
        items: [['v' => 5], ['v' => 2], ['v' => 8]],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'operations' => [
            ['field' => 'v', 'function' => 'min', 'alias' => 'minimum'],
            ['field' => 'v', 'function' => 'max', 'alias' => 'maximum'],
        ],
    ]);

    expect($output->items()[0]['minimum'])->toBe(2)
        ->and($output->items()[0]['maximum'])->toBe(8);
});
