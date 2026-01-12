<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ActiveQuiz;
use Carbon\Carbon;

class FixQuizExpiresAt extends Command
{
    protected $signature = 'quiz:fix-expires';
    protected $description = 'Исправить неправильные expires_at у активных викторин';

    public function handle()
    {
        $this->info('=== Исправление expires_at у викторин ===');
        
        // Найти все активные викторины с неправильным expires_at
        $quizzes = ActiveQuiz::where('is_active', true)->get();
        
        $fixed = 0;
        foreach ($quizzes as $quiz) {
            // Проверить, что expires_at раньше started_at
            if ($quiz->expires_at->lessThan($quiz->started_at)) {
                $this->warn("Викторина #{$quiz->id}: expires_at ({$quiz->expires_at->format('Y-m-d H:i:s')}) раньше started_at ({$quiz->started_at->format('Y-m-d H:i:s')})");
                
                // Пересчитать правильно
                $correctExpiresAt = $quiz->started_at->copy()->addSeconds(20);
                $quiz->update(['expires_at' => $correctExpiresAt]);
                
                $this->info("  ✅ Исправлено: expires_at = {$correctExpiresAt->format('Y-m-d H:i:s')}");
                $fixed++;
            }
        }
        
        if ($fixed > 0) {
            $this->info("\n✅ Исправлено викторин: {$fixed}");
        } else {
            $this->info("\n✅ Проблемных викторин не найдено");
        }
        
        return Command::SUCCESS;
    }
}
