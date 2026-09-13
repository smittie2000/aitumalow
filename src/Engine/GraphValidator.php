<?php

namespace Aitumalow\Engine;

use Aitumalow\Contracts\ExecutionScopeResolver;
use Aitumalow\Enums\NodeType;
use Aitumalow\Exceptions\WorkflowValidationException;
use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowEdge;
use Aitumalow\Models\WorkflowNode;
use Aitumalow\Registry\NodeRegistry;
use Aitumalow\Services\WorkflowNodeConfigValidator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** @phpstan-type AdjacencyMap array<int, list<int>> */
class GraphValidator
{
    public function __construct(
        private readonly NodeRegistry $registry,
        private readonly WorkflowNodeConfigValidator $configValidator,
        private readonly ExecutionScopeResolver $scopeResolver,
    ) {}

    /**
     * Validate a workflow graph. Throws on failure.
     *
     * @throws WorkflowValidationException
     */
    public function validate(Workflow $workflow): void
    {
        $nodes = $workflow->nodes()->get();
        $edges = $workflow->edges()->get();
        $errors = [];

        $this->checkTriggerExists($nodes, $errors);
        $this->checkNodesRegistered($nodes, $errors);
        $this->checkPortValidity($nodes, $edges, $errors);
        $this->checkStateGraph($nodes, $errors);
        $this->checkCycles($nodes, $edges, $errors);
        $this->checkConnectivity($nodes, $edges, $errors);
        $this->checkNodeConfig($workflow, $nodes, $errors);

        if (! empty($errors)) {
            throw new WorkflowValidationException($errors);
        }
    }

    /**
     * @param  Collection<int, WorkflowNode>  $nodes
     * @param  list<string>  $errors
     */
    private function checkStateGraph(Collection $nodes, array &$errors): void
    {
        $stateKeys = [];

        foreach ($nodes->where('node_key', 'core.wait_resume') as $node) {
            $stateKey = $node->config['state_key'] ?? null;
            if ($stateKey === null || $stateKey === '') {
                continue;
            }
            if (! is_string($stateKey) || ! preg_match('/\A[a-z][a-z0-9_-]*\z/', $stateKey)) {
                $errors[] = "Node '{$node->name}' has an invalid stable state key.";

                continue;
            }
            if (isset($stateKeys[$stateKey])) {
                $errors[] = "Workflow state key [{$stateKey}] is declared more than once.";
            }
            $stateKeys[$stateKey] = true;
        }

        foreach ($nodes->where('node_key', 'core.wait_resume') as $node) {
            $commands = $node->config['commands'] ?? [];
            if (! is_array($commands)) {
                continue;
            }

            $commandKeys = [];
            foreach ($commands as $command) {
                $key = is_array($command) ? ($command['key'] ?? null) : null;
                if (! is_string($key) || ! preg_match('/\A[a-z][a-z0-9_-]*\z/', $key)) {
                    $errors[] = "Node '{$node->name}' has a command without a valid stable key.";

                    continue;
                }
                if (isset($commandKeys[$key])) {
                    $errors[] = "Node '{$node->name}' declares command [{$key}] more than once.";
                }
                $commandKeys[$key] = true;

                $target = $command['target_state'] ?? null;
                if (is_string($target) && $target !== '@previous' && ! isset($stateKeys[$target])) {
                    $errors[] = "Command [{$key}] targets undefined workflow state [{$target}].";
                }
            }
        }
    }

    /**
     * Validate and return errors array (non-throwing).
     *
     * @return string[]
     */
    public function errors(Workflow $workflow): array
    {
        try {
            $this->validate($workflow);

            return [];
        } catch (WorkflowValidationException $e) {
            return $e->errors;
        }
    }

    /**
     * @param  Collection<int, WorkflowNode>  $nodes
     * @param  list<string>  $errors
     */
    private function checkTriggerExists(Collection $nodes, array &$errors): void
    {
        $triggers = $nodes->where('type', NodeType::Trigger);

        if ($triggers->isEmpty()) {
            $errors[] = 'Workflow must have at least one trigger node.';
        }

        if ($triggers->count() > 1) {
            $errors[] = 'Workflow must have exactly one trigger node, found '.$triggers->count().'.';
        }
    }

    /**
     * @param  Collection<int, WorkflowNode>  $nodes
     * @param  list<string>  $errors
     */
    private function checkNodesRegistered(Collection $nodes, array &$errors): void
    {
        foreach ($nodes as $node) {
            if (! $this->registry->has($node->node_key)) {
                $nodeName = $node->name ?: $node->node_key;
                $errors[] = "Node '{$nodeName}' (id:{$node->id}) uses unregistered key: {$node->node_key}";
            }
        }
    }

    /**
     * @param  Collection<int, WorkflowNode>  $nodes
     * @param  Collection<int, WorkflowEdge>  $edges
     * @param  list<string>  $errors
     */
    private function checkPortValidity(Collection $nodes, Collection $edges, array &$errors): void
    {
        $nodeMap = $nodes->keyBy('id');

        foreach ($edges as $edge) {
            $source = $nodeMap->get($edge->source_node_id);
            $target = $nodeMap->get($edge->target_node_id);

            if (! $source) {
                $errors[] = "Edge {$edge->id} references non-existent source node: {$edge->source_node_id}";

                continue;
            }

            if (! $target) {
                $errors[] = "Edge {$edge->id} references non-existent target node: {$edge->target_node_id}";

                continue;
            }

            if ($this->registry->has($source->node_key)) {
                $outputPorts = $this->registry->ports($source->node_key, $source->config ?? [])['output_ports'];
                if (! in_array($edge->source_port, $outputPorts, true)) {
                    $errors[] = "Edge {$edge->id}: source node '{$source->name}' does not have output port '{$edge->source_port}'. Available: ".implode(', ', $outputPorts);
                }
            }

            if ($this->registry->has($target->node_key)) {
                $inputPorts = $this->registry->ports($target->node_key, $target->config ?? [])['input_ports'];

                if (! in_array($edge->target_port, $inputPorts, true)) {
                    $errors[] = "Edge {$edge->id}: target node '{$target->name}' does not have input port '{$edge->target_port}'. Available: ".implode(', ', $inputPorts);
                }
            }
        }
    }

