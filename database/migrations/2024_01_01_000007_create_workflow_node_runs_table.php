<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $runsTable = config('aitumalow.tables.runs', 'aitumalow_workflow_runs');

        Schema::create(config('aitumalow.tables.node_runs', 'aitumalow_workflow_node_runs'), function (Blueprint $table) use ($runsTable) {
            $table->id();
            $table->foreignId('workflow_run_id')->constrained($runsTable)->cascadeOnDelete();
            // Snapshot-local identity; the corresponding draft node may later
            // be edited or removed without invalidating revision history.
            $table->unsignedBigInteger('node_id');
            $table->string('durable_activity_id')->nullable()->unique();
            $table->string('status', 20)->default('pending');
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['workflow_run_id', 'node_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('aitumalow.tables.node_runs', 'aitumalow_workflow_node_runs'));
    }
};
