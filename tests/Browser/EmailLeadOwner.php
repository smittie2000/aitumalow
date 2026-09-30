<?php

declare(strict_types=1);

namespace Aitumalow\Tests\Browser;

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\Contracts\WorkflowAction;
use Aitumalow\DTOs\WorkflowContext;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

#[WorkflowNode(key: 'test.lead.email_owner', name: 'Email lead owner', category: 'Test CRM', icon: 'mail')]
final class EmailLeadOwner implements WorkflowAction
{
    public function schema(): array
    {
        return [
            ['key' => 'subject', 'type' => 'string', 'label' => 'Email subject', 'required' => true],
            ['key' => 'message', 'type' => 'textarea', 'label' => 'Message', 'required' => true,
                'description' => 'The lead name and previous/new status are appended automatically. The recipient is the lead owner.'],
        ];
    }

    public function outputSchema(): array
    {
        return [['key' => 'recipient', 'type' => 'string', 'label' => 'Recipient']];
    }

    public function handle(WorkflowContext $context): array
    {
        $leadId = $context->get('lead_id');
        if (! is_int($leadId)) {
            throw new InvalidArgumentException('A lead status event must identify one lead.');
        }
        $lead = Lead::query()->findOrFail($leadId);
        $message = $context->config()['message']."\n\n{$lead->name}: ".$context->get('old_status').' → '.$context->get('new_status');
        Mail::raw($message, function ($mail) use ($lead, $context): void {
            $mail->to($lead->owner_email)->subject($context->config()['subject']);
        });

        return ['recipient' => $lead->owner_email];
    }
}
