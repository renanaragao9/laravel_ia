<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draws', function (Blueprint $table) {
            $table->id();
            $table->string('lottery')->default('rio');
            $table->string('draw_time')->default('');
            $table->string('source')->nullable();
            $table->date('drawn_on');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['drawn_on', 'lottery', 'draw_time']);
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draws');
    }
};
