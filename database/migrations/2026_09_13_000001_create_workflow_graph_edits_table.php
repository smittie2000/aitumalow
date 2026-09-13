<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('aitumalow.tables.graph_edits', 'aitumalow_workflow_graph_edits'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_id')->constrained(config('aitumalow.tables.workflows', 'aitumalow_workflows'))->cascadeOnDelete();
            $table->uuid('request_id');
            $table->char('request_hash', 64);
            $table->string('operation', 40);
            $table->json('before');
            $table->json('after');
            $table->char('before_hash', 64);
            $table->char('after_hash', 64);
            $table->unsignedBigInteger('created_node_id')->nullable();
            $table->timestamps();
            $table->unique(['workflow_id', 'request_id'], 'aitumalow_graph_edit_request_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('aitumalow.tables.graph_edits', 'aitumalow_workflow_graph_edits'));
    }
};
