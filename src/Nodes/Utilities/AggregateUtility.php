<?php

namespace Aitumalow\Nodes\Utilities;

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\Contracts\NodeInterface;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\DTOs\NodeOutput;
use Aitumalow\Enums\AggregateFunction;
use Aitumalow\Enums\NodeType;
use Aitumalow\Nodes\HasDocumentation;

#[WorkflowNode(key: 'core.aggregate', name: 'Aggregate', category: 'Utilities', type: NodeType::Utility)]
class AggregateUtility implements NodeInterface
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
            ['key' => 'group_by', 'type' => 'string', 'label' => 'Group by field (empty = aggregate all)', 'required' => false],
            ['key' => 'operations', 'type' => 'array_of_objects', 'label' => 'Operations', 'required' => true, 'schema' => [
                ['key' => 'field', 'type' => 'string', 'label' => 'Field'],
                ['key' => 'function', 'type' => 'select', 'label' => 'Function', 'options' => array_column(AggregateFunction::cases(), 'value')],
                ['key' => 'alias', 'type' => 'string', 'label' => 'Output alias'],
            ]],
        ];
    }

    public static function outputSchema(): array
    {
        return [];
    }

    /** @param array<string, mixed> $config */
    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $groupBy = $config['group_by'] ?? null;
        $operations = $config['operations'] ?? [];

        // Group items
        $groups = [];
        foreach ($input->items as $item) {
            $key = $groupBy ? (string) data_get($item, $groupBy, '_ungrouped') : '_all';
            $groups[$key][] = $item;
        }

        // Aggregate each group
        $results = [];
        foreach ($groups as $key => $items) {
            $row = $groupBy ? [$groupBy => $key] : [];

            foreach ($operations as $op) {
                $values = array_map(fn (array $item) => data_get($item, $op['field']), $items);
                $alias = $op['alias'] ?? $op['field'].'_'.$op['function'];
                $row[$alias] = AggregateFunction::from($op['function'])->compute($values);
            }

            $results[] = $row;
        }

        return NodeOutput::main($results);
    }
}
