<?php

use Aitumalow\DTOs\ExecutionContext;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\Nodes\Conditions\IfCondition;

beforeEach(function () {
    $this->node = new IfCondition;
    $this->context = new ExecutionContext(workflowRunId: 1, workflowId: 1);
});

it('routes matching items to true port', function () {
    $input = new NodeInput(
        items: [['status' => 'active'], ['status' => 'inactive']],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'field' => 'status',
        'operator' => 'equals',
        'value' => 'active',
    ]);

    expect($output->items('true'))->toHaveCount(1)
        ->and($output->items('true')[0]['status'])->toBe('active')
        ->and($output->items('false'))->toHaveCount(1)
        ->and($output->items('false')[0]['status'])->toBe('inactive');
});

it('supports contains operator', function () {
    $input = new NodeInput(
        items: [['email' => 'user@example.com'], ['email' => 'admin@corp.io']],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'field' => 'email',
        'operator' => 'contains',
        'value' => 'example',
    ]);

    expect($output->items('true'))->toHaveCount(1)
        ->and($output->items('false'))->toHaveCount(1);
});

it('supports is_empty and is_not_empty operators', function () {
    $input = new NodeInput(
        items: [['name' => ''], ['name' => 'John']],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'field' => 'name',
        'operator' => 'is_empty',
    ]);

    expect($output->items('true'))->toHaveCount(1)
        ->and($output->items('false'))->toHaveCount(1);
});

it('supports numeric comparisons', function () {
    $input = new NodeInput(
        items: [['score' => 85], ['score' => 40], ['score' => 70]],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'field' => 'score',
        'operator' => 'greater_or_equal',
        'value' => 70,
    ]);

    expect($output->items('true'))->toHaveCount(2)
        ->and($output->items('false'))->toHaveCount(1);
});

it('has correct ports', function () {
    expect($this->node->inputPorts())->toBe(['main'])
        ->and($this->node->outputPorts())->toBe(['true', 'false']);
});
