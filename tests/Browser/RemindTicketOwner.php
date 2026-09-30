<?php

declare(strict_types=1);

namespace Aitumalow\Tests\Browser;

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\Contracts\WorkflowAction;
use Aitumalow\DTOs\WorkflowContext;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

#[WorkflowNode(key: 'test.ticket.remind_owner', name: 'Remind ticket owner', category: 'Test support', description: 'Emails the owner of each incoming ticket. Rechecks that the ticket is still open.')]
final class RemindTicketOwner implements WorkflowAction
{
    public function schema(): array
    {
        return [['key' => 'message', 'type' => 'textarea', 'label' => 'Reminder message', 'required' => true]];
    }

    public function outputSchema(): array
    {
        return [['key' => 'sent', 'type' => 'boolean', 'label' => 'Sent']];
    }

    public function handle(WorkflowContext $context): array
    {
        $id = $context->get('record_id');
        if (! is_int($id)) {
            throw new InvalidArgumentException('The host must supply one ticket ID per item.');
        }
        $record = Record::query()->where('kind', 'ticket')->findOrFail($id);
        if ($record->status !== 'open') {
            return ['sent' => false];
        }
        Mail::raw($context->config()['message']."\nTicket: {$record->name}", function ($mail) use ($record): void {
            $mail->to($record->data['owner_email'])->subject('Open ticket follow-up');
        });

        return ['sent' => true];
    }
}
