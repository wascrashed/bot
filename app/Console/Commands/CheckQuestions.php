<?php

namespace App\Console\Commands;

use App\Models\Question;
use Illuminate\Console\Command;

class CheckQuestions extends Command
{
    protected $signature = 'questions:check';
    protected $description = 'Проверить структуру вопросов';

    public function handle(): int
    {
        $total = Question::count();
        $withText = Question::whereNotNull('correct_answer_text')->count();
        $withIndex = Question::where('correct_answer', '>=', 0)->count();
        
        $this->info("Всего вопросов: {$total}");
        $this->info("С correct_answer_text: {$withText}");
        $this->info("С correct_answer (индекс): {$withIndex}");
        
        if ($total > 0) {
            $sample = Question::first();
            $this->newLine();
            $this->info("Пример вопроса #{$sample->id}:");
            $this->line("  Вопрос: " . substr($sample->question, 0, 50) . "...");
            $this->line("  correct_answer (индекс): {$sample->correct_answer}");
            $this->line("  correct_answer_text: {$sample->correct_answer_text}");
            $this->line("  getCorrectAnswerText(): " . $sample->getCorrectAnswerText());
        }
        
        return Command::SUCCESS;
    }
}
