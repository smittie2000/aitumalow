<?php

declare(strict_types=1);

namespace Aitumalow\Tests\Browser;

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\Contracts\NodeInterface;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\DTOs\NodeOutput;
use Aitumalow\Enums\NodeType;

#[WorkflowNode(key: 'test.ticket.find_open', name: 'Find open tickets', category: 'Test support', type: NodeType::Action, description: 'Each open ticket becomes an item for the next step. Closed tickets are excluded.')]
final class FindOpenTickets implements NodeInterface
{
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
        return [];
    }

    public static function outputSchema(): array
    {
        return ['main' => [['key' => 'record_id', 'type' => 'integer', 'label' => 'Ticket ID']]];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        return NodeOutput::main(Record::query()->where('kind', 'ticket')->where('status', 'open')->get()
            ->map(fn (Record $record): array => ['record_id' => $record->id])->all());
    }
}
