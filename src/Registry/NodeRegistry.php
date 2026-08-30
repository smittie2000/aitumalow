<?php

namespace Aitumalow\Registry;

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\Contracts\NodeInterface;
use Aitumalow\Contracts\TriggerInterface;
use Aitumalow\Contracts\WorkflowAction;
use Aitumalow\Enums\NodeType;
use Aitumalow\Exceptions\NodeNotFoundException;
use Aitumalow\Nodes\WorkflowActionNode;
use ReflectionClass;

class NodeRegistry
{
    private const string KEY_PATTERN = '/\A[a-z][a-z0-9]*(?:\.[a-z][a-z0-9_-]*)+\z/';

    /** @var array<string, array{class: class-string, name: string, category: string, icon: string, description: string, type: NodeType}> */
    private array $nodes = [];

    /** @param class-string $class */
    public function registerClass(string $class): void
    {
        if (! is_a($class, NodeInterface::class, true) && ! is_a($class, WorkflowAction::class, true)) {
            throw new \InvalidArgumentException("{$class} must implement NodeInterface or WorkflowAction.");
        }

        $attribute = $this->readAttribute($class);

        if (! $attribute) {
            throw new \InvalidArgumentException("{$class} is missing the #[WorkflowNode] attribute.");
        }

        if (strlen($attribute->key) > 100 || ! preg_match(self::KEY_PATTERN, $attribute->key)) {
            throw new \InvalidArgumentException(
                "Capability key [{$attribute->key}] must be a lowercase namespaced key such as app.ticket.create.",
            );
        }

        if (isset($this->nodes[$attribute->key])) {
            throw new \InvalidArgumentException("Capability key [{$attribute->key}] is already registered.");
        }

        $this->nodes[$attribute->key] = [
            'class' => $class,
            'name' => $attribute->name,
            'category' => $attribute->category,
            'icon' => $attribute->icon,
            'description' => $attribute->description,
            'type' => $this->nodeType($class, $attribute),
        ];
    }

    public function resolve(string $key): NodeInterface
    {
        if (! isset($this->nodes[$key])) {
            throw new NodeNotFoundException("Node not found: {$key}");
        }

        $instance = app($this->nodes[$key]['class']);

        return $instance instanceof WorkflowAction
            ? new WorkflowActionNode($instance)
            : $instance;
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return array_map(function (string $key, array $meta): array {
            $instance = app($meta['class']);
            $node = $instance instanceof WorkflowAction ? new WorkflowActionNode($instance) : $instance;

            return [
                'key' => $key,
                'namespace' => explode('.', $key, 2)[0],
                'name' => $meta['name'],
                'category' => $meta['category'],
                'icon' => $meta['icon'],
                'description' => $meta['description'],
                'type' => $meta['type']->value,
                'input_ports' => $node->inputPorts(),
                'output_ports' => $node->outputPorts(),
                'config_schema' => $instance instanceof WorkflowAction ? $instance->schema() : $meta['class']::configSchema(),
                'output_schema' => $instance instanceof WorkflowAction
                    ? ['main' => $instance->outputSchema()]
                    : $meta['class']::outputSchema(),
                'documentation' => method_exists($meta['class'], 'documentation') ? $meta['class']::documentation() : null,
            ];
        }, array_keys($this->nodes), array_values($this->nodes));
    }

    /** @return array<int, array<string, mixed>> */
    public function ofType(NodeType $type): array
    {
        return array_values(array_filter($this->all(), fn (array $node) => $node['type'] === $type->value));
    }

    public function has(string $key): bool
    {
        return isset($this->nodes[$key]);
    }

    /** @return array{class: class-string, name: string, category: string, icon: string, description: string, type: NodeType}|null */
    public function getMeta(string $key): ?array
    {
        return $this->nodes[$key] ?? null;
    }

    /** @return array<string, mixed>|null */
    public function definition(string $key): ?array
    {
        foreach ($this->all() as $definition) {
            if ($definition['key'] === $key) {
                return $definition;
            }
        }

        return null;
    }

    /** @param class-string $class */
    private function readAttribute(string $class): ?WorkflowNode
    {
        $attributes = new ReflectionClass($class)->getAttributes(WorkflowNode::class);

        return $attributes ? $attributes[0]->newInstance() : null;
    }

    /** @param class-string $class */
    private function nodeType(string $class, WorkflowNode $attribute): NodeType
    {
        if ($attribute->type) {
            return $attribute->type;
        }

        if (is_a($class, WorkflowAction::class, true)) {
            return NodeType::Action;
        }

        if (is_a($class, TriggerInterface::class, true)) {
            return NodeType::Trigger;
        }

        throw new \InvalidArgumentException("{$class} must declare a node type.");
    }
}
