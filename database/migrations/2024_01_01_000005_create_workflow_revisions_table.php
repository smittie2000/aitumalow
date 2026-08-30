<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $workflowsTable = config('aitumalow.tables.workflows', 'aitumalow_workflows');
        $revisionsTable = config('aitumalow.tables.revisions', 'aitumalow_workflow_revisions');

        Schema::create($revisionsTable, function (Blueprint $table) use ($workflowsTable) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workflow_id')->constrained($workflowsTable)->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('definition');
            $table->char('definition_hash', 64);
            $table->string('published_by_reference')->nullable();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->unique(['workflow_id', 'version']);
            $table->unique(['workflow_id', 'definition_hash']);
        });

        Schema::table($workflowsTable, function (Blueprint $table) use ($revisionsTable) {
            $table->foreignId('active_revision_id')
                ->nullable()
                ->after('is_active')
                ->constrained($revisionsTable)
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        $workflowsTable = config('aitumalow.tables.workflows', 'aitumalow_workflows');

        Schema::table($workflowsTable, function (Blueprint $table) {
            $table->dropConstrainedForeignId('active_revision_id');
        });

        Schema::dropIfExists(config('aitumalow.tables.revisions', 'aitumalow_workflow_revisions'));
    }
};
