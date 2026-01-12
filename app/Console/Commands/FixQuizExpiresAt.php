<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ActiveQuiz;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FixQuizExpiresAt extends Command
{
    protected $signature = 'quiz:fix-expires';
    protected $description = 'Исправить неправильные expires_at у активных викторин';

    public function handle()
    {
        $this->info('=== Исправление expires_at у викторин ===');
        
        // Найти все викторины (активные и завершенные) с неправильным expires_at
        // Проверяем последние 50 викторин для производительности
        $quizzes = ActiveQuiz::latest()->take(50)->get();
        
        $fixed = 0;
        $checked = 0;
        foreach ($quizzes as $quiz) {
            $checked++;
            
            // Прочитать сырые значения из БД напрямую для точности
            $rawData = DB::table('active_quizzes')
                ->where('id', $quiz->id)
                ->first(['started_at', 'expires_at', 'is_active']);
            
            // Создать Carbon объекты из сырых строк, явно указав UTC
            $startedAt = Carbon::createFromFormat('Y-m-d H:i:s', $rawData->started_at, 'UTC');
            $expiresAt = Carbon::createFromFormat('Y-m-d H:i:s', $rawData->expires_at, 'UTC');
            
            // Проверить, что expires_at раньше или равно started_at
            if ($expiresAt->lessThanOrEqualTo($startedAt)) {
                $status = $rawData->is_active ? '🟢 Активна' : '🔴 Завершена';
                $this->warn("Викторина #{$quiz->id} ({$status}): expires_at ({$expiresAt->format('Y-m-d H:i:s T')}) раньше или равно started_at ({$startedAt->format('Y-m-d H:i:s T')})");
                
                // Пересчитать правильно
                $correctExpiresAt = $startedAt->copy()->addSeconds(20);
                DB::table('active_quizzes')
                    ->where('id', $quiz->id)
                    ->update(['expires_at' => $correctExpiresAt->format('Y-m-d H:i:s')]);
                
                $this->info("  ✅ Исправлено: expires_at = {$correctExpiresAt->format('Y-m-d H:i:s T')}");
                $fixed++;
            }
        }
        
        $this->info("\n📊 Проверено викторин: {$checked}");
        if ($fixed > 0) {
            $this->info("✅ Исправлено викторин: {$fixed}");
        } else {
            $this->info("✅ Проблемных викторин не найдено");
        }
        
        return Command::SUCCESS;
    }
}
