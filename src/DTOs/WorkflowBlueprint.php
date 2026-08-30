<?php

declare(strict_types=1);

namespace Aitumalow\DTOs;

use InvalidArgumentException;

/** A stable, host-facing workflow graph that never exposes database node IDs. */
final readonly class WorkflowBlueprint
{
    /** @var list<array{id: string, capability: string, name: string|null, config: array<string, mixed>}> */
    public array $nodes;

    /** @var list<array{from: string, to: string, output: string, input: string}> */
    public array $edges;

    /**
     * @param  list<mixed>  $nodes
     * @param  list<mixed>  $edges
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        public string $key,
        public string $name,
        array $nodes,
        array $edges,
        public ?string $subjectType = null,
        public ?string $description = null,
        public array $settings = [],
    ) {
        if (! preg_match('/\A[a-z][a-z0-9_-]*\z/', $key) || mb_strlen($key) > 100) {
            throw new InvalidArgumentException('Workflow blueprint keys must be bounded lowercase stable keys.');
        }
        if (trim($name) === '') {
            throw new InvalidArgumentException('Workflow blueprints require a name.');
        }

        $ids = [];
        $normalizedNodes = [];
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                throw new InvalidArgumentException('Workflow blueprint nodes must be arrays.');
            }

            $id = $node['id'] ?? null;
            $capability = $node['capability'] ?? null;
            if (! is_string($id) || ! preg_match('/\A[a-z][a-z0-9_-]*\z/', $id)) {
                throw new InvalidArgumentException('Blueprint nodes require stable lowercase IDs.');
            }
            if (! is_string($capability) || ! str_contains($capability, '.')) {
                throw new InvalidArgumentException("Blueprint node [{$id}] requires a namespaced capability key.");
            }
            if (isset($ids[$id])) {
                throw new InvalidArgumentException("Blueprint node [{$id}] is declared more than once.");
            }
            $ids[$id] = true;
            $name = $node['name'] ?? null;
            if ($name !== null && ! is_string($name)) {
                throw new InvalidArgumentException("Blueprint node [{$id}] requires a string name.");
            }
            $normalizedNodes[] = [
                'id' => $id,
                'capability' => $capability,
                'name' => $name,
                'config' => self::configuration($node['config'] ?? [], $id),
            ];
        }

        $normalizedEdges = [];
        foreach ($edges as $edge) {
            if (! is_array($edge)) {
                throw new InvalidArgumentException('Workflow blueprint edges must be arrays.');
            }

            $from = $edge['from'] ?? null;
            $to = $edge['to'] ?? null;
            if (! is_string($from) || ! isset($ids[$from]) || ! is_string($to) || ! isset($ids[$to])) {
                throw new InvalidArgumentException('Blueprint edges must reference declared stable node IDs.');
            }
            $output = $edge['output'] ?? 'main';
            $input = $edge['input'] ?? 'main';
            if (! is_string($output) || $output === '' || ! is_string($input) || $input === '') {
                throw new InvalidArgumentException('Blueprint edge ports must be non-empty strings.');
            }
            $normalizedEdges[] = compact('from', 'to', 'output', 'input');
        }

        $this->nodes = $normalizedNodes;
        $this->edges = $normalizedEdges;

        json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'subject_type' => $this->subjectType,
            'settings' => $this->settings,
            'nodes' => $this->nodes,
            'edges' => $this->edges,
        ];
    }

    /** @return array<string, mixed> */
    private static function configuration(mixed $configuration, string $node): array
    {
        if (! is_array($configuration) || ($configuration !== [] && array_is_list($configuration))) {
            throw new InvalidArgumentException("Blueprint node [{$node}] configuration must be an object-shaped array.");
        }

        return $configuration;
    }
}
