<?php

use Aitumalow\DTOs\ExecutionContext;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\Nodes\Utilities\FilterUtility;

beforeEach(function () {
    $this->node = new FilterUtility;
    $this->context = new ExecutionContext(workflowRunId: 1, workflowId: 1);
});

it('filters items with AND logic', function () {
    $input = new NodeInput(
        items: [
            ['status' => 'active', 'amount' => 150],
            ['status' => 'active', 'amount' => 50],
            ['status' => 'inactive', 'amount' => 200],
        ],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'conditions' => [
            ['field' => 'status', 'operator' => 'equals', 'value' => 'active'],
            ['field' => 'amount', 'operator' => 'greater_than', 'value' => 100],
        ],
        'logic' => 'and',
    ]);

    expect($output->items())->toHaveCount(1)
        ->and($output->items()[0]['amount'])->toBe(150);
});

it('filters items with OR logic', function () {
    $input = new NodeInput(
        items: [
            ['status' => 'active', 'amount' => 50],
            ['status' => 'inactive', 'amount' => 200],
            ['status' => 'inactive', 'amount' => 30],
        ],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'conditions' => [
            ['field' => 'status', 'operator' => 'equals', 'value' => 'active'],
            ['field' => 'amount', 'operator' => 'greater_than', 'value' => 100],
        ],
        'logic' => 'or',
    ]);

    expect($output->items())->toHaveCount(2);
});

it('returns empty when nothing matches', function () {
    $input = new NodeInput(
        items: [['x' => 1], ['x' => 2]],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'conditions' => [
            ['field' => 'x', 'operator' => 'equals', 'value' => 99],
        ],
    ]);

    expect($output->items())->toBeEmpty();
});
