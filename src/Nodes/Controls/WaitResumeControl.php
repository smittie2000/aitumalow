<?php

namespace Aitumalow\Nodes\Controls;

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\Contracts\NodeInterface;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\DTOs\NodeOutput;
use Aitumalow\Enums\NodeType;
use Aitumalow\Nodes\HasDocumentation;

#[WorkflowNode(key: 'core.wait_resume', name: 'Wait / Resume', category: 'Controls', type: NodeType::Control)]
class WaitResumeControl implements NodeInterface
{
    use HasDocumentation;

    public function inputPorts(): array
    {
        return ['main'];
    }

    public function outputPorts(): array
    {
        return ['resume', 'timeout'];
    }

    public static function configSchema(): array
    {
        return [
            [
                'key' => 'state_key',
                'type' => 'string',
                'label' => 'State key',
                'required' => false,
                'description' => 'Optional stable product state projected while this node is waiting.',
            ],
            [
                'key' => 'metadata',
                'type' => 'json',
                'label' => 'State metadata',
                'required' => false,
                'description' => 'Optional host-owned declarative metadata exposed through the state graph API.',
            ],
            ['key' => 'timeout_seconds', 'type' => 'integer', 'label' => 'Timeout (seconds, 0 = no timeout)', 'required' => false],
            [
                'key' => 'commands',
                'type' => 'array_of_objects',
                'label' => 'Accepted commands',
                'required' => false,
                'description' => 'Stable command keys become output ports. Defaults to resume.',
            ],
        ];
    }

    public static function outputSchema(): array
    {
        return [];
    }

    /** @param array<string, mixed> $config */
    public function execute(NodeInput $input, array $config): NodeOutput
    {
        // DynamicGraphWorkflow owns the durable signal/timeout wait and passes
        // the selected port into this projection activity.
        return NodeOutput::port(
            is_string($config['resume_port'] ?? null) ? $config['resume_port'] : 'resume',
            $input->items,
        );
    }
}
