<?php

declare(strict_types=1);

namespace Aitumalow\Tests\Browser;

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\Enums\NodeType;
use Aitumalow\Nodes\Triggers\ManualTrigger;

#[WorkflowNode(key: 'test.lead.status_changed', name: 'Lead status changed', category: 'Test CRM', icon: 'user', type: NodeType::Trigger)]
final class LeadStatusChanged extends ManualTrigger
{
    public static function configSchema(): array
    {
        return [];
    }

    public static function outputSchema(): array
    {
        return ['main' => [
            ['key' => 'lead_id', 'type' => 'integer', 'label' => 'Lead'],
            ['key' => 'old_status', 'type' => 'string', 'label' => 'Previous status'],
            ['key' => 'new_status', 'type' => 'string', 'label' => 'New status'],
        ]];
    }
}
