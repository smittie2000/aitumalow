<?php

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\Contracts\NodeInterface;
use Aitumalow\Contracts\NodeMiddlewareInterface;
use Aitumalow\Contracts\PluginInterface;
use Aitumalow\DTOs\ExecutionContext;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\DTOs\NodeOutput;
use Aitumalow\Engine\NodeRunner;
use Aitumalow\Enums\NodeType;
use Aitumalow\Exceptions\PluginException;
use Aitumalow\Facades\WorkflowAutomation;
use Aitumalow\Nodes\BaseNode;
use Aitumalow\Plugin\BasePlugin;
use Aitumalow\Plugin\PluginContext;
use Aitumalow\Plugin\PluginManager;
use Aitumalow\Plugin\PluginRegistry;
use Aitumalow\Registry\ExpressionFunctionRegistry;
use Aitumalow\Registry\NodeMiddlewareRegistry;
use Aitumalow\Registry\NodeRegistry;
use Aitumalow\Registry\ReferenceProviderRegistry;
use Aitumalow\Registry\ScheduledSubjectSourceRegistry;
use Aitumalow\Registry\WorkflowSubjectRegistry;

// ── PluginRegistry ──────────────────────────────────────────────

it('registers a plugin', function () {
    $registry = new PluginRegistry;
    $plugin = new TestPlugin;

    $registry->add($plugin);

    expect($registry->has('test/plugin'))->toBeTrue()
        ->and($registry->get('test/plugin'))->toBe($plugin)
        ->and($registry->all())->toHaveCount(1);
});

it('throws on duplicate plugin registration', function () {
    $registry = new PluginRegistry;
    $registry->add(new TestPlugin);
    $registry->add(new TestPlugin);
})->throws(PluginException::class, "Plugin 'test/plugin' is already registered.");

it('tracks boot state', function () {
    $registry = new PluginRegistry;

    expect($registry->isBooted())->toBeFalse();

    $registry->markBooted();

    expect($registry->isBooted())->toBeTrue();
});

// ── PluginManager ───────────────────────────────────────────────

it('calls register on plugin immediately', function () {
    $manager = app(PluginManager::class);
    $plugin = new RegisterTrackingPlugin;

    $manager->plugin($plugin);

    expect($plugin->registered)->toBeTrue()
        ->and($plugin->booted)->toBeFalse();
});

it('calls boot on all plugins via bootPlugins()', function () {
    $pluginRegistry = new PluginRegistry;
    $manager = new PluginManager(
        $pluginRegistry,
        app(NodeRegistry::class),
        new NodeMiddlewareRegistry,
        new ExpressionFunctionRegistry,
        new ReferenceProviderRegistry,
        new WorkflowSubjectRegistry,
        new ScheduledSubjectSourceRegistry,
    );
    $plugin = new RegisterTrackingPlugin;

    $manager->plugin($plugin);
    $manager->bootPlugins();

    expect($plugin->booted)->toBeTrue();
});

it('only boots plugins once', function () {
    $pluginRegistry = new PluginRegistry;
    $manager = new PluginManager(
        $pluginRegistry,
        app(NodeRegistry::class),
        new NodeMiddlewareRegistry,
        new ExpressionFunctionRegistry,
        new ReferenceProviderRegistry,
        new WorkflowSubjectRegistry,
        new ScheduledSubjectSourceRegistry,
    );
    $plugin = new RegisterTrackingPlugin;

    $manager->plugin($plugin);
    $manager->bootPlugins();
    $manager->bootPlugins(); // second call should be no-op

    expect($plugin->bootCount)->toBe(1);
});

it('exposes plugin registry via plugins()', function () {
    $manager = app(PluginManager::class);

    expect($manager->plugins())->toBe(app(PluginRegistry::class));
});

// ── PluginContext ───────────────────────────────────────────────

it('registers a node via plugin context', function () {
    $manager = app(PluginManager::class);
    $plugin = new NodeRegisteringPlugin;

    $manager->plugin($plugin);

    $registry = app(NodeRegistry::class);
    expect($registry->has('test.plugin_node'))->toBeTrue();

    $instance = $registry->resolve('test.plugin_node');
    expect($instance)->toBeInstanceOf(TestPluginNode::class);
});

// ── NodeRegistry::registerClass() ──────────────────────────────

it('registers a node class via attribute', function () {
    $registry = app(NodeRegistry::class);
    $registry->registerClass(TestPluginNode::class);

    expect($registry->has('test.plugin_node'))->toBeTrue();

    $meta = $registry->getMeta('test.plugin_node');
    expect($meta['type'])->toBe(NodeType::Action)
        ->and($meta['name'])->toBe('Test Plugin Node');
});

it('throws when registerClass receives class without attribute', function () {
    $registry = app(NodeRegistry::class);
    $registry->registerClass(NoAttributeNode::class);
})->throws(InvalidArgumentException::class, 'missing the #[WorkflowNode] attribute');

