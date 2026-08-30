<?php

namespace Aitumalow\Nodes\Transformers;

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\DTOs\NodeOutput;
use Aitumalow\Enums\NodeType;
use Aitumalow\Nodes\BaseNode;

#[WorkflowNode(key: 'core.set_fields', name: 'Set Fields', category: 'Transformers', type: NodeType::Transformer)]
class SetFieldsTransformer extends BaseNode
{
    public static function configSchema(): array
    {
        return [
            ['key' => 'fields', 'type' => 'keyvalue', 'label' => 'Fields to set', 'required' => true, 'supports_expression' => true],
            ['key' => 'keep_existing', 'type' => 'boolean', 'label' => 'Keep existing fields', 'required' => false],
        ];
    }

    public static function outputSchema(): array
    {
        return [
            'main' => [['key' => '*', 'type' => 'dynamic', 'label' => 'Fields defined in config']],
        ];
    }

    /** @param array<string, mixed> $config */
    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $results = [];

        foreach ($input->items as $item) {
            $base = ($config['keep_existing'] ?? true) ? $item : [];
            $results[] = array_merge($base, $config['fields']);
        }

        return NodeOutput::main($results);
    }
}
