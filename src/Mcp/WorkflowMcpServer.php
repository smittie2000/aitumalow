<?php

namespace Aitumalow\Mcp;

use Aitumalow\Mcp\Tools\ActivateWorkflowTool;
use Aitumalow\Mcp\Tools\AddWorkflowNodeTool;
use Aitumalow\Mcp\Tools\ConnectWorkflowNodesTool;
use Aitumalow\Mcp\Tools\CreateWorkflowTool;
use Aitumalow\Mcp\Tools\DeactivateWorkflowTool;
use Aitumalow\Mcp\Tools\DisconnectWorkflowNodesTool;
use Aitumalow\Mcp\Tools\ListWorkflowNodesTool;
use Aitumalow\Mcp\Tools\ListWorkflowReferencesTool;
use Aitumalow\Mcp\Tools\ListWorkflowsTool;
use Aitumalow\Mcp\Tools\RemoveWorkflowNodeTool;
use Aitumalow\Mcp\Tools\RunWorkflowTool;
use Aitumalow\Mcp\Tools\ShowWorkflowNodeTool;
use Aitumalow\Mcp\Tools\ShowWorkflowTool;
use Aitumalow\Mcp\Tools\UpdateWorkflowNodeTool;
use Aitumalow\Mcp\Tools\UpdateWorkflowTool;
use Aitumalow\Mcp\Tools\ValidateWorkflowTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Aitumalow')]
#[Version('2.0.0')]
#[Instructions('Compose host-approved workflows from registered workflow nodes. Call list_workflow_nodes, inspect selected definitions with show_workflow_node, create a workflow, use list_workflow_references for any reference field, add nodes by exact stable key, connect them, validate, then activate. Workflow node keys and schemas are authoritative; never invent keys, fields, models, classes, credentials, or provider settings.')]
final class WorkflowMcpServer extends Server
{
    protected array $tools = [
        ListWorkflowNodesTool::class,
        ShowWorkflowNodeTool::class,
        ListWorkflowReferencesTool::class,
        ListWorkflowsTool::class,
        ShowWorkflowTool::class,
        CreateWorkflowTool::class,
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
    ];
}
