<?php

namespace App\Console\Commands;

use App\Models\Question;
use App\Models\ActiveQuiz;
use App\Models\QuizResult;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckDatabaseStructure extends Command
{
    protected $signature = 'db:check-structure';
    protected $description = 'Проверить структуру БД и данные';

    public function handle(): int
    {
        $this->info('=== Проверка структуры БД ===\n');
        
        // 1. Проверить таблицу questions
        $this->info('1. Таблица questions:');
        try {
            $hasCorrectAnswerText = DB::select("SHOW COLUMNS FROM questions LIKE 'correct_answer_text'");
            $hasCorrectAnswer = DB::select("SHOW COLUMNS FROM questions LIKE 'correct_answer'");
            
            if (empty($hasCorrectAnswerText)) {
                $this->error('   ❌ Колонка correct_answer_text отсутствует!');
            } else {
                $this->info('   ✅ Колонка correct_answer_text существует');
            }
            
            if (empty($hasCorrectAnswer)) {
                $this->error('   ❌ Колонка correct_answer отсутствует!');
            } else {
                $this->info('   ✅ Колонка correct_answer существует');
            }
            
            // Проверить данные
            $totalQuestions = Question::count();
            $questionsWithText = Question::whereNotNull('correct_answer_text')->count();
            $questionsWithIndex = Question::where('correct_answer', '>=', 0)->where('correct_answer', '<=', 10)->count();
            
            $this->line("   Всего вопросов: {$totalQuestions}");
            $this->line("   С correct_answer_text: {$questionsWithText}");
            $this->line("   С correct_answer (индекс 0-10): {$questionsWithIndex}");
            
            // Пример вопроса
            $sample = Question::first();
            if ($sample) {
                $this->line("   Пример вопроса #{$sample->id}:");
                $this->line("     correct_answer: {$sample->correct_answer}");
                $this->line("     correct_answer_text: " . ($sample->correct_answer_text ?? 'NULL'));
                $this->line("     getCorrectAnswerText(): " . $sample->getCorrectAnswerText());
            }
        } catch (\Exception $e) {
            $this->error('   ❌ Ошибка: ' . $e->getMessage());
        }
        
        $this->newLine();
        
        // 2. Проверить таблицу active_quizzes
        $this->info('2. Таблица active_quizzes:');
        try {
            $hasCorrectAnswerIndex = DB::select("SHOW COLUMNS FROM active_quizzes LIKE 'correct_answer_index'");
            
            if (empty($hasCorrectAnswerIndex)) {
                $this->warn('   ⚠️ Колонка correct_answer_index отсутствует (не критично, сравнение по тексту)');
            } else {
                $this->info('   ✅ Колонка correct_answer_index существует');
            }
            
            $totalQuizzes = ActiveQuiz::count();
            $activeQuizzes = ActiveQuiz::where('is_active', true)->count();
            
            $this->line("   Всего викторин: {$totalQuizzes}");
            $this->line("   Активных: {$activeQuizzes}");
        } catch (\Exception $e) {
            $this->error('   ❌ Ошибка: ' . $e->getMessage());
        }
        
        $this->newLine();
        
        // 3. Проверить таблицу quiz_results
        $this->info('3. Таблица quiz_results:');
        try {
            $totalResults = QuizResult::count();
            $correctResults = QuizResult::where('is_correct', true)->count();
            
            $this->line("   Всего ответов: {$totalResults}");
            $this->line("   Правильных: {$correctResults}");
            
            // Пример результата
            $sample = QuizResult::with('activeQuiz')->first();
            if ($sample) {
                $this->line("   Пример ответа #{$sample->id}:");
                $this->line("     answer (в БД): " . substr($sample->answer, 0, 50));
                $this->line("     is_correct: " . ($sample->is_correct ? 'true' : 'false'));
                $this->line("     getAnswerText(): " . $sample->getAnswerText());
                
                // Проверить, это индекс или текст
                if (is_numeric($sample->answer) && (int)$sample->answer >= 0 && (int)$sample->answer <= 10) {
                    $this->warn("     ⚠️ answer выглядит как индекс (число), но должен быть текст!");
                } else {
                    $this->info("     ✅ answer содержит текст (правильно)");
                }
            }
        } catch (\Exception $e) {
            $this->error('   ❌ Ошибка: ' . $e->getMessage());
        }
        
        $this->newLine();
        
        // 4. Проверить логику сравнения
        $this->info('4. Проверка логики сравнения:');
        try {
            $question = Question::first();
            if ($question) {
                $correctText = $question->getCorrectAnswerText();
                $this->line("   Правильный ответ для вопроса #{$question->id}: {$correctText}");
                
                // Симулировать выбор ответа
                $answers = $question->getShuffledAnswers();
                if (!empty($answers)) {
                    $this->line("   Варианты ответов (перемешанные):");
                    foreach ($answers as $index => $answer) {
                        $isCorrect = (mb_strtolower(trim($answer)) === mb_strtolower(trim($correctText)));
                        $mark = $isCorrect ? '✅' : '  ';
                        $this->line("     {$mark} [{$index}] {$answer}");
                    }
                }
            }
        } catch (\Exception $e) {
            $this->error('   ❌ Ошибка: ' . $e->getMessage());
        }
        
        $this->newLine();
        $this->info('✅ Проверка завершена');
        
        return Command::SUCCESS;
    }
}
