<?php

namespace Aitumalow\Facades;

use Aitumalow\Services\WorkflowService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Aitumalow\Models\WorkflowRun run(int|\Aitumalow\Models\Workflow $workflow, array<int, array<string, mixed>> $payload = [])
 * @method static \Aitumalow\Models\WorkflowRun start(int|\Aitumalow\Models\Workflow $workflow, \Aitumalow\DTOs\WorkflowStart $command)
 * @method static \Aitumalow\Models\WorkflowRun resume(int|\Aitumalow\Models\WorkflowRun $run, array<int, array<string, mixed>> $payload = [])
 * @method static \Aitumalow\Models\WorkflowCommand command(int|\Aitumalow\Models\WorkflowRun $run, \Aitumalow\DTOs\RunCommand $command)
 * @method static \Aitumalow\Models\WorkflowCommand commandAndWait(int|\Aitumalow\Models\WorkflowRun $run, \Aitumalow\DTOs\RunCommand $command)
 * @method static \Aitumalow\Models\WorkflowCommand synchronizeCommand(int|\Aitumalow\Models\WorkflowCommand $command)
 * @method static \Aitumalow\Models\WorkflowRun cancel(int|\Aitumalow\Models\WorkflowRun $run)
 * @method static \Aitumalow\Models\WorkflowRun replay(int|\Aitumalow\Models\WorkflowRun $run)
 * @method static \Aitumalow\Models\Workflow findByKey(string $key)
 * @method static \Aitumalow\Models\WorkflowRevision installStateWorkflow(\Aitumalow\DTOs\StateWorkflowDefinition $definition, ?string $principalReference = null)
 * @method static \Aitumalow\Models\WorkflowRevision installWorkflow(\Aitumalow\DTOs\WorkflowBlueprint $blueprint, ?string $principalReference = null)
 * @method static \Aitumalow\Models\Workflow create(array<string, mixed> $data)
 * @method static \Aitumalow\Models\Workflow update(int|\Aitumalow\Models\Workflow $workflow, array<string, mixed> $data)
 * @method static void delete(int|\Aitumalow\Models\Workflow $workflow)
 * @method static \Aitumalow\Models\Workflow duplicate(int|\Aitumalow\Models\Workflow $workflow)
 * @method static \Aitumalow\Models\WorkflowRevision publish(int|\Aitumalow\Models\Workflow $workflow, ?string $principalReference = null)
 * @method static \Aitumalow\Models\Workflow activate(int|\Aitumalow\Models\Workflow $workflow, int|\Aitumalow\Models\WorkflowRevision|null $revision = null)
 * @method static \Aitumalow\Models\Workflow restoreDraft(int|\Aitumalow\Models\Workflow $workflow, int|\Aitumalow\Models\WorkflowRevision $revision)
 * @method static \Aitumalow\Models\Workflow deactivate(int|\Aitumalow\Models\Workflow $workflow)
 * @method static array<int, string> validate(int|\Aitumalow\Models\Workflow $workflow)
 * @method static \Aitumalow\Models\WorkflowNode addNode(int|\Aitumalow\Models\Workflow $workflow, string $nodeKey, array<string, mixed> $config = [], ?string $name = null)
 * @method static \Aitumalow\Models\WorkflowNode updateNode(int|\Aitumalow\Models\WorkflowNode $node, array<string, mixed> $data)
 * @method static \Aitumalow\Models\WorkflowEdge connect(int|\Aitumalow\Models\WorkflowNode $source, int|\Aitumalow\Models\WorkflowNode $target, string $sourcePort = 'main', string $targetPort = 'main')
 * @method static void removeNode(int $nodeId)
 * @method static void removeEdge(int $edgeId)
 *
 * @see WorkflowService
 */
class Workflow extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WorkflowService::class;
    }
}
