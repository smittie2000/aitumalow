<?php

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\Contracts\NodeInterface;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\DTOs\NodeOutput;
use Aitumalow\Enums\NodeType;
use Aitumalow\Exceptions\NodeNotFoundException;
use Aitumalow\Nodes\Triggers\ManualTrigger;
use Aitumalow\Registry\NodeRegistry;

beforeEach(function () {
    $this->registry = app(NodeRegistry::class);
});

it('registers built-in capabilities under stable keys', function () {
    expect($this->registry->has('core.manual'))->toBeTrue()
        ->and($this->registry->has('core.if_condition'))->toBeTrue()
        ->and($this->registry->has('core.set_fields'))->toBeTrue()
        ->and($this->registry->has('core.loop'))->toBeTrue()
        ->and($this->registry->has('core.filter'))->toBeTrue();
});

it('resolves a node to a NodeInterface instance', function () {
    $node = $this->registry->resolve('core.manual');

    expect($node)->toBeInstanceOf(ManualTrigger::class);
});

it('throws on unknown node key', function () {
    $this->registry->resolve('does_not_exist');
})->throws(NodeNotFoundException::class);

it('returns all registered nodes with metadata', function () {
    $all = $this->registry->all();

    expect(count($all))->toBeGreaterThanOrEqual(10);

    $manual = collect($all)->firstWhere('key', 'core.manual');
    expect($manual)->not->toBeNull()
        ->and($manual['namespace'])->toBe('core')
        ->and($manual['name'])->toBe('Manual Trigger')
        ->and($manual['category'])->toBe('Triggers')
        ->and($manual['type'])->toBe('trigger')
        ->and($manual['input_ports'])->toBe([])
        ->and($manual['output_ports'])->toContain('main')
        ->and($manual)->toHaveKey('config_schema');
});

it('filters by node type', function () {
    $triggers = $this->registry->ofType(NodeType::Trigger);

    expect(collect($triggers)->pluck('key')->all())->toBe([
        'core.manual',
        'core.host_schedule',
        'core.schedule',
    ])
        ->and(collect($triggers)->every(fn ($n) => $n['type'] === 'trigger'))->toBeTrue();
});

it('allows explicit capability registration', function () {
    $this->registry->registerClass(DummyRegistryNode::class);

    expect($this->registry->has('test.custom_dummy'))->toBeTrue();

    $instance = $this->registry->resolve('test.custom_dummy');
    expect($instance)->toBeInstanceOf(DummyRegistryNode::class);
});

it('rejects duplicate capability keys', function () {
    $this->registry->registerClass(DummyRegistryNode::class);
    $this->registry->registerClass(DummyRegistryNode::class);
})->throws(InvalidArgumentException::class, 'Capability key [test.custom_dummy] is already registered.');

it('rejects keys without a namespace', function () {
    $this->registry->registerClass(InvalidKeyRegistryNode::class);
})->throws(InvalidArgumentException::class, 'must be a lowercase namespaced key');

it('does not expose executable class names in the catalog', function () {
    $catalog = json_encode($this->registry->all(), JSON_THROW_ON_ERROR);

    expect($catalog)->not->toContain(ManualTrigger::class);
});

it('returns metadata for a single key', function () {
    $meta = $this->registry->getMeta('core.manual');

    expect($meta)->not->toBeNull()
        ->toHaveKeys(['class', 'name', 'category', 'icon', 'type']);
});

it('returns null for unknown key metadata', function () {
    expect($this->registry->getMeta('nonexistent'))->toBeNull();
});

it('checks key existence with has()', function () {
    expect($this->registry->has('core.manual'))->toBeTrue()
        ->and($this->registry->has('manual'))->toBeFalse()
        ->and($this->registry->has('nope'))->toBeFalse();
});

// ── Dummy Node ───────────────────────────────────────────────────

#[WorkflowNode(key: 'test.custom_dummy', name: 'Custom Dummy', category: 'Tests', type: NodeType::Action)]
class DummyRegistryNode implements NodeInterface
{
    /** @return array<int, string> */
    public function inputPorts(): array
    {
        return ['main'];
    }

    /** @return array<int, string> */
    public function outputPorts(): array
    {
        return ['main'];
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    public static function outputSchema(): array
    {
        return [];
    }

    /** @param array<string, mixed> $config */
    public function execute(NodeInput $input, array $config): NodeOutput
    {
        return NodeOutput::main($input->items);
    }

    /** @return array<int, array<string, mixed>> */
    public static function configSchema(): array
    {
        return [];
    }
}

#[WorkflowNode(key: 'invalid_key', name: 'Invalid', category: 'Tests', type: NodeType::Action)]
class InvalidKeyRegistryNode extends DummyRegistryNode {}
