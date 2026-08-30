<?php

declare(strict_types=1);

namespace Aitumalow\Runtime;

use Aitumalow\Enums\RunStatus;
use Aitumalow\Models\WorkflowRun;
use Workflow\V2\Activity;
use Workflow\V2\Attributes\Type;

#[Type('aitumalow.run_waiting_projection.v1')]
final class ProjectRunWaitingActivity extends Activity
{
    public function handle(int $projectionRunId, ?int $nodeId, ?string $stateKey): void
    {
        WorkflowRun::query()->whereKey($projectionRunId)->update([
            'status' => $nodeId === null ? RunStatus::Running : RunStatus::Waiting,
            'waiting_node_id' => $nodeId,
            'waiting_state' => $stateKey,
        ]);
    }
}
