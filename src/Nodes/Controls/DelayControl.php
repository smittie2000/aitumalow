<?php

namespace Aitumalow\Nodes\Controls;

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\DTOs\NodeOutput;
use Aitumalow\Enums\NodeType;
use Aitumalow\Nodes\BaseNode;

#[WorkflowNode(key: 'core.delay', name: 'Delay', category: 'Controls', type: NodeType::Control)]
class DelayControl extends BaseNode
{
    public function outputPorts(): array
    {
        return ['main'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'delay_type', 'type' => 'select', 'label' => 'Delay Type', 'options' => ['seconds', 'minutes', 'hours'], 'required' => true],
            ['key' => 'delay_value', 'type' => 'integer', 'label' => 'Delay Value', 'required' => true],
        ];
    }

    /** @param array<string, mixed> $config */
    public function execute(NodeInput $input, array $config): NodeOutput
    {
        // DynamicGraphWorkflow records the timer in Durable history before
        // invoking this projection activity.
        return NodeOutput::main($input->items);
    }
}
