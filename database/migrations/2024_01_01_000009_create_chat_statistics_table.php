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
        Schema::create('chat_statistics', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('chat_id')->unique(); // Telegram chat ID
            $table->string('chat_type')->default('group'); // group, supergroup
            $table->string('chat_title')->nullable();
            $table->integer('total_quizzes')->default(0); // Всего викторин проведено
            $table->integer('total_participants')->default(0); // Всего уникальных участников
            $table->integer('total_answers')->default(0); // Всего ответов
            $table->integer('correct_answers')->default(0); // Правильных ответов
            $table->timestamp('first_quiz_at')->nullable(); // Первая викторина
            $table->timestamp('last_quiz_at')->nullable(); // Последняя викторина
            $table->boolean('is_active')->default(true); // Активен ли чат
            $table->timestamps();
            
            $table->index(['is_active', 'last_quiz_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_statistics');
    }
};
