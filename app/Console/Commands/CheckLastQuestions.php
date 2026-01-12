<?php

namespace App\Console\Commands;

use App\Models\Question;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckLastQuestions extends Command
{
    protected $signature = 'questions:check-last {count=5}';
    protected $description = 'Проверить последние добавленные вопросы';

    public function handle(): int
    {
        $count = (int)$this->argument('count');
        $questions = Question::orderBy('id', 'desc')->take($count)->get();
        
        $this->info("Последние {$count} вопросов:");
        $this->newLine();
        
        foreach ($questions as $q) {
            $this->line("ID: {$q->id}");
            $this->line("  Вопрос: " . substr($q->question, 0, 60) . "...");
            $this->line("  correct_answer (индекс): {$q->correct_answer}");
            $this->line("  correct_answer_text: " . ($q->correct_answer_text ?? 'NULL'));
            $this->line("  getCorrectAnswerText(): " . $q->getCorrectAnswerText());
            $this->newLine();
        }
        
        // Проверить через прямой SQL
        $this->info("Проверка через SQL:");
        $sqlResult = DB::select("SELECT id, correct_answer, correct_answer_text FROM questions ORDER BY id DESC LIMIT 3");
        foreach ($sqlResult as $row) {
            $this->line("  ID {$row->id}: correct_answer='{$row->correct_answer}', correct_answer_text='" . ($row->correct_answer_text ?? 'NULL') . "'");
        }
        
        return Command::SUCCESS;
    }
}
