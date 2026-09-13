<?php

namespace Aitumalow\Support;

use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowCommand;
use Aitumalow\Models\WorkflowEdge;
use Aitumalow\Models\WorkflowFolder;
use Aitumalow\Models\WorkflowGraphEdit;
use Aitumalow\Models\WorkflowNode;
use Aitumalow\Models\WorkflowNodeRun;
use Aitumalow\Models\WorkflowRevision;
use Aitumalow\Models\WorkflowRun;
use Aitumalow\Models\WorkflowTag;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class ConfiguredModels
{
    /** @return class-string<WorkflowGraphEdit> */
    public static function graphEdit(): string
    {
        return self::resolve('aitumalow.models.graph_edit', WorkflowGraphEdit::class);
    }

    /** @return class-string<Workflow> */
    public static function workflow(): string
    {
        return self::resolve('aitumalow.models.workflow', Workflow::class);
    }

    /** @return class-string<WorkflowNode> */
    public static function node(): string
    {
        return self::resolve('aitumalow.models.node', WorkflowNode::class);
    }

    /** @return class-string<WorkflowEdge> */
    public static function edge(): string
    {
        return self::resolve('aitumalow.models.edge', WorkflowEdge::class);
    }

    /** @return class-string<WorkflowRun> */
    public static function run(): string
    {
        return self::resolve('aitumalow.models.run', WorkflowRun::class);
    }

    /** @return class-string<WorkflowCommand> */
    public static function command(): string
    {
        return self::resolve('aitumalow.models.command', WorkflowCommand::class);
    }

    /** @return class-string<WorkflowRevision> */
    public static function revision(): string
    {
        return self::resolve('aitumalow.models.revision', WorkflowRevision::class);
    }

    /** @return class-string<WorkflowNodeRun> */
    public static function nodeRun(): string
    {
        return self::resolve('aitumalow.models.node_run', WorkflowNodeRun::class);
    }

    /** @return class-string<WorkflowTag> */
    public static function tag(): string
    {
        return self::resolve('aitumalow.models.tag', WorkflowTag::class);
    }

    /** @return class-string<WorkflowFolder> */
    public static function folder(): string
    {
        return self::resolve('aitumalow.models.folder', WorkflowFolder::class);
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $default
     * @return class-string<TModel>
     */
    private static function resolve(string $key, string $default): string
    {
        $configured = config($key, $default);

        if (! is_string($configured) || ! is_a($configured, $default, true)) {
            throw new InvalidArgumentException("Configuration [{$key}] must be {$default} or a subclass.");
        }

        return $configured;
    }
}
