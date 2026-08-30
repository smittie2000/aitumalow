<?php

use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowFolder;
use Aitumalow\Models\WorkflowTag;
use Aitumalow\Services\WorkflowService;

// ── Tags CRUD ───────────────────────────────────────────────────

it('lists tags', function () {
    WorkflowTag::factory()->count(3)->create();

    $this->getJson('/workflow-engine/tags')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('creates a tag', function () {
    $this->postJson('/workflow-engine/tags', [
        'name' => 'production',
        'color' => '#ff0000',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'production')
        ->assertJsonPath('data.color', '#ff0000');

    $this->assertDatabaseHas(config('aitumalow.tables.tags'), ['name' => 'production']);
});

it('rejects duplicate tag name', function () {
    WorkflowTag::factory()->create(['name' => 'existing']);

    $this->postJson('/workflow-engine/tags', ['name' => 'existing'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('updates a tag', function () {
    $tag = WorkflowTag::factory()->create(['name' => 'old']);

    $this->putJson("/workflow-engine/tags/{$tag->id}", ['name' => 'new'])
        ->assertOk()
        ->assertJsonPath('data.name', 'new');
});

it('deletes a tag', function () {
    $tag = WorkflowTag::factory()->create();

    $this->deleteJson("/workflow-engine/tags/{$tag->id}")
        ->assertOk();

    $this->assertDatabaseMissing(config('aitumalow.tables.tags'), ['id' => $tag->id]);
});

// ── Folders CRUD ────────────────────────────────────────────────

it('lists folders', function () {
    WorkflowFolder::factory()->count(3)->create();

    $this->getJson('/workflow-engine/folders')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('lists folders as tree', function () {
    $parent = WorkflowFolder::factory()->create(['name' => 'Parent']);
    $child = WorkflowFolder::factory()->create(['name' => 'Child', 'parent_id' => $parent->id]);
    WorkflowFolder::factory()->create(['name' => 'Grandchild', 'parent_id' => $child->id]);
    WorkflowFolder::factory()->create(['name' => 'Root 2']);

    $this->getJson('/workflow-engine/folders?tree=1')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Parent')
        ->assertJsonPath('data.0.children.0.name', 'Child')
        ->assertJsonPath('data.0.children.0.children.0.name', 'Grandchild');
});

it('creates a folder', function () {
    $this->postJson('/workflow-engine/folders', ['name' => 'Marketing'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Marketing');

    $this->assertDatabaseHas(config('aitumalow.tables.folders'), ['name' => 'Marketing']);
});

it('creates a child folder', function () {
    $parent = WorkflowFolder::factory()->create();

    $this->postJson('/workflow-engine/folders', [
        'name' => 'Child',
        'parent_id' => $parent->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.parent_id', $parent->id);
});

it('updates a folder', function () {
    $folder = WorkflowFolder::factory()->create(['name' => 'Old']);

    $this->putJson("/workflow-engine/folders/{$folder->id}", ['name' => 'New'])
        ->assertOk()
        ->assertJsonPath('data.name', 'New');
});

it('moves a folder under another folder', function () {
    $parent = WorkflowFolder::factory()->create();
    $folder = WorkflowFolder::factory()->create();

    $this->putJson("/workflow-engine/folders/{$folder->id}", ['parent_id' => $parent->id])
        ->assertOk()
        ->assertJsonPath('data.parent_id', $parent->id);
});

it('rejects moving a folder inside itself', function () {
    $folder = WorkflowFolder::factory()->create();

    $this->putJson("/workflow-engine/folders/{$folder->id}", ['parent_id' => $folder->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);
});

it('rejects moving a folder inside one of its descendants', function () {
    $parent = WorkflowFolder::factory()->create();
    $child = WorkflowFolder::factory()->create(['parent_id' => $parent->id]);
    $grandchild = WorkflowFolder::factory()->create(['parent_id' => $child->id]);

    $this->putJson("/workflow-engine/folders/{$parent->id}", ['parent_id' => $grandchild->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);

    expect($parent->refresh()->parent_id)->toBeNull();
});

it('deletes a folder', function () {
    $folder = WorkflowFolder::factory()->create();

    $this->deleteJson("/workflow-engine/folders/{$folder->id}")
        ->assertOk();

    $this->assertDatabaseMissing(config('aitumalow.tables.folders'), ['id' => $folder->id]);
});

it('blocks deleting a folder that contains workflows', function () {
    $folder = WorkflowFolder::factory()->create();
    $workflow = Workflow::factory()->create(['folder_id' => $folder->id]);

    $this->deleteJson("/workflow-engine/folders/{$folder->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['folder']);

    expect($folder->refresh()->exists)->toBeTrue()
        ->and($workflow->refresh()->folder_id)->toBe($folder->id);
});

it('blocks deleting a folder that contains subfolders', function () {
    $folder = WorkflowFolder::factory()->create();
    $child = WorkflowFolder::factory()->create(['parent_id' => $folder->id]);

    $this->deleteJson("/workflow-engine/folders/{$folder->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['folder']);

    expect($folder->refresh()->exists)->toBeTrue()
        ->and($child->refresh()->parent_id)->toBe($folder->id);
});

// ── Workflow + Tags ─────────────────────────────────────────────

it('creates a workflow with tags', function () {
    $tags = WorkflowTag::factory()->count(2)->create();

    $this->postJson('/workflow-engine/workflows', [
        'name' => 'Tagged Workflow',
        'tag_ids' => $tags->pluck('id')->all(),
    ])
        ->assertCreated()
        ->assertJsonCount(2, 'data.tags');
});

it('updates workflow tags via sync', function () {
    $workflow = Workflow::factory()->create();
    $tag1 = WorkflowTag::factory()->create();
    $tag2 = WorkflowTag::factory()->create();
    $workflow->tags()->attach($tag1);

    $this->putJson("/workflow-engine/workflows/{$workflow->id}", [
        'tag_ids' => [$tag2->id],
    ])
        ->assertOk()
        ->assertJsonCount(1, 'data.tags')
        ->assertJsonPath('data.tags.0.id', $tag2->id);
});

// ── Workflow + Folders ──────────────────────────────────────────

it('creates a workflow in a folder', function () {
    $folder = WorkflowFolder::factory()->create();

    $this->postJson('/workflow-engine/workflows', [
        'name' => 'Foldered Workflow',
        'folder_id' => $folder->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.folder_id', $folder->id)
        ->assertJsonPath('data.folder.id', $folder->id);
});

it('moves a workflow to a different folder', function () {
    $folder1 = WorkflowFolder::factory()->create();
    $folder2 = WorkflowFolder::factory()->create();
    $workflow = Workflow::factory()->create(['folder_id' => $folder1->id]);

    $this->putJson("/workflow-engine/workflows/{$workflow->id}", [
        'folder_id' => $folder2->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.folder_id', $folder2->id);
});

// ── Filtering ───────────────────────────────────────────────────

it('filters workflows by folder_id', function () {
    $folder = WorkflowFolder::factory()->create();
    Workflow::factory()->create(['folder_id' => $folder->id]);
    Workflow::factory()->create(); // no folder

    $this->getJson("/workflow-engine/workflows?folder_id={$folder->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters uncategorized workflows', function () {
    $folder = WorkflowFolder::factory()->create();
    Workflow::factory()->create(['folder_id' => $folder->id]);
    Workflow::factory()->count(2)->create(); // no folder

    $this->getJson('/workflow-engine/workflows?uncategorized=1')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('filters workflows by tag name', function () {
    $tag = WorkflowTag::factory()->create(['name' => 'production']);
    $tagged = Workflow::factory()->create();
    $tagged->tags()->attach($tag);
    Workflow::factory()->create(); // untagged

    $this->getJson('/workflow-engine/workflows?tag=production')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters workflows by tag_id', function () {
    $tag = WorkflowTag::factory()->create();
    $tagged = Workflow::factory()->create();
    $tagged->tags()->attach($tag);
    Workflow::factory()->create(); // untagged

    $this->getJson("/workflow-engine/workflows?tag_id={$tag->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('shows tags and folder in workflow listing', function () {
    $folder = WorkflowFolder::factory()->create();
    $tag = WorkflowTag::factory()->create();
    $workflow = Workflow::factory()->create(['folder_id' => $folder->id]);
    $workflow->tags()->attach($tag);

    $this->getJson('/workflow-engine/workflows')
        ->assertOk()
        ->assertJsonCount(1, 'data.0.tags')
        ->assertJsonPath('data.0.folder.id', $folder->id);
});

it('searches tags by name', function () {
    WorkflowTag::factory()->create(['name' => 'production']);
    WorkflowTag::factory()->create(['name' => 'staging']);

    $this->getJson('/workflow-engine/tags?search=prod')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

// ── Service: tag_ids in create/update ──────────────────────────

it('creates workflow with tag_ids via service', function () {
    $service = app(WorkflowService::class);
    $tags = WorkflowTag::factory()->count(2)->create();

    $workflow = $service->create([
        'name' => 'Service Test',
        'tag_ids' => $tags->pluck('id')->all(),
    ]);

    expect($workflow->tags)->toHaveCount(2);
});

it('updates workflow tag_ids via service', function () {
    $service = app(WorkflowService::class);
    $workflow = Workflow::factory()->create();
    $tag1 = WorkflowTag::factory()->create();
    $tag2 = WorkflowTag::factory()->create();
    $workflow->tags()->attach($tag1);

    $workflow = $service->update($workflow, ['tag_ids' => [$tag2->id]]);

    expect($workflow->tags)->toHaveCount(1)
        ->and($workflow->tags->first()->id)->toBe($tag2->id);
});

it('duplicates workflow with tags', function () {
    $service = app(WorkflowService::class);
    $workflow = Workflow::factory()->create();
    $tags = WorkflowTag::factory()->count(2)->create();
    $workflow->tags()->sync($tags->pluck('id'));

    $copy = $service->duplicate($workflow);

    expect($copy->tags)->toHaveCount(2)
        ->and($copy->tags->pluck('id')->sort()->values()->all())->toBe($tags->pluck('id')->sort()->values()->all());
});

// ── Fluent API: attachTags, detachTags, moveToFolder ───────────

it('attaches tags via fluent API', function () {
    $workflow = Workflow::factory()->create();
    $tags = WorkflowTag::factory()->count(3)->create();

    $tagIds = $tags->map(fn (WorkflowTag $tag): int => $tag->id)->all();
    $result = $workflow->attachTags($tagIds);

    expect($result)->toBe($workflow)
        ->and($workflow->tags)->toHaveCount(3);
});

it('detaches specific tags via fluent API', function () {
    $workflow = Workflow::factory()->create();
    $tags = WorkflowTag::factory()->count(3)->create();
    $workflow->tags()->sync($tags->pluck('id'));

    $workflow->detachTags([$tags[0]->id]);

    expect($workflow->tags)->toHaveCount(2);
});

it('detaches all tags via fluent API', function () {
    $workflow = Workflow::factory()->create();
    $tags = WorkflowTag::factory()->count(3)->create();
    $workflow->tags()->sync($tags->pluck('id'));

    $workflow->detachTags();

    expect($workflow->tags)->toBeEmpty();
});

it('moves workflow to folder via fluent API', function () {
    $workflow = Workflow::factory()->create();
    $folder = WorkflowFolder::factory()->create();

    $result = $workflow->moveToFolder($folder);

    expect($result)->toBe($workflow)
        ->and($workflow->folder_id)->toBe($folder->id)
        ->and($workflow->folder->id)->toBe($folder->id);
});

it('removes workflow from folder via fluent API', function () {
    $folder = WorkflowFolder::factory()->create();
    $workflow = Workflow::factory()->create(['folder_id' => $folder->id]);

    $workflow->moveToFolder(null);

    expect($workflow->folder_id)->toBeNull();
});

it('chains fluent tag and folder methods', function () {
    $workflow = Workflow::factory()->create();
    $tag = WorkflowTag::factory()->create();
    $folder = WorkflowFolder::factory()->create();

    $workflow->attachTags([$tag->id])->moveToFolder($folder);

    expect($workflow->tags)->toHaveCount(1)
        ->and($workflow->folder_id)->toBe($folder->id);
});
