<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('kind');
            $table->string('source')->nullable();
            $table->text('content');
            $table->dateTime('trained_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('kind');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_documents');
    }
};
