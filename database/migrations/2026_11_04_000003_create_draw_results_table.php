<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draw_results', function (Blueprint $table) {
            $table->id();
            $table->string('thousand', 4);
            $table->string('hundred', 3);
            $table->string('ten', 2);
            $table->unsignedTinyInteger('position');
            $table->unsignedTinyInteger('animal_number');
            $table->foreignId('draw_id')->constrained('draws')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['draw_id', 'position']);
            $table->index('ten');
            $table->index('animal_number');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draw_results');
    }
};
