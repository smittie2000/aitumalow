<?php

use Aitumalow\Contracts\NodeInterface;
use Aitumalow\DTOs\ExecutionContext;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\DTOs\NodeOutput;
use Aitumalow\Engine\NodeRunner;

beforeEach(function () {
    $this->runner = new NodeRunner;
    $this->context = new ExecutionContext(workflowRunId: 1, workflowId: 1);
});

it('runs a node successfully', function () {
    $node = Mockery::mock(NodeInterface::class);
    $node->shouldReceive('execute')->once()->andReturn(NodeOutput::main([['result' => 'ok']]));
    $node->shouldReceive('outputPorts')->andReturn(['main']);

    $input = new NodeInput(items: [['data' => 'test']], context: $this->context);

    $output = $this->runner->run($node, $input, []);

    expect($output->items('main'))->toHaveCount(1)
        ->and($output->items('main')[0]['result'])->toBe('ok');
});

it('lets failures reach the durable activity retry policy', function () {
    $node = Mockery::mock(NodeInterface::class);
    $node->shouldReceive('execute')->andThrow(new RuntimeException('fatal'));
    $node->shouldReceive('outputPorts')->andReturn(['main']);

    $input = new NodeInput(items: [[]], context: $this->context);

    $this->runner->run($node, $input, []);
})->throws(RuntimeException::class, 'fatal');
