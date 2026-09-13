<?php

declare(strict_types=1);

namespace Aitumalow\Services;

use Aitumalow\Contracts\ExecutionScopeResolver;
use Aitumalow\Exceptions\WorkflowDraftConflictException;
use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowEdge;
use Aitumalow\Models\WorkflowNode;
use Aitumalow\Registry\NodeRegistry;
use Aitumalow\Support\ConfiguredModels;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Validation\ValidationException;

/** Atomic editor gestures shared by HTTP, PHP, and MCP clients. */
final readonly class WorkflowGraphService
{
    public function __construct(
        private WorkflowGraphSnapshot $snapshots,
        private WorkflowNodeConfigValidator $configs,
        private ExecutionScopeResolver $scopes,
        private NodeRegistry $registry,
        private Factory $validator,
    ) {}

    /** @return array<string, mixed> */
    public function get(int|Workflow $workflow): array
    {
        return $this->locked($workflow, fn (Workflow $locked): array => $this->state($locked));
    }

    /**
     * One UUID identifies one immutable request, including its expected hash.
     * A retry returns the receipt and current graph without applying it again.
     *
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    public function edit(int|Workflow $workflow, array $request): array
    {
        $request = $this->validator->make($request, [
            'request_id' => ['required', 'uuid'],
            'expected_hash' => ['required', 'string', 'size:64'],
            'operation' => ['required', 'in:add_node,update_node,remove,connect,move_nodes,pin,unpin,undo,redo'],
            'data' => ['present', 'array'],
        ])->validate();

        return $this->locked($workflow, function (Workflow $locked) use ($request): array {
            $fingerprint = $this->snapshots->hash($request);
            $existing = $locked->graphEdits()->where('request_id', $request['request_id'])->first();
            if ($existing !== null) {
                if (! hash_equals($existing->request_hash, $fingerprint)) {
                    throw new WorkflowDraftConflictException;
                }

                return [...$this->state($locked), 'edit' => $existing->receipt()];
            }

            $before = $this->snapshots->capture($locked);
            $beforeHash = $this->snapshots->hash($before);
            if (! hash_equals($beforeHash, $request['expected_hash'])) {
                throw new WorkflowDraftConflictException;
            }

            $created = $this->apply($locked, $request['operation'], $request['data'], $beforeHash);
            $after = $this->snapshots->capture($locked);
            $edit = $locked->graphEdits()->create([
                'request_id' => $request['request_id'],
                'request_hash' => $fingerprint,
                'operation' => $request['operation'],
                'before' => $before,
                'after' => $after,
                'before_hash' => $beforeHash,
                'after_hash' => $this->snapshots->hash($after),
                'created_node_id' => $created,
            ]);

            return [...$this->state($locked), 'edit' => $edit->receipt()];
        });
    }

    /** @param array<string, mixed> $data */
    private function apply(Workflow $workflow, string $operation, array $data, string $currentHash): ?int
    {
        if (in_array($operation, ['undo', 'redo'], true)) {
            $data = $this->validate($data, ['edit_id' => 'required|integer|min:1']);
            $edit = $workflow->graphEdits()->whereKey($data['edit_id'])->firstOrFail();
            $expected = $operation === 'undo' ? $edit->after_hash : $edit->before_hash;
            if (in_array($edit->operation, ['undo', 'redo'], true) || ! hash_equals($expected, $currentHash)) {
                throw new WorkflowDraftConflictException;
            }
            $this->restore($workflow, $operation === 'undo' ? $edit->before : $edit->after);

            return null;
        }

        if ($operation === 'add_node') {
            $data = $this->validate($data, [
                'node_key' => 'required|string|max:100', 'name' => 'nullable|string|max:255',
                'config' => 'sometimes|array', 'position_x' => 'required|integer', 'position_y' => 'required|integer',
                'source' => 'sometimes|array:node_id,port|required_array_keys:node_id,port', 'source.node_id' => 'required_with:source|integer|min:1',
                'source.port' => 'required_with:source|string|max:100',
                'edge_id' => 'sometimes|integer|min:1|prohibits:source',
                'input_port' => 'required_with:source,edge_id|string|max:100',
                'output_port' => 'required_with:edge_id|string|max:100',
            ]);
            $config = $this->configs->validate($data['node_key'], $data['config'] ?? [], $this->scopes->resolve($workflow), allowIncomplete: true);
            $node = $workflow->nodes()->create([
                'node_key' => $data['node_key'], 'name' => $data['name'] ?? null,
                'type' => $this->registry->getMeta($data['node_key'])['type'], 'config' => $config,
                'position_x' => $data['position_x'], 'position_y' => $data['position_y'],
            ]);
            if (isset($data['edge_id'])) {
                $edge = $workflow->edges()->whereKey($data['edge_id'])->firstOrFail();
                $targetId = $edge->target_node_id;
                $targetPort = $edge->target_port;
                $this->checkConnection($workflow, $edge->source_node_id, $node->id, $edge->source_port, $data['input_port']);
                $edge->update(['target_node_id' => $node->id, 'target_port' => $data['input_port']]);
                $this->connect($workflow, $node->id, $targetId, $data['output_port'], $targetPort);
            } elseif (isset($data['source'])) {
                $this->connect($workflow, (int) $data['source']['node_id'], $node->id, $data['source']['port'], $data['input_port']);
            }

            return $node->id;
        }

        if ($operation === 'connect') {
            $data = $this->validate($data, [
                'source_node_id' => 'required|integer|min:1', 'target_node_id' => 'required|integer|min:1',
                'source_port' => 'required|string|max:100', 'target_port' => 'required|string|max:100',
            ]);
            $this->connect($workflow, (int) $data['source_node_id'], (int) $data['target_node_id'], $data['source_port'], $data['target_port']);
        } elseif ($operation === 'remove') {
            $data = $this->validate($data, [
                'node_ids' => 'present|array|max:500', 'node_ids.*' => 'integer|min:1|distinct',
                'edge_ids' => 'present|array|max:2000', 'edge_ids.*' => 'integer|min:1|distinct',
            ]);
            // Resolve every ID before deleting anything, including explicitly selected incident edges.
            $nodes = $workflow->nodes()->findOrFail($data['node_ids']);
            $workflow->edges()->findOrFail($data['edge_ids']);
            $workflow->edges()->where(fn ($query) => $query->whereIn('id', $data['edge_ids'])
                ->orWhereIn('source_node_id', $data['node_ids'])->orWhereIn('target_node_id', $data['node_ids']))->delete();
            $nodes->each(fn (WorkflowNode $node) => $node->delete());
        } elseif ($operation === 'move_nodes') {
            $data = $this->validate($data, [
                'positions' => 'required|array|max:500', 'positions.*' => 'array:node_id,position_x,position_y',
                'positions.*.node_id' => 'required|integer|min:1|distinct',
                'positions.*.position_x' => 'required|integer', 'positions.*.position_y' => 'required|integer',
            ]);
            foreach ($data['positions'] as $position) {
                $workflow->nodes()->whereKey($position['node_id'])->firstOrFail()->update([
                    'position_x' => $position['position_x'], 'position_y' => $position['position_y'],
                ]);
            }
        } else {
            $rules = ['node_id' => 'required|integer|min:1'];
            if ($operation === 'update_node') {
                $rules += ['name' => 'sometimes|nullable|string|max:255', 'config' => 'sometimes|array'];
            } elseif ($operation === 'pin') {
                $rules += ['source' => 'required|in:run,manual', 'node_run_id' => 'required_if:source,run|integer|min:1',
                    'input' => 'sometimes|array|list', 'input.*' => 'array',
                    'output' => 'sometimes|array', 'output.*' => 'array|list', 'output.*.*' => 'array'];
            }
            $data = $this->validate($data, $rules);
            $node = $workflow->nodes()->whereKey($data['node_id'])->firstOrFail();
            if ($operation === 'update_node') {
                unset($data['node_id']);
                if (isset($data['config'])) {
                    $data['config'] = $this->configs->validate($node->node_key, $data['config'], $this->scopes->resolve($workflow), allowIncomplete: true);
                }
                $node->update($data);
            } elseif ($operation === 'pin') {
                if ($data['source'] === 'run') {
                    $run = ConfiguredModels::nodeRun()::query()->where('node_id', $node->id)
                        ->whereHas('workflowRun', fn ($query) => $query->where('workflow_id', $workflow->id))->whereKey($data['node_run_id'])->firstOrFail();
                    $pinned = ['input' => $run->input, 'output' => $run->output, 'source_run_id' => $run->workflow_run_id];
                } else {
                    $pinned = array_intersect_key($data, array_flip(['input', 'output']));
                }
                $node->update(['pinned_data' => $pinned]);
            } else {
                $node->update(['pinned_data' => null]);
            }
        }

        return null;
    }

    private function connect(Workflow $workflow, int $sourceId, int $targetId, string $sourcePort, string $targetPort): WorkflowEdge
    {
        $this->checkConnection($workflow, $sourceId, $targetId, $sourcePort, $targetPort);

        return $workflow->edges()->firstOrCreate([
            'source_node_id' => $sourceId, 'target_node_id' => $targetId,
            'source_port' => $sourcePort, 'target_port' => $targetPort,
        ]);
    }

    private function checkConnection(Workflow $workflow, int $sourceId, int $targetId, string $sourcePort, string $targetPort): void
    {
        $source = $workflow->nodes()->findOrFail($sourceId);
        $target = $workflow->nodes()->findOrFail($targetId);
        $outputs = $this->registry->ports($source->node_key, $source->config ?? [])['output_ports'];
        $inputs = $this->registry->ports($target->node_key, $target->config ?? [])['input_ports'];
        if (! in_array($sourcePort, $outputs, true) || ! in_array($targetPort, $inputs, true)) {
            throw ValidationException::withMessages(['connection' => 'Choose existing output and input ports for this connection.']);
        }
    }

    /** @param array<string, mixed> $snapshot */
    private function restore(Workflow $workflow, array $snapshot): void
    {
        $workflow->edges()->whereNotIn('id', array_column($snapshot['edges'], 'id'))->delete();
        $workflow->nodes()->whereNotIn('id', array_column($snapshot['nodes'], 'id'))->delete();

        foreach ($snapshot['nodes'] as $attributes) {
            $node = $workflow->nodes()->whereKey($attributes['id'])->first();
            if ($node === null || $node->config !== $attributes['config']) {
                $this->configs->validate($attributes['node_key'], $attributes['config'] ?? [], $this->scopes->resolve($workflow), allowIncomplete: true);
            }
            if ($node === null && ConfiguredModels::node()::query()->whereKey($attributes['id'])->exists()) {
                throw new WorkflowDraftConflictException;
            }
            $workflow->nodes()->updateOrCreate(['id' => $attributes['id']], $attributes);
        }
        foreach ($snapshot['edges'] as $attributes) {
            if (! $workflow->edges()->whereKey($attributes['id'])->exists() && ConfiguredModels::edge()::query()->whereKey($attributes['id'])->exists()) {
                throw new WorkflowDraftConflictException;
            }
            $workflow->edges()->updateOrCreate(['id' => $attributes['id']], $attributes);
        }
    }

    /** @return array<string, mixed> */
    private function state(Workflow $workflow): array
    {
        $snapshot = $this->snapshots->capture($workflow);

        return ['workflow' => $workflow->load(['tags', 'folder', 'activeRevision']), 'hash' => $this->snapshots->hash($snapshot)];
    }

    /**
     * @template TResult
     *
     * @param  callable(Workflow): TResult  $callback
     * @return TResult
     */
    private function locked(int|Workflow $workflow, callable $callback): mixed
    {
        $workflow = $workflow instanceof Workflow ? $workflow : ConfiguredModels::workflow()::query()->findOrFail($workflow);

        return $workflow->getConnection()->transaction(fn () => $callback($workflow->newQuery()->lockForUpdate()->findOrFail($workflow->id)));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $rules
     * @return array<string, mixed>
     */
    private function validate(array $data, array $rules): array
    {
        return $this->validator->make($data, $rules)->validate();
    }
}
