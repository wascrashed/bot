<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ActiveQuiz;
use App\Services\QuizService;
use Carbon\Carbon;

class FinishStuckQuizzes extends Command
{
    protected $signature = 'quiz:finish-stuck';
    protected $description = 'Завершить истекшие викторины (expires_at <= now)';

    public function handle(QuizService $quizService)
    {
        $this->info('=== Поиск зависших викторин ===');
        
        // Найти викторины, которые должны были завершиться
        $stuckQuizzes = ActiveQuiz::where('is_active', true)
            ->where('expires_at', '<=', now())
            ->get();
        
        if ($stuckQuizzes->isEmpty()) {
            $this->info('✅ Зависших викторин не найдено');
            return Command::SUCCESS;
        }
        
        $this->warn("⚠️ Найдено зависших викторин: {$stuckQuizzes->count()}");
        
        foreach ($stuckQuizzes as $quiz) {
            $elapsed = now()->diffInSeconds($quiz->expires_at);
            $this->line("   Викторина #{$quiz->id} | Чат: {$quiz->chat_id} | Просрочена на: {$elapsed} сек.");
            
            // Завершить викторину
            $quizService->finishQuiz($quiz->id);
            $this->info("   ✅ Викторина #{$quiz->id} завершена");
        }
        
        $this->info("\n✅ Все зависшие викторины завершены");
        return Command::SUCCESS;
    }
}
