<?php

namespace App\Console\Commands;

use App\Models\Question;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ConvertQuestionsToIndex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'questions:convert-to-index {--dry-run : Показать что будет изменено без изменений}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Конвертировать correct_answer из текста в индекс для всех вопросов';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('🔍 Режим проверки (dry-run) - изменения не будут применены');
        } else {
            $this->info('🔧 Режим конвертации - изменения будут применены');
        }
        
        $this->newLine();
        
        // Проверить наличие колонки correct_answer_text
        try {
            $hasColumn = DB::select("SHOW COLUMNS FROM questions LIKE 'correct_answer_text'");
            if (empty($hasColumn)) {
                $this->error('   ❌ Колонка correct_answer_text отсутствует в questions!');
                $this->info('   💡 Выполните миграцию: php artisan migrate');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error("   ❌ Ошибка проверки структуры: " . $e->getMessage());
            return Command::FAILURE;
        }
        
        // Найти все вопросы, где correct_answer_text пустой или null
        $questions = Question::whereNull('correct_answer_text')
            ->orWhere('correct_answer_text', '')
            ->get();
        
        $this->info("Найдено вопросов для конвертации: " . $questions->count());
        $this->newLine();
        
        $converted = 0;
        $skipped = 0;
        
        foreach ($questions as $question) {
            // Если correct_answer уже число - пропустить
            if (is_numeric($question->correct_answer)) {
                $skipped++;
                continue;
            }
            
            $correctAnswerText = $question->correct_answer;
            
            // Определить индекс
            $correctAnswerIndex = 0;
            if ($question->question_type === Question::TYPE_TRUE_FALSE) {
                $correctAnswerLower = mb_strtolower(trim($correctAnswerText));
                $correctAnswerIndex = (in_array($correctAnswerLower, ['верно', 'да', 'true', '1', '✓', '✅'])) ? 0 : 1;
            }
            
            if (!$dryRun) {
                $question->update([
                    'correct_answer' => (string)$correctAnswerIndex,
                    'correct_answer_text' => $correctAnswerText,
                ]);
            }
            
            $converted++;
            $this->line("   ✅ Вопрос #{$question->id}: '{$correctAnswerText}' -> индекс {$correctAnswerIndex}");
        }
        
        $this->newLine();
        $this->info("Конвертировано: {$converted}");
        $this->info("Пропущено (уже индекс): {$skipped}");
        
        if ($dryRun) {
            $this->newLine();
            $this->info('💡 Для применения изменений запустите без --dry-run:');
            $this->line('   php artisan questions:convert-to-index');
        }
        
        return Command::SUCCESS;
    }
}
