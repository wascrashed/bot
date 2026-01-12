<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // Добавить поле для текста правильного ответа
            $table->string('correct_answer_text')->nullable()->after('correct_answer');
        });
        
        // Конвертировать существующие данные:
        // correct_answer (текст) -> correct_answer_text
        // correct_answer -> индекс (всегда 0, так как правильный ответ первый в массиве)
        DB::statement("
            UPDATE questions 
            SET correct_answer_text = correct_answer,
                correct_answer = '0'
            WHERE correct_answer_text IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Восстановить текст из correct_answer_text обратно в correct_answer
        DB::statement("
            UPDATE questions 
            SET correct_answer = COALESCE(correct_answer_text, '0')
            WHERE correct_answer_text IS NOT NULL
        ");
        
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('correct_answer_text');
        });
    }
};
