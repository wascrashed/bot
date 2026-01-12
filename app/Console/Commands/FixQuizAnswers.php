<?php

namespace App\Console\Commands;

use App\Models\ActiveQuiz;
use App\Models\Question;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixQuizAnswers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quiz:fix-answers {--dry-run : Показать что будет исправлено без изменений}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Исправить correct_answer_index для существующих викторин и обновить ответы на индексы';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('🔍 Режим проверки (dry-run) - изменения не будут применены');
        } else {
            $this->info('🔧 Режим исправления - изменения будут применены');
        }
        
        $this->newLine();
        
        // 1. Исправить correct_answer_index для активных викторин без индекса
        $this->info('1. Проверка correct_answer_index в active_quizzes...');
        $quizzesWithoutIndex = ActiveQuiz::whereNull('correct_answer_index')
            ->where('is_active', true)
            ->with('question')
            ->get();
        
        $fixedCount = 0;
        foreach ($quizzesWithoutIndex as $quiz) {
            if (!$quiz->question) {
                continue;
            }
            
            $answersOrder = $quiz->answers_order ?? [];
            if (empty($answersOrder)) {
                continue;
            }
            
            $correctAnswerIndex = null;
            $question = $quiz->question;
            
            if (in_array($question->question_type, [Question::TYPE_MULTIPLE_CHOICE, Question::TYPE_TRUE_FALSE])) {
                if ($question->question_type === Question::TYPE_TRUE_FALSE) {
                    $correctAnswerLower = mb_strtolower(trim($question->correct_answer));
                    if (in_array($correctAnswerLower, ['верно', 'да', 'true', '1', '✓', '✅'])) {
                        $correctAnswerIndex = 0;
                    } else {
                        $correctAnswerIndex = 1;
                    }
                } else {
                    $correctAnswerLower = mb_strtolower(trim($question->correct_answer));
                    foreach ($answersOrder as $index => $answer) {
                        if (mb_strtolower(trim($answer)) === $correctAnswerLower) {
                            $correctAnswerIndex = $index;
                            break;
                        }
                    }
                }
                
                if ($correctAnswerIndex !== null) {
                    if (!$dryRun) {
                        $quiz->update(['correct_answer_index' => $correctAnswerIndex]);
                    }
                    $fixedCount++;
                    $this->line("   ✅ Викторина #{$quiz->id}: установлен индекс {$correctAnswerIndex}");
                } else {
                    $this->warn("   ⚠️ Викторина #{$quiz->id}: не удалось найти правильный ответ");
                }
            }
        }
        
        $this->info("   Исправлено викторин: {$fixedCount}");
        $this->newLine();
        
        // 2. Проверить структуру таблицы
        $this->info('2. Проверка структуры таблиц...');
        try {
            $hasColumn = DB::select("SHOW COLUMNS FROM active_quizzes LIKE 'correct_answer_index'");
            if (empty($hasColumn)) {
                $this->error('   ❌ Колонка correct_answer_index отсутствует в active_quizzes!');
                $this->info('   💡 Выполните: php artisan migrate');
                return Command::FAILURE;
            } else {
                $this->info('   ✅ Колонка correct_answer_index существует');
            }
        } catch (\Exception $e) {
            $this->error("   ❌ Ошибка проверки структуры: " . $e->getMessage());
            return Command::FAILURE;
        }
        
        $this->newLine();
        
        // 3. Статистика
        $this->info('3. Статистика:');
        $totalQuizzes = ActiveQuiz::count();
        $quizzesWithIndex = ActiveQuiz::whereNotNull('correct_answer_index')->count();
        $quizzesWithoutIndex = ActiveQuiz::whereNull('correct_answer_index')->count();
        
        $this->line("   Всего викторин: {$totalQuizzes}");
        $this->line("   С correct_answer_index: {$quizzesWithIndex}");
        $this->line("   Без correct_answer_index: {$quizzesWithoutIndex}");
        
        if ($dryRun) {
            $this->newLine();
            $this->info('💡 Для применения изменений запустите без --dry-run:');
            $this->line('   php artisan quiz:fix-answers');
        }
        
        return Command::SUCCESS;
    }
}