    /**
     * Reject hot cycles while allowing durable state-machine loops.
     *
     * Removing every durable blocking node must leave an acyclic graph. This
     * proves that every possible cycle crosses a wait/update boundary or a
     * positive timer instead of spinning inside one workflow task.
     *
     * @param  Collection<int, WorkflowNode>  $nodes
     * @param  Collection<int, WorkflowEdge>  $edges
     * @param  list<string>  $errors
     */
    private function checkCycles(Collection $nodes, Collection $edges, array &$errors): void
    {
        $blockingNodeIds = $nodes
            ->filter(fn (WorkflowNode $node): bool => $this->isDurableBlockingNode($node))
            ->mapWithKeys(fn (WorkflowNode $node): array => [$node->id => true])
            ->all();

        $adjacency = [];
        foreach ($edges as $edge) {
            if (isset($blockingNodeIds[$edge->source_node_id]) || isset($blockingNodeIds[$edge->target_node_id])) {
                continue;
            }

            $adjacency[$edge->source_node_id][] = $edge->target_node_id;
        }

        // WHITE=0, GRAY=1, BLACK=2
        $colors = [];
        foreach ($nodes as $node) {
            if (isset($blockingNodeIds[$node->id])) {
                continue;
            }

            $colors[$node->id] = 0;
        }

        foreach ($nodes as $node) {
            if (! isset($colors[$node->id])) {
                continue;
            }

            if ($colors[$node->id] === 0) {
                if ($this->dfsCycleCheck($node->id, $adjacency, $colors)) {
                    $errors[] = 'Workflow graph contains a hot cycle. Every cycle must pass through a wait node or a positive delay.';

                    return;
                }
            }
        }
    }

    private function isDurableBlockingNode(WorkflowNode $node): bool
    {
        if ($node->node_key === 'core.wait_resume') {
            return true;
        }

        if ($node->node_key !== 'core.delay') {
            return false;
        }

        return (int) ($node->config['delay_value'] ?? 0) > 0;
    }

    /**
     * @param  AdjacencyMap  $adjacency
     * @param  array<int, 0|1|2>  $colors
     */
    private function dfsCycleCheck(int $nodeId, array $adjacency, array &$colors): bool
    {
        $colors[$nodeId] = 1; // GRAY — visiting

        foreach ($adjacency[$nodeId] ?? [] as $neighbor) {
            if (! isset($colors[$neighbor])) {
                continue;
            }

            if ($colors[$neighbor] === 1) {
                return true; // Back edge — cycle found
            }

            if ($colors[$neighbor] === 0 && $this->dfsCycleCheck($neighbor, $adjacency, $colors)) {
                return true;
            }
        }

        $colors[$nodeId] = 2; // BLACK — done

        return false;
    }

    /**
     * Check that all non-trigger nodes are reachable from the trigger.
     *
     * @param  Collection<int, WorkflowNode>  $nodes
     * @param  Collection<int, WorkflowEdge>  $edges
     * @param  list<string>  $errors
     */
    private function checkConnectivity(Collection $nodes, Collection $edges, array &$errors): void
    {
        $trigger = $nodes->firstWhere('type', NodeType::Trigger);

        if (! $trigger) {
            return; // Already reported by checkTriggerExists
        }

        $adjacency = [];
        foreach ($edges as $edge) {
            $adjacency[$edge->source_node_id][] = $edge->target_node_id;
        }

        $visited = [];
        $this->bfsReach($trigger->id, $adjacency, $visited);

        foreach ($nodes as $node) {
            if ($node->id !== $trigger->id && ! isset($visited[$node->id])) {
                $nodeName = $node->name ?: $node->node_key;
                $errors[] = "Node '{$nodeName}' (id:{$node->id}) is unreachable from the trigger.";
            }
        }
    }

    /**
     * @param  AdjacencyMap  $adjacency
     * @param  array<int, true>  $visited
     */
    private function bfsReach(int $startId, array $adjacency, array &$visited): void
    {
        $queue = [$startId];
        $visited[$startId] = true;

        while (! empty($queue)) {
            $current = array_shift($queue);

            foreach ($adjacency[$current] ?? [] as $neighbor) {
                if (! isset($visited[$neighbor])) {
                    $visited[$neighbor] = true;
                    $queue[] = $neighbor;
                }
            }
        }
    }

    /**
     * @param  Collection<int, WorkflowNode>  $nodes
     * @param  list<string>  $errors
     */
    private function checkNodeConfig(Workflow $workflow, Collection $nodes, array &$errors): void
    {
        $scope = $this->scopeResolver->resolve($workflow);

        foreach ($nodes as $node) {
            if (! $this->registry->has($node->node_key)) {
                continue;
            }

            try {
                $this->configValidator->validate($node->node_key, $node->config ?? [], $scope);
            } catch (ValidationException $exception) {
                $nodeName = $node->name ?: $node->node_key;

                foreach ($exception->errors() as $messages) {
                    foreach ($messages as $message) {
                        $errors[] = "Node '{$nodeName}' (id:{$node->id}): {$message}";
                    }
                }
            }
        }
    }
}
