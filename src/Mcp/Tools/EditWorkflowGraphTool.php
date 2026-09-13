<?php

declare(strict_types=1);

namespace Aitumalow\Mcp\Tools;

use Aitumalow\Exceptions\WorkflowDraftConflictException;
use Aitumalow\Http\Resources\WorkflowResource;
use Aitumalow\Services\WorkflowGraphService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('edit_workflow_graph')]
#[Description('Apply one atomic draft edit. Preserve the exact UUID and body when retrying. add_node: node_key, name, config, position_x/y; optional source:{node_id,port} and input_port, OR edge_id,input_port,output_port to insert. update_node: node_id,name,config. connect: source_node_id,target_node_id,source_port,target_port. remove: node_ids,edge_ids arrays. move_nodes: positions:[{node_id,position_x,position_y}]. pin: node_id,source:run,node_run_id or source:manual,input,output. unpin: node_id. undo/redo: edit_id from an original receipt; only permitted at the matching graph state. Never publishes or activates. Incomplete drafts are allowed; validate before publishing.')]
final class EditWorkflowGraphTool extends Tool
{
    public function __construct(private readonly WorkflowGraphService $graphs) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'workflow_id' => $schema->integer()->required(),
            'request_id' => $schema->string()->required()->description('New UUID for a new edit. Reuse only for an exact retry.'),
            'expected_hash' => $schema->string()->required()->description('hash returned by get_workflow_graph or the previous edit.'),
            'operation' => $schema->string()->enum(['add_node', 'update_node', 'connect', 'remove', 'move_nodes', 'pin', 'unpin', 'undo', 'redo'])->required(),
            'data' => $schema->object()->required()->description('Operation fields described by this tool.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        try {
            $graph = $this->graphs->edit($request->integer('workflow_id'), $request->only(['request_id', 'expected_hash', 'operation', 'data']));

            return Response::structured([...$graph, 'workflow' => new WorkflowResource($graph['workflow'])->resolve()]);
        } catch (WorkflowDraftConflictException $exception) {
            return Response::error($exception->getMessage());
        } catch (ValidationException $exception) {
            return Response::error(implode(' ', $exception->validator->errors()->all()));
        } catch (ModelNotFoundException) {
            return Response::error('The workflow or one of its graph items could not be found. Read the graph again.');
        }
    }
}
