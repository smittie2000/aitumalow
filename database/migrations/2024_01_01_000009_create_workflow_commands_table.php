<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $runsTable = config('aitumalow.tables.runs', 'aitumalow_workflow_runs');

        Schema::create(config('aitumalow.tables.commands', 'aitumalow_workflow_commands'), function (Blueprint $table) use ($runsTable) {
            $table->id();
            $table->foreignId('workflow_run_id')->constrained($runsTable)->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('idempotency_key', 191);
            $table->unsignedBigInteger('expected_node_id')->nullable();
            $table->json('payload');
            $table->json('execution_scope');
            $table->string('durable_update_id')->nullable()->unique();
            $table->string('status', 20)->default('pending');
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->unique(['workflow_run_id', 'idempotency_key']);
            $table->index(['workflow_run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('aitumalow.tables.commands', 'aitumalow_workflow_commands'));
    }
};
