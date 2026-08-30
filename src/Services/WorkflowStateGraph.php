<?php

declare(strict_types=1);

namespace Aitumalow\Services;

use Aitumalow\DTOs\WorkflowState;
use Aitumalow\DTOs\WorkflowTransition;
use Aitumalow\Models\WorkflowRevision;
use LogicException;

/**
 * Read-only product-state projection over an immutable workflow revision.
 *
 * Hosts consume stable states and commands without parsing Aitumalow's graph
 * snapshot or depending on private Durable Workflow concepts.
 */
final readonly class WorkflowStateGraph
{
    private ?string $initialState;

    /** @var array<string, WorkflowState> */
    private array $states;

    /** @var array<string, list<WorkflowTransition>> */
    private array $transitions;

    public function __construct(WorkflowRevision $revision)
    {
        $states = [];
        $transitions = [];

        foreach ($revision->definition['nodes'] ?? [] as $node) {
            if (! is_array($node) || ($node['key'] ?? null) !== 'core.wait_resume') {
                continue;
            }

            $config = is_array($node['config'] ?? null) ? $node['config'] : [];
            $stateKey = $config['state_key'] ?? null;
            if (! is_string($stateKey) || $stateKey === '') {
                continue;
            }

            $metadata = is_array($config['metadata'] ?? null) ? $config['metadata'] : [];
            $states[$stateKey] = new WorkflowState(
                key: $stateKey,
                label: is_string($node['name'] ?? null) && $node['name'] !== '' ? $node['name'] : $stateKey,
                metadata: $metadata,
            );

            foreach ($config['commands'] ?? [] as $command) {
                if (! is_array($command) || ! is_string($command['key'] ?? null)) {
                    continue;
                }

                $target = is_string($command['target_state'] ?? null)
                    ? $command['target_state']
                    : null;
                $transitions[$stateKey][] = new WorkflowTransition(
                    key: $command['key'],
                    label: is_string($command['label'] ?? null) && $command['label'] !== ''
                        ? $command['label']
                        : $command['key'],
                    fromState: $stateKey,
                    targetState: $target,
                    metadata: is_array($command['metadata'] ?? null) ? $command['metadata'] : [],
                );
            }
        }

        $this->states = $states;
        $this->transitions = $transitions;
        $initialState = data_get($revision->definition, 'settings.state_workflow.initial_state');
        $this->initialState = is_string($initialState) && $initialState !== '' ? $initialState : null;
    }

    public function initialState(): string
    {
        if ($this->initialState === null || ! isset($this->states[$this->initialState])) {
            throw new LogicException('This revision does not declare an initial product state.');
        }

        return $this->initialState;
    }

    /** @return list<WorkflowState> */
    public function states(): array
    {
        return array_values($this->states);
    }

    public function state(string $key): WorkflowState
    {
        return $this->states[$key]
            ?? throw new LogicException("Workflow state [{$key}] is not defined by this revision.");
    }

    /** @return list<WorkflowTransition> */
    public function transitionsFrom(string $state): array
    {
        $this->state($state);

        return $this->transitions[$state] ?? [];
    }
}
