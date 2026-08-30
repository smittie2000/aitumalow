<?php

namespace Aitumalow\Nodes\Annotations;

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\DTOs\NodeOutput;
use Aitumalow\Enums\NodeType;
use Aitumalow\Nodes\BaseNode;

#[WorkflowNode(key: 'core.sticky_note', name: 'Sticky Note', category: 'Annotations', type: NodeType::Annotation)]
class StickyNote extends BaseNode
{
    public static function configSchema(): array
    {
        return [
            ['key' => 'content', 'type' => 'textarea', 'label' => 'Content'],
            ['key' => 'color', 'type' => 'select', 'label' => 'Color', 'options' => ['yellow', 'blue', 'green', 'pink', 'purple'], 'default' => 'yellow'],
        ];
    }

    public function inputPorts(): array
    {
        return [];
    }

    public function outputPorts(): array
    {
        return [];
    }

    /** @param array<string, mixed> $config */
    public function execute(NodeInput $input, array $config): NodeOutput
    {
        return NodeOutput::main($input->items);
    }
}
