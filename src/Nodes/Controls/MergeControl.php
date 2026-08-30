<?php

namespace Aitumalow\Nodes\Controls;

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\Contracts\NodeInterface;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\DTOs\NodeOutput;
use Aitumalow\Enums\NodeType;
use Aitumalow\Nodes\HasDocumentation;

#[WorkflowNode(key: 'core.merge', name: 'Merge', category: 'Controls', type: NodeType::Control)]
class MergeControl implements NodeInterface
{
    use HasDocumentation;

    public function inputPorts(): array
    {
        return ['main_1', 'main_2', 'main_3', 'main_4'];
    }

    public function outputPorts(): array
    {
        return ['main'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'mode', 'type' => 'select', 'label' => 'Merge Mode', 'options' => ['append', 'zip', 'wait_all'], 'required' => false],
        ];
    }

    public static function outputSchema(): array
    {
        return [];
    }

    /** @param array<string, mixed> $config */
    public function execute(NodeInput $input, array $config): NodeOutput
    {
        // Items from all input ports are already merged by DynamicGraphWorkflow.
        return NodeOutput::main($input->items);
    }
}
