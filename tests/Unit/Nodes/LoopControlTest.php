<?php

use Aitumalow\DTOs\ExecutionContext;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\Nodes\Controls\LoopControl;

beforeEach(function () {
    $this->node = new LoopControl;
    $this->context = new ExecutionContext(workflowRunId: 1, workflowId: 1);
});

it('flattens an array field into loop items', function () {
    $input = new NodeInput(
        items: [['name' => 'Order', 'items' => ['apple', 'banana', 'cherry']]],
        context: $this->context,
    );

    $output = $this->node->execute($input, ['source_field' => 'items']);

    $loopItems = $output->items('loop_item');
    expect($loopItems)->toHaveCount(3)
        ->and($loopItems[0]['_loop_index'])->toBe(0)
        ->and($loopItems[0]['_loop_item'])->toBe(['value' => 'apple'])
        ->and($loopItems[2]['_loop_item'])->toBe(['value' => 'cherry']);
});

it('preserves original items on loop_done port', function () {
    $input = new NodeInput(
        items: [['name' => 'Order', 'items' => ['a', 'b']]],
        context: $this->context,
    );

    $output = $this->node->execute($input, ['source_field' => 'items']);

    expect($output->items('loop_done'))->toHaveCount(1)
        ->and($output->items('loop_done')[0]['name'])->toBe('Order');
});

it('handles array of objects', function () {
    $input = new NodeInput(
        items: [['products' => [['id' => 1], ['id' => 2]]]],
        context: $this->context,
    );

    $output = $this->node->execute($input, ['source_field' => 'products']);

    $loopItems = $output->items('loop_item');
    expect($loopItems)->toHaveCount(2)
        ->and($loopItems[0]['_loop_item'])->toBe(['id' => 1]);
});

it('has correct ports', function () {
    expect($this->node->inputPorts())->toBe(['main'])
        ->and($this->node->outputPorts())->toBe(['loop_item', 'loop_done']);
});
