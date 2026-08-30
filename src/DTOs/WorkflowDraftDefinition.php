<?php

declare(strict_types=1);

namespace Aitumalow\DTOs;

use InvalidArgumentException;

/** A mutable graph document using request-local aliases instead of database IDs. */
final readonly class WorkflowDraftDefinition
{
    /** @var list<array{id: string, capability: string, name: string|null, config: array<string, mixed>}> */
    public array $nodes;

    /** @var list<array{from: string, to: string, output: string, input: string}> */
    public array $edges;

    /**
     * @param  list<mixed>  $nodes
     * @param  list<mixed>  $edges
     */
    public function __construct(array $nodes, array $edges)
    {
        $ids = [];
        $normalizedNodes = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                throw new InvalidArgumentException('Workflow draft nodes must be objects.');
            }

            $unknown = array_diff(array_keys($node), ['id', 'capability', 'name', 'config']);
            if ($unknown !== []) {
                throw new InvalidArgumentException('Workflow draft node contains unknown fields: '.implode(', ', $unknown).'.');
            }

            $id = $node['id'] ?? null;
            $capability = $node['capability'] ?? null;

            if (! is_string($id)
                || mb_strlen($id) > 100
                || ! preg_match('/\A[a-z][a-z0-9_-]*\z/', $id)) {
                throw new InvalidArgumentException('Workflow draft nodes require bounded lowercase aliases.');
            }

            if (! is_string($capability)
                || mb_strlen($capability) > 100
                || ! preg_match('/\A[a-z][a-z0-9]*(?:\.[a-z][a-z0-9_-]*)+\z/', $capability)) {
                throw new InvalidArgumentException("Workflow draft node [{$id}] requires a namespaced capability key.");
            }

            if (isset($ids[$id])) {
                throw new InvalidArgumentException("Workflow draft node alias [{$id}] is declared more than once.");
            }

            $name = $node['name'] ?? null;
            if ($name !== null && (! is_string($name) || mb_strlen($name) > 255)) {
                throw new InvalidArgumentException("Workflow draft node [{$id}] requires a bounded string name.");
            }

            $config = $node['config'] ?? [];
            if (! is_array($config) || ($config !== [] && array_is_list($config))) {
                throw new InvalidArgumentException("Workflow draft node [{$id}] configuration must be an object.");
            }

            $ids[$id] = true;
            $normalizedNodes[] = [
                'id' => $id,
                'capability' => $capability,
                'name' => $name,
                'config' => $config,
            ];
        }

        $normalizedEdges = [];
        foreach ($edges as $edge) {
            if (! is_array($edge)) {
                throw new InvalidArgumentException('Workflow draft edges must be objects.');
            }

            $unknown = array_diff(array_keys($edge), ['from', 'to', 'output', 'input']);
            if ($unknown !== []) {
                throw new InvalidArgumentException('Workflow draft edge contains unknown fields: '.implode(', ', $unknown).'.');
            }

            $from = $edge['from'] ?? null;
            $to = $edge['to'] ?? null;
            if (! is_string($from) || ! isset($ids[$from]) || ! is_string($to) || ! isset($ids[$to])) {
                throw new InvalidArgumentException('Workflow draft edges must reference declared node aliases.');
            }

            $output = $edge['output'] ?? 'main';
            $input = $edge['input'] ?? 'main';
            if (! is_string($output)
                || $output === ''
                || mb_strlen($output) > 50
                || ! is_string($input)
                || $input === ''
                || mb_strlen($input) > 50) {
                throw new InvalidArgumentException('Workflow draft edge ports must be bounded non-empty strings.');
            }

            $normalizedEdges[] = compact('from', 'to', 'output', 'input');
        }

        $this->nodes = $normalizedNodes;
        $this->edges = $normalizedEdges;

        json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /** @return array{nodes: list<array{id: string, capability: string, name: string|null, config: array<string, mixed>}>, edges: list<array{from: string, to: string, output: string, input: string}>} */
    public function toArray(): array
    {
        return [
            'nodes' => $this->nodes,
            'edges' => $this->edges,
        ];
    }
}
