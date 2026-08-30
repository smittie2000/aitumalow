<?php

namespace Aitumalow\Nodes\Utilities;

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\Contracts\NodeInterface;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\DTOs\NodeOutput;
use Aitumalow\Enums\NodeType;
use Aitumalow\Enums\Operator;
use Aitumalow\Nodes\HasDocumentation;

#[WorkflowNode(key: 'core.filter', name: 'Filter', category: 'Utilities', type: NodeType::Utility)]
class FilterUtility implements NodeInterface
{
    use HasDocumentation;

    public function inputPorts(): array
    {
        return ['main'];
    }

    public function outputPorts(): array
    {
        return ['main'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'conditions', 'type' => 'array_of_objects', 'label' => 'Conditions', 'required' => true, 'schema' => [
                ['key' => 'field', 'type' => 'string', 'label' => 'Field'],
                ['key' => 'operator', 'type' => 'select', 'label' => 'Operator', 'options' => array_column(Operator::cases(), 'value')],
                ['key' => 'value', 'type' => 'string', 'label' => 'Value'],
            ]],
            ['key' => 'logic', 'type' => 'select', 'label' => 'Logic', 'options' => ['and', 'or'], 'required' => false],
        ];
    }

    public static function outputSchema(): array
    {
        return [];
    }

    /** @param array<string, mixed> $config */
    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $conditions = $config['conditions'] ?? [];
        $logic = $config['logic'] ?? 'and';

        $filtered = array_filter($input->items, function (array $item) use ($conditions, $logic) {
            $results = array_map(
                fn (array $cond) => Operator::from($cond['operator'])->evaluate(
                    data_get($item, $cond['field']),
                    $cond['value'] ?? null,
                ),
                $conditions,
            );

            return $logic === 'and'
                ? ! in_array(false, $results, true)
                : in_array(true, $results, true);
        });

        return NodeOutput::main(array_values($filtered));
    }
}
