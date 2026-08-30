<?php

declare(strict_types=1);

namespace Aitumalow\DTOs;

use InvalidArgumentException;

/**
 * Host-facing definition for a durable product state machine.
 *
 * Hosts provide business labels, metadata, and one registered transition
 * capability. Aitumalow owns the graph, immutable revisions, command waits,
 * routing, and Durable execution details generated from this definition.
 */
final readonly class StateWorkflowDefinition
{
    /** @var list<WorkflowState> */
    public array $states;

    /** @var list<WorkflowTransition> */
    public array $transitions;

    /**
     * @param  list<mixed>  $states
     * @param  list<mixed>  $transitions
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $initialState,
        public string $transitionCapability,
        array $states,
        array $transitions,
        public ?string $subjectType = null,
        public ?string $description = null,
        public array $settings = [],
    ) {
        if (! preg_match('/\A[a-z][a-z0-9_-]*\z/', $key) || mb_strlen($key) > 100) {
            throw new InvalidArgumentException('State workflow keys must be bounded lowercase stable keys.');
        }

        if (trim($name) === '') {
            throw new InvalidArgumentException('State workflows require a name.');
        }

        if (! preg_match('/\A[a-z][a-z0-9]*(?:\.[a-z][a-z0-9_-]*)+\z/', $transitionCapability)) {
            throw new InvalidArgumentException('State workflows require a namespaced transition capability key.');
        }

        $statesByKey = [];
        foreach ($states as $state) {
            if (! $state instanceof WorkflowState) {
                throw new InvalidArgumentException('State workflows accept only WorkflowState values.');
            }
            if (isset($statesByKey[$state->key])) {
                throw new InvalidArgumentException("Workflow state [{$state->key}] is declared more than once.");
            }
            $statesByKey[$state->key] = true;
        }

        if (! isset($statesByKey[$initialState])) {
            throw new InvalidArgumentException("Initial workflow state [{$initialState}] is not defined.");
        }

        $commandsByState = [];
        foreach ($transitions as $transition) {
            if (! $transition instanceof WorkflowTransition) {
                throw new InvalidArgumentException('State workflows accept only WorkflowTransition values.');
            }
            if (! isset($statesByKey[$transition->fromState])) {
                throw new InvalidArgumentException("Transition [{$transition->key}] starts at an undefined state [{$transition->fromState}].");
            }
            if ($transition->targetState === null) {
                throw new InvalidArgumentException("Transition [{$transition->key}] requires a target state.");
            }
            if ($transition->targetState !== '@previous' && ! isset($statesByKey[$transition->targetState])) {
                throw new InvalidArgumentException("Transition [{$transition->key}] targets an undefined state [{$transition->targetState}].");
            }
            if (isset($commandsByState[$transition->fromState][$transition->key])) {
                throw new InvalidArgumentException("Command [{$transition->key}] is declared more than once for state [{$transition->fromState}].");
            }
            $commandsByState[$transition->fromState][$transition->key] = true;
        }

        $this->states = $states;
        $this->transitions = $transitions;

        json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'initial_state' => $this->initialState,
            'transition_capability' => $this->transitionCapability,
            'subject_type' => $this->subjectType,
            'settings' => $this->settings,
            'states' => array_map(static fn (WorkflowState $state): array => [
                'key' => $state->key,
                'label' => $state->label,
                'metadata' => $state->metadata,
            ], $this->states),
            'transitions' => array_map(static fn (WorkflowTransition $transition): array => [
                'key' => $transition->key,
                'label' => $transition->label,
                'from_state' => $transition->fromState,
                'target_state' => $transition->targetState,
                'metadata' => $transition->metadata,
            ], $this->transitions),
        ];
    }
}
