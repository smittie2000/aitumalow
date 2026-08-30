<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $workflowsTable = config('aitumalow.tables.workflows', 'aitumalow_workflows');
        $nodesTable = config('aitumalow.tables.nodes', 'aitumalow_workflow_nodes');

        Schema::create(config('aitumalow.tables.edges', 'aitumalow_workflow_edges'), function (Blueprint $table) use ($workflowsTable, $nodesTable) {
            $table->id();
            $table->foreignId('workflow_id')->constrained($workflowsTable)->cascadeOnDelete();
            $table->foreignId('source_node_id')->constrained($nodesTable)->cascadeOnDelete();
            $table->string('source_port', 50)->default('main');
            $table->foreignId('target_node_id')->constrained($nodesTable)->cascadeOnDelete();
            $table->string('target_port', 50)->default('main');
            $table->timestamps();

            $table->index('workflow_id');
            $table->index(['source_node_id', 'source_port']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('aitumalow.tables.edges', 'aitumalow_workflow_edges'));
    }
};
