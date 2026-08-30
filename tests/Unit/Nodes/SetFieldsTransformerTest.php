<?php

use Aitumalow\DTOs\ExecutionContext;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\Nodes\Transformers\SetFieldsTransformer;

beforeEach(function () {
    $this->node = new SetFieldsTransformer;
    $this->context = new ExecutionContext(workflowRunId: 1, workflowId: 1);
});

it('sets fields on items keeping existing data', function () {
    $input = new NodeInput(
        items: [['name' => 'John', 'age' => 30]],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'fields' => ['role' => 'admin', 'active' => true],
        'keep_existing' => true,
    ]);

    expect($output->items())->toHaveCount(1)
        ->and($output->items()[0])->toBe(['name' => 'John', 'age' => 30, 'role' => 'admin', 'active' => true]);
});

it('replaces all fields when keep_existing is false', function () {
    $input = new NodeInput(
        items: [['name' => 'John', 'age' => 30]],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'fields' => ['role' => 'admin'],
        'keep_existing' => false,
    ]);

    expect($output->items()[0])->toBe(['role' => 'admin']);
});

it('overwrites existing field values', function () {
    $input = new NodeInput(
        items: [['status' => 'pending']],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'fields' => ['status' => 'active'],
    ]);

    expect($output->items()[0]['status'])->toBe('active');
});

it('processes multiple items', function () {
    $input = new NodeInput(
        items: [['a' => 1], ['a' => 2], ['a' => 3]],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'fields' => ['b' => 'x'],
    ]);

    expect($output->items())->toHaveCount(3)
        ->and($output->items()[2])->toBe(['a' => 3, 'b' => 'x']);
});
