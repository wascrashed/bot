<?php

namespace App\Console\Commands;

use App\Models\Question;
use App\Models\ActiveQuiz;
use Illuminate\Console\Command;

class ExplainQuestionStructure extends Command
{
    protected $signature = 'questions:explain {question_id? : ID вопроса для объяснения}';
    protected $description = 'Объяснить структуру вопроса и как работает проверка ответов';

    public function handle(): int
    {
        $questionId = $this->argument('question_id');
        
        if ($questionId) {
            $question = Question::find($questionId);
            if (!$question) {
                $this->error("Вопрос #{$questionId} не найден");
                return Command::FAILURE;
            }
            $this->explainQuestion($question);
        } else {
            $this->info('=== Объяснение структуры вопросов ===\n');
            $this->line('Пример из вашей БД:');
            $this->newLine();
            
            $question = Question::first();
            if ($question) {
                $this->explainQuestion($question);
            } else {
                $this->warn('Нет вопросов в БД');
            }
        }
        
        return Command::SUCCESS;
    }
    
    private function explainQuestion(Question $question): void
    {
        $this->info("Вопрос #{$question->id}:");
        $this->line("  Текст: " . substr($question->question, 0, 60) . "...");
        $this->newLine();
        
        $this->info("1. В таблице questions:");
        $this->line("   correct_answer (индекс): {$question->correct_answer}");
        $this->line("   correct_answer_text: {$question->correct_answer_text}");
        $this->line("   wrong_answers: " . json_encode($question->wrong_answers));
        $this->newLine();
        
        $allAnswers = $question->getAllAnswers();
        $this->info("2. Исходный массив ответов (до перемешивания):");
        foreach ($allAnswers as $index => $answer) {
            $marker = ($index == (int)$question->correct_answer) ? '✅' : '  ';
            $this->line("   {$marker} [{$index}] {$answer}");
        }
        $this->newLine();
        
        $shuffled = $question->getShuffledAnswers();
        $this->info("3. Перемешанный массив (как показывается пользователю):");
        $correctIndexInShuffled = null;
        foreach ($shuffled as $index => $answer) {
            if ($answer === $question->getCorrectAnswerText()) {
                $correctIndexInShuffled = $index;
                $this->line("   ✅ [{$index}] {$answer} ← правильный ответ");
            } else {
                $this->line("      [{$index}] {$answer}");
            }
        }
        $this->newLine();
        
        $this->info("4. Как это сохраняется в active_quizzes:");
        $this->line("   answers_order: " . json_encode($shuffled));
        $this->line("   correct_answer_index: {$correctIndexInShuffled}");
        $this->newLine();
        
        $this->info("5. Как проверяется ответ:");
        $this->line("   - Пользователь выбирает индекс: например, {$correctIndexInShuffled}");
        $this->line("   - Сохраняется в quiz_results.answer: '{$correctIndexInShuffled}'");
        $this->line("   - Сравнивается: {$correctIndexInShuffled} === {$correctIndexInShuffled} → ✅ Правильно!");
        $this->newLine();
        
        // Проверить активные викторины для этого вопроса
        $activeQuizzes = ActiveQuiz::where('question_id', $question->id)
            ->where('is_active', true)
            ->get();
        
        if ($activeQuizzes->count() > 0) {
            $this->info("6. Активные викторины с этим вопросом:");
            foreach ($activeQuizzes as $aq) {
                $this->line("   Викторина #{$aq->id}:");
                $this->line("      correct_answer_index: " . ($aq->correct_answer_index ?? 'NULL'));
                $this->line("      answers_order: " . json_encode($aq->answers_order));
            }
        }
    }
}
