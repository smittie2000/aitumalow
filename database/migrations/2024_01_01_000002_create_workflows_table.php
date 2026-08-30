<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $foldersTable = config('aitumalow.tables.folders', 'aitumalow_workflow_folders');

        Schema::create(config('aitumalow.tables.workflows', 'aitumalow_workflows'), function (Blueprint $table) use ($foldersTable) {
            $table->id();
            $table->string('key', 191)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false);
            $table->json('settings')->nullable();
            $table->string('created_via')->nullable();
            $table->foreignId('folder_id')->nullable()->constrained($foldersTable)->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('aitumalow.tables.workflows', 'aitumalow_workflows'));
    }
};
