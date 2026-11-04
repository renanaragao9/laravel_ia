<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->text('content');
            $table->json('vector')->nullable();
            $table->decimal('norm', 14, 8)->default(0);
            $table->unsignedInteger('position');
            $table->foreignId('knowledge_document_id')->constrained('knowledge_documents')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['knowledge_document_id', 'position']);
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_chunks');
    }
};