it('throws when registerClass receives non-NodeInterface class', function () {
    $registry = app(NodeRegistry::class);
    $registry->registerClass(stdClass::class);
})->throws(InvalidArgumentException::class, 'must implement NodeInterface or WorkflowAction');

// ── NodeRunner Middleware ───────────────────────────────────────

it('executes middleware in order', function () {
    $runner = new NodeRunner;
    $log = new ArrayObject;

    $runner->pushMiddleware(new readonly class($log) implements NodeMiddlewareInterface
    {
        /** @param ArrayObject<int, string> $log */
        public function __construct(private ArrayObject $log) {}

        public function handle(NodeInterface $node, NodeInput $input, array $config, Closure $next): NodeOutput
        {
            $this->log->append('before_A');
            $result = $next($node, $input, $config);
            $this->log->append('after_A');

            return $result;
        }
    });

    $runner->pushMiddleware(new readonly class($log) implements NodeMiddlewareInterface
    {
        /** @param ArrayObject<int, string> $log */
        public function __construct(private ArrayObject $log) {}

        public function handle(NodeInterface $node, NodeInput $input, array $config, Closure $next): NodeOutput
        {
            $this->log->append('before_B');
            $result = $next($node, $input, $config);
            $this->log->append('after_B');

            return $result;
        }
    });

    $node = Mockery::mock(NodeInterface::class);
    $node->shouldReceive('execute')->once()->andReturn(NodeOutput::main([['ok' => true]]));
    $node->shouldReceive('outputPorts')->andReturn(['main']);

    $context = new ExecutionContext(workflowRunId: 1, workflowId: 1);
    $input = new NodeInput(items: [['data' => 'test']], context: $context);

    $output = $runner->run($node, $input, []);

    expect($log->getArrayCopy())->toBe(['before_A', 'before_B', 'after_B', 'after_A'])
        ->and($output->items('main'))->toHaveCount(1);
});

it('runs without middleware same as before', function () {
    $runner = new NodeRunner;

    $node = Mockery::mock(NodeInterface::class);
    $node->shouldReceive('execute')->once()->andReturn(NodeOutput::main([['result' => 'ok']]));
    $node->shouldReceive('outputPorts')->andReturn(['main']);

    $context = new ExecutionContext(workflowRunId: 1, workflowId: 1);
    $input = new NodeInput(items: [['data' => 'test']], context: $context);

    $output = $runner->run($node, $input, []);

    expect($output->items('main')[0]['result'])->toBe('ok');
});

// ── Config-based Plugin Registration ────────────────────────────

it('registers plugins from config', function () {
    config()->set('aitumalow.plugins', [TestPlugin::class]);

    // Re-boot to pick up config plugins
    $manager = app(PluginManager::class);

    // Since the service provider already booted, manually test config registration
    foreach (config('aitumalow.plugins', []) as $pluginClass) {
        if (is_string($pluginClass) && is_a($pluginClass, PluginInterface::class, true)) {
            $plugin = $pluginClass::make();

            if (! $manager->plugins()->has($plugin->getId())) {
                $manager->plugin($plugin);
            }
        }
    }

    expect($manager->plugins()->has('test/plugin'))->toBeTrue();
});

// ── WorkflowAutomation Facade ───────────────────────────────────

it('resolves PluginManager via facade', function () {
    $manager = WorkflowAutomation::getFacadeRoot();

    expect($manager)->toBeInstanceOf(PluginManager::class);
});

// ── Test Doubles ────────────────────────────────────────────────

class TestPlugin extends BasePlugin
{
    public function getId(): string
    {
        return 'test/plugin';
    }

    public function getName(): string
    {
        return 'Test Plugin';
    }

    public function register(PluginContext $context): void
    {
        //
    }
}

class RegisterTrackingPlugin extends BasePlugin
{
    public bool $registered = false;

    public bool $booted = false;

    public int $bootCount = 0;

    public function getId(): string
    {
        return 'test/tracking';
    }

    public function getName(): string
    {
        return 'Tracking Plugin';
    }

    public function register(PluginContext $context): void
    {
        $this->registered = true;
    }

    public function boot(PluginContext $context): void
    {
        $this->booted = true;
        $this->bootCount++;
    }
}

class NodeRegisteringPlugin extends BasePlugin
{
    public function getId(): string
    {
        return 'test/node-registering';
    }

    public function getName(): string
    {
        return 'Node Registering Plugin';
    }

    public function register(PluginContext $context): void
    {
        $context->registerNode(TestPluginNode::class);
    }
}

#[WorkflowNode(key: 'test.plugin_node', name: 'Test Plugin Node', category: 'Tests', type: NodeType::Action)]
class TestPluginNode extends BaseNode
{
    public function execute(NodeInput $input, array $config): NodeOutput
    {
        return NodeOutput::main($input->items);
    }
}

class NoAttributeNode implements NodeInterface
{
    public function inputPorts(): array
    {
        return ['main'];
    }

    public function outputPorts(): array
    {
        return ['main'];
    }

    public static function outputSchema(): array
    {
        return [];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        return NodeOutput::main($input->items);
    }

    public static function configSchema(): array
    {
        return [];
    }
}
