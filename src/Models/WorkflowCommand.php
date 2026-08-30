<?php

declare(strict_types=1);

namespace Aitumalow\Models;

use Aitumalow\Enums\CommandStatus;
use Aitumalow\Support\ConfiguredModels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workflow_run_id
 * @property string $name
 * @property string $idempotency_key
 * @property int|null $expected_node_id
 * @property array<string, mixed> $payload
 * @property array{tenant_reference: string|null, principal_reference: string|null} $execution_scope
 * @property string|null $durable_update_id
 * @property CommandStatus $status
 * @property array<string, mixed>|null $result
 * @property string|null $error_message
 * @property Carbon|null $applied_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WorkflowRun $run
 */
class WorkflowCommand extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return config('aitumalow.tables.commands', 'aitumalow_workflow_commands');
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'execution_scope' => 'array',
            'status' => CommandStatus::class,
            'result' => 'array',
            'applied_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<WorkflowRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(ConfiguredModels::run(), 'workflow_run_id');
    }
}
