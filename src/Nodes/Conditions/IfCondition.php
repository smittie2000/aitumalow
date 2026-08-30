<?php

namespace Aitumalow\Nodes\Conditions;

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\Contracts\NodeInterface;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\DTOs\NodeOutput;
use Aitumalow\Enums\NodeType;
use Aitumalow\Enums\Operator;
use Aitumalow\Nodes\HasDocumentation;

#[WorkflowNode(key: 'core.if_condition', name: 'IF Condition', category: 'Conditions', type: NodeType::Condition)]
class IfCondition implements NodeInterface
{
    use HasDocumentation;

    public function inputPorts(): array
    {
        return ['main'];
    }

    public function outputPorts(): array
    {
        return ['true', 'false'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'field', 'type' => 'string', 'label' => 'Field', 'required' => true, 'supports_expression' => true],
            ['key' => 'operator', 'type' => 'select', 'label' => 'Operator', 'required' => true, 'options' => array_column(Operator::cases(), 'value')],
            ['key' => 'value', 'type' => 'mixed', 'label' => 'Value', 'required' => false, 'supports_expression' => true],
        ];
    }

    public static function outputSchema(): array
    {
        return [
            'true' => [['key' => '*', 'type' => 'passthrough', 'label' => 'Items matching condition']],
            'false' => [['key' => '*', 'type' => 'passthrough', 'label' => 'Items not matching condition']],
        ];
    }

    /** @param array<string, mixed> $config */
    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $trueItems = [];
        $falseItems = [];
        $operator = Operator::from($config['operator']);

        foreach ($input->items as $item) {
            $fieldValue = data_get($item, $config['field']);

            if ($operator->evaluate($fieldValue, $config['value'] ?? null)) {
                $trueItems[] = $item;
            } else {
                $falseItems[] = $item;
            }
        }

        return NodeOutput::ports(['true' => $trueItems, 'false' => $falseItems]);
    }
}
