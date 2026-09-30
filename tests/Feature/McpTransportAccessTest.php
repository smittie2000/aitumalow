<?php

declare(strict_types=1);

use Aitumalow\Http\EditorApiRoutes;
use Aitumalow\Mcp\WorkflowMcpServer;
use Aitumalow\Models\Workflow;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

beforeEach(function (): void {
    Gate::define('manage-workflows', fn (GenericUser $user): bool => $user->getAuthIdentifier() === 1);
    $middleware = ['api', 'auth', 'can:manage-workflows'];
    Route::prefix('protected-editor')->middleware($middleware)->group(fn () => EditorApiRoutes::register());
    Mcp::web('/protected-mcp', WorkflowMcpServer::class)->middleware($middleware);
});

it('requires the same authenticated editor permission on the MCP transport', function (): void {
    $call = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => [
        'name' => 'create_workflow', 'arguments' => ['name' => 'Unauthorized creation'],
    ]];
    $this->getJson('/protected-editor/workflows')->assertUnauthorized();
    $this->postJson('/protected-mcp', $call)->assertUnauthorized();
    $this->actingAs(new GenericUser(['id' => 2]));
    $this->getJson('/protected-editor/workflows')->assertForbidden();
    $this->postJson('/protected-mcp', $call)->assertForbidden();
    expect(Workflow::query()->count())->toBe(0);
});

it('lets an authorized MCP user create the same draft visible through the editor', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));
    $created = $this->postJson('/protected-mcp', [
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => [
            'name' => 'create_workflow', 'arguments' => ['name' => 'Created as editor user'],
        ],
    ])->assertOk()->assertJsonPath('result.isError', false);
    $id = $created->json('result.structuredContent.workflow.id');
    $this->getJson("/protected-editor/workflows/{$id}")->assertOk()->assertJsonPath('data.name', 'Created as editor user')
        ->assertJsonPath('data.is_active', false);
});
