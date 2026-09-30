<?php

declare(strict_types=1);

namespace Aitumalow\Tests\Browser;

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\Contracts\WorkflowAction;
use Aitumalow\DTOs\WorkflowContext;
use Illuminate\Support\Str;
use InvalidArgumentException;

#[WorkflowNode(key: 'test.booking.call_client', name: 'Call client with AI agent', category: 'Test calendar', description: 'Captures a call request for each pending booking. The host supplies the phone number and booking context. No real calls are made in this test area.')]
final class CallBookingClient implements WorkflowAction
{
    public function schema(): array
    {
        return [['key' => 'instructions', 'type' => 'textarea', 'label' => 'Agent instructions', 'required' => true,
            'description' => 'Describe what the agent should accomplish. The Laravel host owns the calling provider and permitted tools.']];
    }

    public function outputSchema(): array
    {
        return [['key' => 'call_requested', 'type' => 'boolean', 'label' => 'Call requested']];
    }

    public function handle(WorkflowContext $context): array
    {
        $id = $context->get('record_id');
        if (! is_int($id)) {
            throw new InvalidArgumentException('The host must supply one booking ID per item.');
        }
        $record = Record::query()->where('kind', 'booking')->findOrFail($id);
        if ($record->status !== 'pending') {
            return ['call_requested' => false];
        }
        // Capture the provider boundary, keyed by Durable activity + item for retry deduplication.
        $key = hash('sha256', ($context->activityId() ?? (string) Str::uuid()).':'.$record->id);
        file_put_contents(storage_path('calls/'.$key.'.json'), json_encode([
            'booking_id' => $record->id, 'client' => $record->name, 'phone' => $record->data['phone'],
            'instructions' => $context->config()['instructions'], 'booking' => $record->data,
        ], JSON_THROW_ON_ERROR));

        return ['call_requested' => true];
    }
}
