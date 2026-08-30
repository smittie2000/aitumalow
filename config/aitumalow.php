<?php

use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowCommand;
use Aitumalow\Models\WorkflowEdge;
use Aitumalow\Models\WorkflowFolder;
use Aitumalow\Models\WorkflowNode;
use Aitumalow\Models\WorkflowNodeRun;
use Aitumalow\Models\WorkflowRevision;
use Aitumalow\Models\WorkflowRun;
use Aitumalow\Models\WorkflowTag;

return [

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | Aitumalow owns the visual workflow definition and read-model tables.
    | Durable Workflow owns execution, task, timer, signal, and schedule tables.
    |
    */

    'tables' => [
        'workflows' => 'aitumalow_workflows',
        'nodes' => 'aitumalow_workflow_nodes',
        'edges' => 'aitumalow_workflow_edges',
        'runs' => 'aitumalow_workflow_runs',
        'commands' => 'aitumalow_workflow_commands',
        'revisions' => 'aitumalow_workflow_revisions',
        'node_runs' => 'aitumalow_workflow_node_runs',
        'tags' => 'aitumalow_workflow_tags',
        'tag_pivot' => 'aitumalow_workflow_tag_pivot',
        'folders' => 'aitumalow_workflow_folders',
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Classes
    |--------------------------------------------------------------------------
    |
    | Override these to extend the default models with your own.
    |
    */

    'models' => [
        'workflow' => Workflow::class,
        'node' => WorkflowNode::class,
        'edge' => WorkflowEdge::class,
        'run' => WorkflowRun::class,
        'command' => WorkflowCommand::class,
        'revision' => WorkflowRevision::class,
        'node_run' => WorkflowNodeRun::class,
        'tag' => WorkflowTag::class,
        'folder' => WorkflowFolder::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Durable Runtime
    |--------------------------------------------------------------------------
    |
    | Durable Workflow owns dispatch, retries, timers, signals, concurrency,
    | and recovery. These values only select the Durable worker queue and the
    | default activity retry policy used by Aitumalow capability nodes.
    |
    */

    'queue' => env('AITUMALOW_QUEUE', 'default'),
    'default_retry_count' => 0,
    'default_retry_delay_ms' => 1000,
    'command_effect_timeout_seconds' => 15,

    /*
    |--------------------------------------------------------------------------
    | Expression Engine
    |--------------------------------------------------------------------------
    |
    | 'safe'   — Dot-notation access + whitelisted functions (recommended)
    | 'strict' — Dot-notation access only, no function calls
    |
    */

    'expression_mode' => 'safe',

    /*
    |--------------------------------------------------------------------------
    | Plugins
    |--------------------------------------------------------------------------
    |
    | Register workflow plugins via config. Each entry should be a fully
    | qualified class name implementing PluginInterface. Plugins registered
    | here are loaded during boot, in addition to any plugins registered
    | via WorkflowAutomation::plugin() in service providers.
    |
    */

    'plugins' => [
        // \Acme\WorkflowSlack\SlackPlugin::class,
    ],

];
