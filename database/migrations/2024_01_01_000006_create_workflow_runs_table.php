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

        Schema::create(config('aitumalow.tables.runs', 'aitumalow_workflow_runs'), function (Blueprint $table) use ($workflowsTable, $revisionsTable) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workflow_id')->constrained($workflowsTable);
            $table->foreignId('workflow_revision_id')->constrained($revisionsTable);
            $table->string('durable_workflow_id')->nullable()->unique();
            $table->string('durable_run_id')->nullable()->unique();
            $table->string('status', 20)->default('pending');
            // Snapshot-local editor node identity. Do not constrain this to the
            // mutable draft node table: published revisions outlive draft edits.
            $table->unsignedBigInteger('trigger_node_id')->nullable();
            $table->unsignedBigInteger('waiting_node_id')->nullable();
            $table->string('waiting_state', 100)->nullable();
            $table->json('execution_scope');
            $table->string('subject_type', 100)->nullable();
            $table->string('subject_reference', 191)->nullable();
            $table->json('subject_context')->nullable();
            $table->string('subject_freshness', 191)->nullable();
            $table->string('executor_reference', 191)->nullable();
            $table->string('idempotency_scope', 191)->nullable();
            $table->string('idempotency_key', 191)->nullable();
            $table->json('initial_payload')->nullable();
            $table->json('context')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['workflow_id', 'status']);
            $table->index(['workflow_revision_id', 'status']);
            $table->unique(['idempotency_scope', 'idempotency_key']);
            $table->index(['subject_type', 'subject_reference']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('aitumalow.tables.runs', 'aitumalow_workflow_runs'));
    }
};
