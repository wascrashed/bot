<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\ActiveQuiz;
use App\Models\QuizResult;

class TestWebhook extends Command
{
    protected $signature = 'test:webhook';
    protected $description = 'Проверить работу webhook и обработку ответов';

    public function handle()
    {
        $this->info('=== Проверка Webhook и обработки ответов ===');
        
        // 1. Проверить последние логи webhook
        $this->info("\n1. Проверка логов webhook:");
        $logPath = storage_path('logs/laravel.log');
        if (file_exists($logPath)) {
            $lines = file($logPath);
            $lastLines = array_slice($lines, -50);
            $webhookEvents = [];
            foreach ($lastLines as $line) {
                if (stripos($line, 'webhook received') !== false || 
                    stripos($line, 'message received in group') !== false ||
                    stripos($line, 'telegram webhook received') !== false) {
                    $webhookEvents[] = trim($line);
                }
            }
            
            if (empty($webhookEvents)) {
                $this->warn("   ⚠️ Событий webhook не найдено в последних 50 строках");
                $this->warn("   Это может означать, что бот не получает сообщения из группы!");
                $this->info('');
                $this->info('💡 Проверьте:');
                $this->line('   1. Privacy mode должен быть отключен в BotFather');
                $this->line('   2. Бот должен быть добавлен в группу');
                $this->line('   3. Отправьте сообщение в группу и проверьте логи');
            } else {
                $this->info("   ✅ Найдено событий webhook: " . count($webhookEvents));
                foreach (array_slice($webhookEvents, -5) as $event) {
                    $this->line("   " . substr($event, 0, 120));
                }
            }
        } else {
            $this->warn("   ⚠️ Файл laravel.log не найден");
        }
        
        // 2. Проверить активные викторины
        $this->info("\n2. Активные викторины:");
        $activeQuizzes = ActiveQuiz::where('is_active', true)->get();
        if ($activeQuizzes->isEmpty()) {
            $this->warn("   ⚠️ Активных викторин нет");
        } else {
            foreach ($activeQuizzes as $quiz) {
                $resultsCount = QuizResult::where('active_quiz_id', $quiz->id)->count();
                $elapsed = now()->diffInSeconds($quiz->started_at);
                $remaining = max(0, $quiz->expires_at->diffInSeconds(now()));
                $this->line("   Викторина #{$quiz->id} | Чат: {$quiz->chat_id} | Ответов: {$resultsCount} | Прошло: {$elapsed}с | Осталось: {$remaining}с");
            }
        }
        
        // 3. Проверить последние ответы
        $this->info("\n3. Последние 10 ответов:");
        $lastResults = QuizResult::latest()->take(10)->get();
        if ($lastResults->isEmpty()) {
            $this->warn("   ⚠️ Ответов не найдено");
        } else {
            foreach ($lastResults as $result) {
                $userName = $result->first_name ?? $result->username ?? "ID:{$result->user_id}";
                $timeAgo = $result->created_at->diffForHumans();
                $this->line("   {$userName}: '{$result->answer}' ({$timeAgo})");
            }
        }
        
        // 4. Проверить очередь
        $this->info("\n4. Задачи в очереди:");
        $jobsCount = \DB::table('jobs')->count();
        $this->line("   Всего задач: {$jobsCount}");
        
        if ($jobsCount > 0) {
            $jobs = \DB::table('jobs')->take(5)->get();
            foreach ($jobs as $job) {
                $payload = json_decode($job->payload, true);
                $displayName = $payload['displayName'] ?? 'Unknown';
                $availableAt = \Carbon\Carbon::createFromTimestamp($job->available_at);
                $this->line("   - {$displayName} (доступна: {$availableAt->format('H:i:s')})");
            }
        }
        
        // 5. Рекомендации
        $this->info("\n5. Рекомендации:");
        $this->line("   - Проверьте логи: tail -100 storage/logs/laravel.log");
        $this->line("   - Проверьте статус викторины: php artisan quiz:status");
        $this->line("   - Проверьте работу очереди: php artisan queue:work --once --verbose");
        $this->line("   - Проверьте cron: tail -30 storage/logs/cron.log");
        
        return Command::SUCCESS;
    }
}
