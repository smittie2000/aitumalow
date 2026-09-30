<?php

declare(strict_types=1);

use Aitumalow\Mcp\Tools\ControlWorkflowRunTool;
use Aitumalow\Mcp\Tools\CreateWorkflowTool;
use Aitumalow\Mcp\Tools\GetWorkflowDraftTool;
use Aitumalow\Mcp\Tools\ListWorkflowsTool;
use Aitumalow\Mcp\Tools\ManageWorkflowFolderTool;
use Aitumalow\Mcp\Tools\ShowWorkflowRunTool;
use Aitumalow\Mcp\Tools\ShowWorkflowTool;
use Aitumalow\Mcp\Tools\UpdateWorkflowTool;
use Aitumalow\Mcp\WorkflowMcpServer;
use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowFolder;
use Aitumalow\Models\WorkflowRun;
use Aitumalow\Models\WorkflowTag;
use Aitumalow\Services\WorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Testing\Fluent\AssertableJson;

beforeEach(function (): void {
    config(['aitumalow.models.workflow' => ScopedEditorWorkflow::class,
        'aitumalow.models.run' => ScopedEditorRun::class,
        'aitumalow.models.folder' => ScopedEditorFolder::class,
        'aitumalow.models.tag' => ScopedEditorTag::class]);
});

it('applies configured workflow scopes to both editor and MCP listing and lookups', function (): void {
    $visible = Workflow::factory()->create(['name' => 'Visible workflow']);
    $hidden = Workflow::factory()->create(['name' => 'Hidden workflow']);
    $this->getJson('/workflow-engine/workflows')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $visible->id);
    $this->getJson("/workflow-engine/workflows/{$visible->id}")->assertOk();
    $this->getJson("/workflow-engine/workflows/{$hidden->id}")->assertNotFound();
    $this->patchJson("/workflow-engine/workflows/{$hidden->id}", ['description' => 'Changed'])->assertNotFound();
    WorkflowMcpServer::tool(ListWorkflowsTool::class)->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('pagination.total', 1)->where('workflows.0.id', $visible->id)->etc());
    WorkflowMcpServer::tool(ShowWorkflowTool::class, ['workflow_id' => $hidden->id])->assertHasErrors();
    WorkflowMcpServer::tool(GetWorkflowDraftTool::class, ['workflow_id' => $hidden->id])->assertHasErrors();
    WorkflowMcpServer::tool(UpdateWorkflowTool::class, ['workflow_id' => $hidden->id, 'description' => 'Changed'])->assertHasErrors();
    expect($hidden->fresh()->description)->not->toBe('Changed')
        ->and(fn () => app(WorkflowService::class)->delete($hidden->id))->toThrow(ModelNotFoundException::class)
        ->and($hidden->fresh())->not->toBeNull();
});

it('creates editor and MCP drafts through the configured model hooks', function (): void {
    $this->postJson('/workflow-engine/workflows', ['name' => 'Visible editor draft'])->assertCreated()->assertJsonPath('data.description', 'Configured model hook');
    WorkflowMcpServer::tool(CreateWorkflowTool::class, ['name' => 'Visible MCP draft',
        'nodes' => [['id' => 'start', 'capability' => 'core.manual']], 'edges' => [],
    ])->assertOk();
    $workflow = ScopedEditorWorkflow::query()->where('name', 'Visible MCP draft')->sole();
    expect($workflow->description)->toBe('Configured model hook')->and($workflow->is_active)->toBeFalse();
});

it('rejects inaccessible run reads and cancellation through editor and MCP', function (): void {
    $hidden = Workflow::factory()->create(['name' => 'Hidden workflow']);
    $run = WorkflowRun::factory()->create(['workflow_id' => $hidden->id]);
    $this->getJson("/workflow-engine/runs/{$run->id}")->assertNotFound();
    $this->postJson("/workflow-engine/runs/{$run->id}/cancel")->assertNotFound();
    WorkflowMcpServer::tool(ShowWorkflowRunTool::class, ['workflow_run_id' => $run->id, 'include_data' => true])->assertHasErrors();
    WorkflowMcpServer::tool(ControlWorkflowRunTool::class, ['workflow_run_id' => $run->id, 'action' => 'cancel'])->assertHasErrors();
    expect($run->fresh()->status)->toBe($run->status);
});

it('rejects assigning inaccessible organization records before any draft is saved', function (): void {
    $folder = WorkflowFolder::factory()->create(['name' => 'Hidden folder']);
    $tag = WorkflowTag::factory()->create(['name' => 'Hidden tag']);
    foreach (['folder_id' => $folder->id, 'tag_ids' => [$tag->id]] as $field => $value) {
        $this->postJson('/workflow-engine/workflows', ['name' => 'Visible editor draft', $field => $value])->assertNotFound();
        WorkflowMcpServer::tool(CreateWorkflowTool::class, ['name' => 'Visible MCP draft', $field => $value])->assertHasErrors();
    }
    $this->postJson('/workflow-engine/folders', ['name' => 'Visible child', 'parent_id' => $folder->id])->assertNotFound();
    WorkflowMcpServer::tool(ManageWorkflowFolderTool::class, ['operation' => 'create', 'name' => 'Visible child', 'parent_id' => $folder->id])->assertHasErrors();
    expect(Workflow::query()->count())->toBe(0)->and(WorkflowFolder::query()->count())->toBe(1);
});

class ScopedEditorWorkflow extends Workflow
{
    protected static function booted(): void
    {
        parent::booted();
        static::addGlobalScope('visible', fn (Builder $query) => $query->where('name', 'like', 'Visible%'));
        static::creating(fn (Workflow $workflow) => $workflow->description = 'Configured model hook');
    }
}

class ScopedEditorRun extends WorkflowRun
{
    protected static function booted(): void
    {
        static::addGlobalScope('visible', fn (Builder $query) => $query->whereHas('workflow'));
    }
}

class ScopedEditorFolder extends WorkflowFolder
{
    protected static function booted(): void
    {
        static::addGlobalScope('visible', fn (Builder $query) => $query->where('name', 'like', 'Visible%'));
    }
}

class ScopedEditorTag extends WorkflowTag
{
    protected static function booted(): void
    {
        static::addGlobalScope('visible', fn (Builder $query) => $query->where('name', 'like', 'Visible%'));
    }
}
