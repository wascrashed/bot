<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ActiveQuiz;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CleanOldQuizzes extends Command
{
    protected $signature = 'quiz:clean-old 
                            {--days=7 : Удалить завершенные викторины старше указанного количества дней}
                            {--all : Удалить все завершенные викторины}
                            {--broken : Удалить викторины с неправильным expires_at (expires_at <= started_at)}
                            {--force : Не спрашивать подтверждение}';
    
    protected $description = 'Очистить старые завершенные викторины из базы данных';

    public function handle()
    {
        $this->info('=== Очистка старых викторин ===');
        
        $days = $this->option('days');
        $all = $this->option('all');
        $broken = $this->option('broken');
        $force = $this->option('force');
        
        if ($broken) {
            // Удалить викторины с неправильным expires_at
            $quizzes = ActiveQuiz::all();
            $brokenQuizzes = [];
            
            foreach ($quizzes as $quiz) {
                $rawData = DB::table('active_quizzes')
                    ->where('id', $quiz->id)
                    ->first(['started_at', 'expires_at', 'is_active']);
                
                $startedAt = Carbon::createFromFormat('Y-m-d H:i:s', $rawData->started_at, 'UTC');
                $expiresAt = Carbon::createFromFormat('Y-m-d H:i:s', $rawData->expires_at, 'UTC');
                
                if ($expiresAt->lessThanOrEqualTo($startedAt)) {
                    $brokenQuizzes[] = $quiz->id;
                }
            }
            
            $count = count($brokenQuizzes);
            
            if ($count === 0) {
                $this->info('✅ Викторин с неправильным expires_at не найдено');
                return Command::SUCCESS;
            }
            
            $this->warn("Найдено викторин с неправильным expires_at: {$count}");
            $this->line("ID викторин: " . implode(', ', $brokenQuizzes));
            
            if (!$force && !$this->confirm("Удалить {$count} викторин с неправильным expires_at?", false)) {
                $this->info('❌ Операция отменена');
                return Command::SUCCESS;
            }
            
            $deleted = ActiveQuiz::whereIn('id', $brokenQuizzes)->delete();
            $this->info("✅ Удалено викторин: {$deleted}");
            
        } elseif ($all) {
            // Удалить все завершенные викторины
            $count = ActiveQuiz::where('is_active', false)->count();
            
            if ($count === 0) {
                $this->info('✅ Завершенных викторин не найдено');
                return Command::SUCCESS;
            }
            
            $this->warn("Найдено завершенных викторин: {$count}");
            
            if (!$force && !$this->confirm("Удалить все {$count} завершенных викторин?", false)) {
                $this->info('❌ Операция отменена');
                return Command::SUCCESS;
            }
            
            $deleted = ActiveQuiz::where('is_active', false)->delete();
            $this->info("✅ Удалено викторин: {$deleted}");
            
        } else {
            // Удалить викторины старше указанного количества дней
            $cutoffDate = Carbon::now('UTC')->subDays($days);
            
            $count = ActiveQuiz::where('is_active', false)
                ->where('updated_at', '<', $cutoffDate)
                ->count();
            
            if ($count === 0) {
                $this->info("✅ Викторин старше {$days} дней не найдено");
                return Command::SUCCESS;
            }
            
            $this->warn("Найдено викторин старше {$days} дней: {$count}");
            $this->line("Дата отсечения: {$cutoffDate->format('Y-m-d H:i:s T')}");
            
            if (!$force && !$this->confirm("Удалить {$count} старых викторин?", false)) {
                $this->info('❌ Операция отменена');
                return Command::SUCCESS;
            }
            
            $deleted = ActiveQuiz::where('is_active', false)
                ->where('updated_at', '<', $cutoffDate)
                ->delete();
            
            $this->info("✅ Удалено викторин: {$deleted}");
        }
        
        // Показать статистику
        $activeCount = ActiveQuiz::where('is_active', true)->count();
        $completedCount = ActiveQuiz::where('is_active', false)->count();
        $totalCount = ActiveQuiz::count();
        
        $this->info("\n📊 Статистика:");
        $this->line("   Активных викторин: {$activeCount}");
        $this->line("   Завершенных викторин: {$completedCount}");
        $this->line("   Всего викторин: {$totalCount}");
        
        return Command::SUCCESS;
    }
}
