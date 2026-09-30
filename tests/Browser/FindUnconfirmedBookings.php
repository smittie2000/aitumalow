<?php

declare(strict_types=1);

namespace Aitumalow\Tests\Browser;

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\Contracts\NodeInterface;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\DTOs\NodeOutput;
use Aitumalow\Enums\NodeType;

#[WorkflowNode(key: 'test.calendar.find_unconfirmed', name: 'Find upcoming unconfirmed bookings', category: 'Test calendar', type: NodeType::Action, description: 'Checks the host calendar for pending bookings in the next two days. Each matching booking becomes an item.')]
final class FindUnconfirmedBookings implements NodeInterface
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
        return ['main' => [['key' => 'record_id', 'type' => 'integer', 'label' => 'Booking ID']]];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        return NodeOutput::main(Record::query()->where('kind', 'booking')->where('status', 'pending')->get()
            ->filter(fn (Record $record): bool => $record->data['date'] >= now()->toDateString()
                && $record->data['date'] <= now()->addDays(2)->toDateString())
            ->map(fn (Record $record): array => ['record_id' => $record->id])->values()->all());
    }
}
