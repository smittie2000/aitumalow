<?php

namespace Aitumalow\Nodes\Conditions;

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\Contracts\NodeInterface;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\DTOs\NodeOutput;
use Aitumalow\Enums\NodeType;
use Aitumalow\Enums\Operator;
use Aitumalow\Nodes\HasDocumentation;

#[WorkflowNode(key: 'core.switch', name: 'Switch', category: 'Conditions', type: NodeType::Condition)]
class SwitchCondition implements NodeInterface
{
    use HasDocumentation;

    public function inputPorts(): array
    {
        return ['main'];
    }

    public function outputPorts(): array
    {
        return ['default']; // Dynamic case_* ports are created from config
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'field', 'type' => 'string', 'label' => 'Field to check', 'required' => true, 'supports_expression' => true],
            ['key' => 'cases', 'type' => 'array_of_objects', 'label' => 'Cases', 'required' => true, 'schema' => [
                ['key' => 'port', 'type' => 'string', 'label' => 'Port Name (e.g. case_premium)'],
                ['key' => 'operator', 'type' => 'select', 'label' => 'Operator', 'options' => array_column(Operator::cases(), 'value')],
                ['key' => 'value', 'type' => 'string', 'label' => 'Value'],
            ]],
            ['key' => 'fallthrough', 'type' => 'boolean', 'label' => 'Route unmatched to "default" port', 'required' => false],
        ];
    }

    public static function outputSchema(): array
    {
        return [];
    }

    /** @param array<string, mixed> $config */
    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $portItems = [];
        $cases = $config['cases'] ?? [];

        foreach ($input->items as $item) {
            $fieldValue = data_get($item, $config['field']);
            $matched = false;

            foreach ($cases as $case) {
                if (Operator::from($case['operator'])->evaluate($fieldValue, $case['value'])) {
                    $portItems[$case['port']][] = $item;
                    $matched = true;

                    break;
                }
            }

            if (! $matched && ($config['fallthrough'] ?? true)) {
                $portItems['default'][] = $item;
            }
        }

        return NodeOutput::ports($portItems);
    }
}
