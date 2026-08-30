<?php

namespace Aitumalow\Mcp;

use Aitumalow\Mcp\Tools\ActivateWorkflowTool;
use Aitumalow\Mcp\Tools\AddWorkflowNodeTool;
use Aitumalow\Mcp\Tools\ConnectWorkflowNodesTool;
use Aitumalow\Mcp\Tools\CreateWorkflowTool;
use Aitumalow\Mcp\Tools\DeactivateWorkflowTool;
use Aitumalow\Mcp\Tools\DisconnectWorkflowNodesTool;
use Aitumalow\Mcp\Tools\GetWorkflowDraftTool;
use Aitumalow\Mcp\Tools\ListWorkflowNodesTool;
use Aitumalow\Mcp\Tools\ListWorkflowReferencesTool;
use Aitumalow\Mcp\Tools\ListWorkflowsTool;
use Aitumalow\Mcp\Tools\RemoveWorkflowNodeTool;
use Aitumalow\Mcp\Tools\RunWorkflowTool;
use Aitumalow\Mcp\Tools\SaveWorkflowDraftTool;
use Aitumalow\Mcp\Tools\ShowWorkflowNodeTool;
use Aitumalow\Mcp\Tools\ShowWorkflowRunTool;
use Aitumalow\Mcp\Tools\ShowWorkflowTool;
use Aitumalow\Mcp\Tools\UpdateWorkflowNodeTool;
use Aitumalow\Mcp\Tools\UpdateWorkflowTool;
use Aitumalow\Mcp\Tools\ValidateWorkflowTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Aitumalow')]
#[Version('2.1.0')]
#[Instructions('Compose host-approved workflows only from registered workflow nodes. For edits, call get_workflow_draft, inspect any changed definitions with show_workflow_node, preserve unrelated nodes and edges, then pass the complete graph and unchanged draft_hash to save_workflow_draft. For new workflows, create the empty draft before fetching and saving its graph. Use list_workflow_references for reference fields. Validate after granular edits. Never invent keys, fields, models, classes, credentials, providers, or voice-agent settings. Saving changes only the mutable draft; never activate, deactivate, or run a workflow unless the user explicitly asks for that separate operation.')]
final class WorkflowMcpServer extends Server
{
    protected array $tools = [
        ListWorkflowNodesTool::class,
        ShowWorkflowNodeTool::class,
        ListWorkflowReferencesTool::class,
        ListWorkflowsTool::class,
        ShowWorkflowTool::class,
        GetWorkflowDraftTool::class,
        CreateWorkflowTool::class,
        SaveWorkflowDraftTool::class,
        UpdateWorkflowTool::class,
        AddWorkflowNodeTool::class,
        UpdateWorkflowNodeTool::class,
        RemoveWorkflowNodeTool::class,
        ConnectWorkflowNodesTool::class,
        DisconnectWorkflowNodesTool::class,
        ValidateWorkflowTool::class,
        ActivateWorkflowTool::class,
        DeactivateWorkflowTool::class,
        RunWorkflowTool::class,
        ShowWorkflowRunTool::class,
    ];
}
