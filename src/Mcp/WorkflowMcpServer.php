<?php

namespace Aitumalow\Mcp;

use Aitumalow\Mcp\Tools\ActivateWorkflowTool;
use Aitumalow\Mcp\Tools\AddWorkflowNodeTool;
use Aitumalow\Mcp\Tools\CompareWorkflowRevisionTool;
use Aitumalow\Mcp\Tools\ConnectWorkflowNodesTool;
use Aitumalow\Mcp\Tools\ControlWorkflowRunTool;
use Aitumalow\Mcp\Tools\CreateWorkflowTool;
use Aitumalow\Mcp\Tools\DeactivateWorkflowTool;
use Aitumalow\Mcp\Tools\DeleteWorkflowTool;
use Aitumalow\Mcp\Tools\DisconnectWorkflowNodesTool;
use Aitumalow\Mcp\Tools\DuplicateWorkflowTool;
use Aitumalow\Mcp\Tools\EditWorkflowGraphTool;
use Aitumalow\Mcp\Tools\GetWorkflowDraftTool;
use Aitumalow\Mcp\Tools\GetWorkflowGraphTool;
use Aitumalow\Mcp\Tools\GetWorkflowVariablesTool;
use Aitumalow\Mcp\Tools\ListWorkflowNodesTool;
use Aitumalow\Mcp\Tools\ListWorkflowOrganizationTool;
use Aitumalow\Mcp\Tools\ListWorkflowReferencesTool;
use Aitumalow\Mcp\Tools\ListWorkflowRevisionsTool;
use Aitumalow\Mcp\Tools\ListWorkflowRunsTool;
use Aitumalow\Mcp\Tools\ListWorkflowsTool;
use Aitumalow\Mcp\Tools\ManageWorkflowFolderTool;
use Aitumalow\Mcp\Tools\ManageWorkflowTagTool;
use Aitumalow\Mcp\Tools\RemoveWorkflowNodeTool;
use Aitumalow\Mcp\Tools\RestoreWorkflowDraftTool;
use Aitumalow\Mcp\Tools\RunWorkflowTool;
use Aitumalow\Mcp\Tools\SaveWorkflowDraftTool;
use Aitumalow\Mcp\Tools\ShowWorkflowNodeTool;
use Aitumalow\Mcp\Tools\ShowWorkflowRunTool;
use Aitumalow\Mcp\Tools\ShowWorkflowTool;
use Aitumalow\Mcp\Tools\TestWorkflowNodeTool;
use Aitumalow\Mcp\Tools\UpdateWorkflowNodeTool;
use Aitumalow\Mcp\Tools\UpdateWorkflowTool;
use Aitumalow\Mcp\Tools\ValidateWorkflowTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Aitumalow')]
#[Version('2.3.0')]
#[Instructions('Compose host-approved workflows only from registered workflow nodes. For focused edits that preserve editor identities, positions, and pins, use get_workflow_graph and edit_workflow_graph with its hash and a unique request UUID. Retry the exact request after transport failures. For complete document replacements, call get_workflow_draft, inspect any changed definitions with show_workflow_node, preserve unrelated nodes and edges, then pass the complete graph and unchanged draft_hash to save_workflow_draft. For new workflows, discover schemas with list_workflow_nodes(include_schemas:true), then supply nodes and edges to create_workflow to save a complete validated draft in one call. No application PHP editing is needed. MCP and the editor share the same catalog, validators and services. Use the organization, variable, revision and run tools for the same user operations available in the editor. Use list_workflow_references for reference fields. Validate after granular edits. Never invent keys, fields, models, classes, credentials, providers, or voice-agent settings. Saving changes only the mutable draft; never activate, deactivate, or run a workflow unless the user explicitly asks for that separate operation. Draft tests and replay can execute business effects; obtain the same authorization as an editor user. Never delete or restore a draft without an explicit user request.')]
final class WorkflowMcpServer extends Server
{
    // Return the complete editor surface in a normal tools/list request.
    public int $defaultPaginationLength = 50;

    protected array $tools = [
        ListWorkflowNodesTool::class,
        ShowWorkflowNodeTool::class,
        ListWorkflowReferencesTool::class,
        ListWorkflowsTool::class,
        ShowWorkflowTool::class,
        GetWorkflowDraftTool::class,
        GetWorkflowGraphTool::class,
        EditWorkflowGraphTool::class,
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
        DuplicateWorkflowTool::class,
        DeleteWorkflowTool::class,
        GetWorkflowVariablesTool::class,
        TestWorkflowNodeTool::class,
        ListWorkflowRunsTool::class,
        ControlWorkflowRunTool::class,
        ListWorkflowRevisionsTool::class,
        CompareWorkflowRevisionTool::class,
        RestoreWorkflowDraftTool::class,
        ListWorkflowOrganizationTool::class,
        ManageWorkflowFolderTool::class,
        ManageWorkflowTagTool::class,
    ];
}
