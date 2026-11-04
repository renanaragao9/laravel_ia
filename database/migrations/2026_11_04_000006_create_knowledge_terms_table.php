<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_terms', function (Blueprint $table) {
            $table->id();
            $table->string('term')->unique();
            $table->decimal('idf', 14, 8)->default(0);
            $table->unsignedInteger('document_frequency')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_terms');
    }
};
