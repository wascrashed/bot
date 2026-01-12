<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ActiveQuiz;
use App\Models\QuizResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DiagnoseQuiz extends Command
{
    protected $signature = 'quiz:diagnose';
    protected $description = 'Полная диагностика работы викторин и cron';

    public function handle()
    {
        $this->info('=== ПОЛНАЯ ДИАГНОСТИКА ВИКТОРИН ===');
        
        // 1. Проверить активные викторины
        $this->info("\n1. АКТИВНЫЕ ВИКТОРИНЫ:");
        $activeQuizzes = ActiveQuiz::where('is_active', true)->get();
        
        if ($activeQuizzes->isEmpty()) {
            $this->warn("   ⚠️ Активных викторин нет");
        } else {
            foreach ($activeQuizzes as $quiz) {
                $now = Carbon::now();
                $elapsed = $now->diffInSeconds($quiz->started_at);
                $remaining = max(0, $quiz->expires_at->diffInSeconds($now));
                $isExpired = $quiz->isExpired();
                $expiresBeforeStart = $quiz->expires_at->lessThan($quiz->started_at);
                
                $this->line("   Викторина #{$quiz->id}:");
                $this->line("      Чат: {$quiz->chat_id}");
                $this->line("      Начата: {$quiz->started_at->format('Y-m-d H:i:s')}");
                $this->line("      Истекает: {$quiz->expires_at->format('Y-m-d H:i:s')}");
                $this->line("      Сейчас: {$now->format('Y-m-d H:i:s')}");
                $this->line("      Прошло: {$elapsed} сек.");
                $this->line("      Осталось: {$remaining} сек.");
                
                if ($expiresBeforeStart) {
                    $this->error("      ❌ КРИТИЧНО: expires_at раньше started_at!");
                }
                
                if ($isExpired) {
                    $this->warn("      ⚠️ Викторина истекла");
                } else {
                    $this->info("      ✅ Викторина активна");
                }
                
                $resultsCount = QuizResult::where('active_quiz_id', $quiz->id)->count();
                $this->line("      Ответов в БД: {$resultsCount}");
            }
        }
        
        // 2. Проверить последние викторины
        $this->info("\n2. ПОСЛЕДНИЕ 5 ВИКТОРИН:");
        $lastQuizzes = ActiveQuiz::latest()->take(5)->get();
        foreach ($lastQuizzes as $quiz) {
            $resultsCount = QuizResult::where('active_quiz_id', $quiz->id)->count();
            $timeAgo = $quiz->started_at->diffForHumans();
            $status = $quiz->is_active ? '🟢 Активна' : '🔴 Завершена';
            $expiresBeforeStart = $quiz->expires_at->lessThan($quiz->started_at);
            
            $this->line("   {$status} | ID: {$quiz->id} | Чат: {$quiz->chat_id}");
            $this->line("      Ответов: {$resultsCount} | {$timeAgo}");
            $this->line("      Начата: {$quiz->started_at->format('Y-m-d H:i:s')}");
            $this->line("      Истекает: {$quiz->expires_at->format('Y-m-d H:i:s')}");
            
            if ($expiresBeforeStart) {
                $this->error("      ❌ КРИТИЧНО: expires_at ({$quiz->expires_at->format('Y-m-d H:i:s')}) раньше started_at ({$quiz->started_at->format('Y-m-d H:i:s')})!");
            }
        }
        
        // 3. Проверить последние ответы
        $this->info("\n3. ПОСЛЕДНИЕ 10 ОТВЕТОВ:");
        $lastResults = QuizResult::with('activeQuiz')->latest()->take(10)->get();
        if ($lastResults->isEmpty()) {
            $this->warn("   ⚠️ Ответов не найдено");
        } else {
            foreach ($lastResults as $result) {
                $userName = $result->first_name ?? $result->username ?? "ID:{$result->user_id}";
                $timeAgo = $result->created_at->diffForHumans();
                $correct = $result->is_correct ? '✅' : '❌';
                $answerText = $result->activeQuiz ? $result->getAnswerText() : $result->answer;
                $this->line("   {$correct} {$userName}: '{$answerText}' ({$timeAgo})");
            }
        }
        
        // 4. Проверить очередь
        $this->info("\n4. ОЧЕРЕДЬ:");
        $jobsCount = DB::table('jobs')->count();
        $this->line("   Всего задач: {$jobsCount}");
        
        if ($jobsCount > 0) {
            $jobs = DB::table('jobs')->take(5)->get();
            foreach ($jobs as $job) {
                try {
                    $payload = json_decode($job->payload, true);
                    $displayName = $payload['displayName'] ?? 'Unknown';
                    $availableAt = Carbon::createFromTimestamp($job->available_at);
                    $now = Carbon::now();
                    $ready = $availableAt->lessThanOrEqualTo($now) ? '✅ Готова' : '⏰ Ожидает';
                    $this->line("   - {$displayName} | {$ready} | Доступна: {$availableAt->format('Y-m-d H:i:s')}");
                } catch (\Exception $e) {
                    $this->error("   - ❌ Битая задача #{$job->id}: {$e->getMessage()}");
                }
            }
        }
        
        // 5. Проверить cron логи
        $this->info("\n5. CRON ЛОГИ (последние 5 записей):");
        $cronLogPath = storage_path('logs/cron.log');
        if (file_exists($cronLogPath)) {
            $lines = file($cronLogPath);
            $lastLines = array_slice($lines, -5);
            foreach ($lastLines as $line) {
                $this->line("   " . trim($line));
            }
        } else {
            $this->warn("   ⚠️ Файл cron.log не найден");
        }
        
        // 6. Проверить последние логи webhook
        $this->info("\n6. ПОСЛЕДНИЕ СОБЫТИЯ WEBHOOK (последние 10):");
        $logPath = storage_path('logs/laravel.log');
        if (file_exists($logPath)) {
            $lines = file($logPath);
            $lastLines = array_slice($lines, -50);
            $webhookEvents = [];
            foreach ($lastLines as $line) {
                if (stripos($line, 'webhook') !== false || 
                    stripos($line, 'message received') !== false || 
                    stripos($line, 'processing text answer') !== false ||
                    stripos($line, 'active quiz found') !== false ||
                    stripos($line, 'quiz answer saved') !== false) {
                    $webhookEvents[] = trim($line);
                }
            }
            
            if (empty($webhookEvents)) {
                $this->warn("   ⚠️ Событий webhook не найдено в последних 50 строках лога");
            } else {
                foreach (array_slice($webhookEvents, -10) as $event) {
                    $this->line("   " . substr($event, 0, 150));
                }
            }
        } else {
            $this->warn("   ⚠️ Файл laravel.log не найден");
        }
        
        // 7. Проверить настройки
        $this->info("\n7. НАСТРОЙКИ:");
        $autoQuizEnabled = \Illuminate\Support\Facades\Cache::get('auto_quiz_enabled', config('telegram.auto_quiz_enabled', true));
        $this->line("   Авто-викторины: " . ($autoQuizEnabled ? '✅ Включены' : '❌ Выключены'));
        
        $activeChats = \App\Models\ChatStatistics::where('is_active', true)->count();
        $this->line("   Активных чатов: {$activeChats}");
        
        // 8. Рекомендации
        $this->info("\n8. РЕКОМЕНДАЦИИ:");
        
        $hasExpiredQuizzes = ActiveQuiz::where('is_active', true)
            ->where('expires_at', '<=', now())
            ->exists();
        
        if ($hasExpiredQuizzes) {
            $this->warn("   ⚠️ Есть истекшие активные викторины. Выполните: php artisan quiz:finish-stuck");
        }
        
        $hasInvalidExpires = ActiveQuiz::where('is_active', true)
            ->get()
            ->filter(function($q) {
                return $q->expires_at->lessThan($q->started_at);
            })
            ->count();
        
        if ($hasInvalidExpires > 0) {
            $this->error("   ❌ Найдено викторин с неправильным expires_at. Выполните: php artisan quiz:fix-expires");
        }
        
        if ($jobsCount > 10) {
            $this->warn("   ⚠️ Много задач в очереди ({$jobsCount}). Проверьте работу queue:work");
        }
        
        $this->info("\n✅ Диагностика завершена");
        return Command::SUCCESS;
    }
}
