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
        Schema::create('question_history', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('chat_id'); // Telegram chat ID
            $table->foreignId('question_id')->constrained('questions')->onDelete('cascade');
            $table->timestamp('asked_at');
            $table->timestamps();
            
            $table->index(['chat_id', 'asked_at']);
            $table->index('question_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_history');
    }
};
