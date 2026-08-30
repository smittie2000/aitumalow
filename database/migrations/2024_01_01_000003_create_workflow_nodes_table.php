<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $workflowsTable = config('aitumalow.tables.workflows', 'aitumalow_workflows');

        Schema::create(config('aitumalow.tables.nodes', 'aitumalow_workflow_nodes'), function (Blueprint $table) use ($workflowsTable) {
            $table->id();
            $table->foreignId('workflow_id')->constrained($workflowsTable)->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('node_key', 100);
            $table->string('name')->nullable();
            $table->json('config');
            $table->json('pinned_data')->nullable();
            $table->integer('position_x')->default(0);
            $table->integer('position_y')->default(0);
            $table->timestamps();

            $table->index(['workflow_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('aitumalow.tables.nodes', 'aitumalow_workflow_nodes'));
    }
};
