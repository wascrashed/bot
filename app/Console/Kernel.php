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
