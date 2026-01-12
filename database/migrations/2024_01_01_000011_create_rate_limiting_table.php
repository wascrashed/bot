<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rate_limiting', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint'); // Telegram API endpoint
            $table->integer('requests_count')->default(0); // Количество запросов
            $table->timestamp('window_start'); // Начало временного окна
            $table->timestamps();
            
            $table->unique('endpoint');
            $table->index('window_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rate_limiting');
    }
};
