<?php

use Aitumalow\DTOs\ExecutionContext;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\Nodes\Conditions\SwitchCondition;

beforeEach(function () {
    $this->node = new SwitchCondition;
    $this->context = new ExecutionContext(workflowRunId: 1, workflowId: 1);
});

it('routes items to matching case ports', function () {
    $input = new NodeInput(
        items: [
            ['tier' => 'premium'],
            ['tier' => 'basic'],
            ['tier' => 'enterprise'],
        ],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'field' => 'tier',
        'cases' => [
            ['port' => 'case_premium', 'operator' => 'equals', 'value' => 'premium'],
            ['port' => 'case_basic', 'operator' => 'equals', 'value' => 'basic'],
        ],
        'fallthrough' => true,
    ]);

    expect($output->items('case_premium'))->toHaveCount(1)
        ->and($output->items('case_basic'))->toHaveCount(1)
        ->and($output->items('default'))->toHaveCount(1)
        ->and($output->items('default')[0]['tier'])->toBe('enterprise');
});

it('drops unmatched items when fallthrough is false', function () {
    $input = new NodeInput(
        items: [['type' => 'unknown']],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'field' => 'type',
        'cases' => [
            ['port' => 'case_a', 'operator' => 'equals', 'value' => 'a'],
        ],
        'fallthrough' => false,
    ]);

    expect($output->items('default'))->toBeEmpty()
        ->and($output->items('case_a'))->toBeEmpty();
});
