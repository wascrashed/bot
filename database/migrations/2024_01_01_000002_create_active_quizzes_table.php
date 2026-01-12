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
        Schema::create('active_quizzes', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('chat_id'); // Telegram chat ID
            $table->string('chat_type')->default('group'); // group, supergroup
            $table->foreignId('question_id')->constrained('questions')->onDelete('cascade');
            $table->integer('message_id')->nullable(); // ID сообщения с вопросом
            $table->json('answers_order')->nullable(); // Порядок ответов, показанных пользователю
            $table->timestamp('started_at');
            $table->timestamp('expires_at')->nullable();            // Когда заканчивается время на ответ (started_at + 10 секунд)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['chat_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('active_quizzes');
    }
};
