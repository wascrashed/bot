<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\ActiveQuiz;
use App\Models\ChatStatistics;
use Illuminate\Support\Facades\Cache;

class CheckCronStatus extends Command
{
    protected $signature = 'cron:status';
    protected $description = 'Проверить статус cron задач и очереди';

    public function handle()
    {
        $this->info('=== Статус Cron задач ===');
        
        // Проверить очередь
        $jobsCount = DB::table('jobs')->count();
        $failedJobsCount = DB::table('failed_jobs')->count();
        
        $this->line("📋 Задач в очереди: {$jobsCount}");
        $this->line("❌ Провалившихся задач: {$failedJobsCount}");
        
        // Проверить активные викторины
        $activeQuizzes = ActiveQuiz::where('is_active', true)->count();
        $this->line("🎮 Активных викторин: {$activeQuizzes}");
        
        // Проверить последние викторины
        $lastQuiz = ActiveQuiz::latest()->first();
        if ($lastQuiz) {
            $timeAgo = $lastQuiz->started_at->diffForHumans();
            $this->line("⏰ Последняя викторина: {$timeAgo} ({$lastQuiz->started_at->format('d.m.Y H:i:s')})");
        } else {
            $this->warn("⚠️ Викторин еще не было");
        }
        
        // Проверить авто-викторины
        $autoQuizEnabled = Cache::get('auto_quiz_enabled', config('telegram.auto_quiz_enabled', true));
        $this->line("⚙️ Авто-викторины: " . ($autoQuizEnabled ? '✅ Включены' : '❌ Выключены'));
        
        // Проверить чаты
        $activeChats = ChatStatistics::where('is_active', true)->count();
        $this->line("💬 Активных чатов: {$activeChats}");
        
        // Проверить викторины за последний час
        $quizzesLastHour = ActiveQuiz::where('started_at', '>=', now()->subHour())->count();
        $this->line("📊 Викторин за последний час: {$quizzesLastHour}");
        
        if ($quizzesLastHour == 0 && $autoQuizEnabled && $activeChats > 0) {
            $this->warn("⚠️ ВНИМАНИЕ: Викторины не запускаются, хотя должны!");
            $this->warn("   Проверьте, что cron задача настроена и выполняется.");
        }
        
        $this->info("\n✅ Проверка завершена");
        
        return Command::SUCCESS;
    }
}
