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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->text('question');
            $table->string('question_type')->default('multiple_choice'); // multiple_choice, text, true_false, image
            $table->string('correct_answer');
            $table->json('wrong_answers')->nullable(); // Массив неправильных ответов (для multiple_choice)
            $table->string('category')->default('heroes'); // heroes, abilities, items, lore, esports, memes
            $table->string('difficulty')->default('medium'); // easy (+1), medium (+3), hard (+5)
            $table->string('image_url')->nullable(); // URL изображения для типа image
            $table->string('image_file_id')->nullable(); // Telegram file_id для типа image
            $table->timestamps();
            
            $table->index(['category', 'question_type']);
            $table->index('difficulty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
