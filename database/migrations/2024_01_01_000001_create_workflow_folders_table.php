<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $foldersTable = config('aitumalow.tables.folders', 'aitumalow_workflow_folders');

        Schema::create($foldersTable, function (Blueprint $table) use ($foldersTable) {
            $table->id();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained($foldersTable)->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('aitumalow.tables.folders', 'aitumalow_workflow_folders'));
    }
};
