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
        Schema::create('bot_analytics', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique(); // Дата для агрегации
            $table->integer('active_chats')->default(0); // Активных чатов
            $table->integer('total_participants')->default(0); // Всего участников
            $table->integer('total_quizzes')->default(0); // Всего викторин
            $table->integer('total_answers')->default(0); // Всего ответов
            $table->integer('correct_answers')->default(0); // Правильных ответов
            $table->integer('errors_count')->default(0); // Количество ошибок
            $table->integer('avg_response_time_ms')->default(0); // Среднее время ответа в мс
            $table->integer('uptime_percentage')->default(100); // Аптайм в процентах
            $table->timestamps();
            
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_analytics');
    }
};
