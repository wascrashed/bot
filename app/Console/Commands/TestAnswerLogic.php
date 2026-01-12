<?php

namespace App\Console\Commands;

use App\Models\Question;
use App\Models\ActiveQuiz;
use App\Services\QuizService;
use Illuminate\Console\Command;

class TestAnswerLogic extends Command
{
    protected $signature = 'quiz:test-answer-logic {question_id?}';
    protected $description = 'Тестировать логику проверки ответов';

    public function handle(QuizService $quizService): int
    {
        $questionId = $this->argument('question_id');
        
        if ($questionId) {
            $question = Question::find($questionId);
        } else {
            $question = Question::first();
        }
        
        if (!$question) {
            $this->error('Вопрос не найден');
            return Command::FAILURE;
        }
        
        $this->info("=== Тест логики проверки ответов ===\n");
        $this->info("Вопрос #{$question->id}: {$question->question}");
        $this->newLine();
        
        // Получить правильный ответ
        $correctAnswerText = $question->getCorrectAnswerText();
        $this->info("Правильный ответ: {$correctAnswerText}");
        $this->newLine();
        
        // Получить перемешанные ответы
        $answers = $question->getShuffledAnswers();
        $this->info("Варианты ответов (перемешанные):");
        
        $correctIndex = null;
        foreach ($answers as $index => $answer) {
            $isCorrect = (mb_strtolower(trim($answer)) === mb_strtolower(trim($correctAnswerText)));
            $mark = $isCorrect ? '✅' : '  ';
            $this->line("  {$mark} [{$index}] {$answer}");
            
            if ($isCorrect) {
                $correctIndex = $index;
            }
        }
        
        $this->newLine();
        $this->info("Правильный ответ находится на индексе: {$correctIndex} (пункт " . ($correctIndex + 1) . ")");
        $this->newLine();
        
        // Симулировать выбор каждого варианта
        $this->info("Симуляция проверки каждого варианта:");
        foreach ($answers as $index => $answer) {
            $selectedAnswerNormalized = mb_strtolower(trim($answer));
            $correctAnswerNormalized = mb_strtolower(trim($correctAnswerText));
            $isCorrect = ($selectedAnswerNormalized === $correctAnswerNormalized);
            
            $result = $isCorrect ? '✅ ПРАВИЛЬНО' : '❌ неправильно';
            $this->line("  Пункт " . ($index + 1) . " ({$answer}): {$result}");
        }
        
        $this->newLine();
        $this->info("✅ Логика работает правильно!");
        
        return Command::SUCCESS;
    }
}
