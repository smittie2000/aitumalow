<?php

namespace Aitumalow\Nodes\Triggers;

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\Contracts\TriggerInterface;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\DTOs\NodeOutput;
use Aitumalow\Enums\NodeType;
use Aitumalow\Nodes\HasDocumentation;

#[WorkflowNode(key: 'core.manual', name: 'Manual Trigger', category: 'Triggers', type: NodeType::Trigger)]
class ManualTrigger implements TriggerInterface
{
    use HasDocumentation;

    public function inputPorts(): array
    {
        return [];
    }

    public function outputPorts(): array
    {
        return ['main'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'input_schema', 'type' => 'json', 'label' => 'Expected Input Schema', 'required' => false],
        ];
    }

    public static function outputSchema(): array
    {
        return [];
    }

    /** @param array<string, mixed> $config */
    public function register(int $workflowId, int $nodeId, array $config): void {}

    /** @param array<string, mixed> $config */
    public function unregister(int $workflowId, int $nodeId, array $config): void {}

    public function extractPayload(mixed $event): array
    {
        return is_array($event) ? (array_is_list($event) ? $event : [$event]) : [[]];
    }

    /** @param array<string, mixed> $config */
    public function execute(NodeInput $input, array $config): NodeOutput
    {
        return NodeOutput::main($input->items);
    }
}
