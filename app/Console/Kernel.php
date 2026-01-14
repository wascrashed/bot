<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Запускать викторину каждые 6 минут (10 раз в час) - только если включено
        $autoQuizEnabled = \Illuminate\Support\Facades\Cache::get('auto_quiz_enabled', config('telegram.auto_quiz_enabled', true));
        if ($autoQuizEnabled) {
            $schedule->command('quiz:start-random')
                ->cron('*/6 * * * *')
                ->withoutOverlapping()
                ->runInBackground();
        }
        
        // Обновлять аналитику каждые 5 минут
        $schedule->command('analytics:update')
            ->cron('*/5 * * * *')
            ->withoutOverlapping();
        
        // Очищать старые записи истории вопросов (старше 48 часов)
        $schedule->call(function () {
                \App\Models\QuestionHistory::where('asked_at', '<', now()->subHours(48))->delete();
            })
            ->hourly();
        
        // Завершать зависшие викторины (каждые 10 секунд)
        // ВАЖНО: Эта команда должна запускаться каждые 10 секунд через cron напрямую
        // Добавьте в crontab: * * * * * cd /path-to-project && php artisan quiz:finish-stuck >> /dev/null 2>&1
        // И еще 5 записей с задержкой 10, 20, 30, 40, 50 секунд
        // Или используйте: */10 * * * * * (если cron поддерживает секунды)
        $schedule->command('quiz:finish-stuck')
            ->everyMinute() // Fallback на случай, если cron не настроен
            ->withoutOverlapping();
        
        // Очищать старые завершенные викторины (старше 7 дней) - каждый день в 3:00
        $schedule->command('quiz:clean-old --days=7 --force')
            ->dailyAt('03:00')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
