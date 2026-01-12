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
        Schema::create('quiz_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('active_quiz_id')->constrained('active_quizzes')->onDelete('cascade');
            $table->bigInteger('user_id'); // Telegram user ID
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->text('answer');
            $table->boolean('is_correct')->default(false);
            $table->integer('response_time_ms'); // Время ответа в миллисекундах
            $table->timestamps();
            
            $table->index(['active_quiz_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_results');
    }
};
