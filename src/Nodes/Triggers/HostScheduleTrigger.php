<?php

declare(strict_types=1);

namespace Aitumalow\Nodes\Triggers;

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\Contracts\TriggerInterface;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\DTOs\NodeOutput;
use Aitumalow\Enums\NodeType;
use Aitumalow\Nodes\HasDocumentation;

#[WorkflowNode(key: 'core.host_schedule', name: 'Host subject schedule', category: 'Triggers', type: NodeType::Trigger)]
final class HostScheduleTrigger implements TriggerInterface
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
            ['key' => 'source', 'type' => 'string', 'label' => 'Registered subject source', 'required' => true],
            ['key' => 'configuration', 'type' => 'json', 'label' => 'Source configuration', 'required' => true],
        ];
    }

    public static function outputSchema(): array
    {
        return [];
    }

    public function register(int $workflowId, int $nodeId, array $config): void {}

    public function unregister(int $workflowId, int $nodeId, array $config): void {}

    public function extractPayload(mixed $event): array
    {
        return is_array($event) ? [$event] : [[]];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        return NodeOutput::main($input->items);
    }
}
