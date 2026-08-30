<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('aitumalow.tables.tags', 'aitumalow_workflow_tags'), function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color', 7)->nullable();
            $table->timestamps();

            $table->unique('name');
        });

        Schema::create(config('aitumalow.tables.tag_pivot', 'aitumalow_workflow_tag_pivot'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained(
                config('aitumalow.tables.workflows', 'aitumalow_workflows')
            )->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained(
                config('aitumalow.tables.tags', 'aitumalow_workflow_tags')
            )->cascadeOnDelete();

            $table->unique(['workflow_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('aitumalow.tables.tag_pivot', 'aitumalow_workflow_tag_pivot'));
        Schema::dropIfExists(config('aitumalow.tables.tags', 'aitumalow_workflow_tags'));
    }
};
