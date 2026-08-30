<?php

namespace Aitumalow\Nodes\Triggers;

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\Contracts\TriggerInterface;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\DTOs\NodeOutput;
use Aitumalow\Enums\NodeType;
use Aitumalow\Nodes\HasDocumentation;

#[WorkflowNode(key: 'core.schedule', name: 'Schedule', category: 'Triggers', type: NodeType::Trigger)]
class ScheduleTrigger implements TriggerInterface
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
            ['key' => 'cron', 'type' => 'cron', 'label' => 'Cron Expression', 'required' => true, 'default' => '* * * * *'],
            ['key' => 'timezone', 'type' => 'timezone', 'label' => 'Timezone', 'required' => true, 'default' => 'UTC'],
        ];
    }

    public static function outputSchema(): array
    {
        return [
            'main' => [
                ['key' => 'schedule_id', 'type' => 'string', 'label' => 'Durable Schedule ID'],
            ],
        ];
    }

    /** @param array<string, mixed> $config */
    public function register(int $workflowId, int $nodeId, array $config): void
    {
        // WorkflowService synchronizes the complete immutable graph snapshot.
    }

    /** @param array<string, mixed> $config */
    public function unregister(int $workflowId, int $nodeId, array $config): void {}

    public function extractPayload(mixed $event): array
    {
        return [['triggered_at' => now()->toISOString()]];
    }

    /** @param array<string, mixed> $config */
    public function execute(NodeInput $input, array $config): NodeOutput
    {
        return NodeOutput::main($input->items);
    }
}
