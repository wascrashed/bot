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
        Schema::create('user_scores', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id'); // Telegram user ID
            $table->bigInteger('chat_id'); // Telegram chat ID
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->integer('total_points')->default(0); // Общее количество очков в этом чате
            $table->integer('correct_answers')->default(0); // Количество правильных ответов
            $table->integer('total_answers')->default(0); // Общее количество ответов
            $table->integer('first_place_count')->default(0); // Количество первых мест
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'chat_id']);
            $table->index(['chat_id', 'total_points']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_scores');
    }
};
