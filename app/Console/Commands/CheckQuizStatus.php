<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ActiveQuiz;
use App\Models\QuizResult;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CheckQuizStatus extends Command
{
    protected $signature = 'quiz:status {--chat-id= : ID чата для проверки}';
    protected $description = 'Проверить статус активных викторин и ответов';

    public function handle()
    {
        $chatId = $this->option('chat-id');
        
        $this->info('=== Статус викторин ===');
        
        // Найти активные викторины
        $query = ActiveQuiz::where('is_active', true)
            ->with(['question', 'results']);
            
        if ($chatId) {
            $query->where('chat_id', $chatId);
        }
        
        $activeQuizzes = $query->get();
        
        if ($activeQuizzes->isEmpty()) {
            $this->warn('⚠️ Активных викторин не найдено');
            
            // Показать последние завершенные викторины
            $lastQuizzes = ActiveQuiz::latest()->take(5)->get();
            if ($lastQuizzes->isNotEmpty()) {
                $this->info("\n📋 Последние викторины:");
                foreach ($lastQuizzes as $quiz) {
                    $resultsCount = QuizResult::where('active_quiz_id', $quiz->id)->count();
                    $timeAgo = $quiz->started_at->diffForHumans();
                    $status = $quiz->is_active ? '🟢 Активна' : '🔴 Завершена';
                    $this->line("  {$status} | Чат: {$quiz->chat_id} | Ответов: {$resultsCount} | {$timeAgo}");
                }
            }
            return Command::SUCCESS;
        }
        
        foreach ($activeQuizzes as $quiz) {
            $this->info("\n🎮 Викторина #{$quiz->id}");
            $this->line("   Чат ID: {$quiz->chat_id}");
            $this->line("   Вопрос ID: {$quiz->question_id}");
            $this->line("   Начата: {$quiz->started_at->format('d.m.Y H:i:s')}");
            
            $now = Carbon::now();
            $elapsed = $now->diffInSeconds($quiz->started_at);
            $remaining = max(0, $quiz->expires_at->diffInSeconds($now));
            
            $this->line("   Прошло: {$elapsed} сек.");
            $this->line("   Осталось: {$remaining} сек.");
            $this->line("   Истекает: {$quiz->expires_at->format('d.m.Y H:i:s')}");
            
            // Проверить результаты
            $results = QuizResult::where('active_quiz_id', $quiz->id)->get();
            $this->line("   📊 Ответов в БД: {$results->count()}");
            
            if ($results->isNotEmpty()) {
                $this->line("   📝 Детали ответов:");
                foreach ($results as $result) {
                    $userName = $result->first_name ?? $result->username ?? "ID:{$result->user_id}";
                    $correct = $result->is_correct ? '✅' : '❌';
                    $time = number_format($result->response_time_ms / 1000, 2);
                    $answerText = $result->activeQuiz ? $result->getAnswerText() : $result->answer;
                    $this->line("      {$correct} {$userName}: '{$answerText}' ({$time} сек.)");
                }
            } else {
                $this->warn("   ⚠️ Ответов не найдено в БД!");
            }
            
            // Проверить задачи в очереди
            $jobs = DB::table('jobs')
                ->where('queue', 'default')
                ->get();
            
            $checkQuizJobs = 0;
            foreach ($jobs as $job) {
                $payload = json_decode($job->payload, true);
                if (isset($payload['displayName']) && $payload['displayName'] === 'App\\Jobs\\CheckQuizResults') {
                    $data = unserialize($payload['data']['command']);
                    if (isset($data->activeQuizId) && $data->activeQuizId == $quiz->id) {
                        $checkQuizJobs++;
                        $availableAt = Carbon::createFromTimestamp($job->available_at);
                        $this->line("   ⏰ Задача CheckQuizResults запланирована на: {$availableAt->format('d.m.Y H:i:s')}");
                        $this->line("      До выполнения: {$availableAt->diffForHumans()}");
                    }
                }
            }
            
            if ($checkQuizJobs == 0) {
                $this->warn("   ⚠️ Задача CheckQuizResults не найдена в очереди!");
            }
        }
        
        // Проверить общую статистику
        $this->info("\n📊 Общая статистика:");
        $totalActive = ActiveQuiz::where('is_active', true)->count();
        $totalResults = QuizResult::whereHas('activeQuiz', function($q) {
            $q->where('is_active', true);
        })->count();
        $totalJobs = DB::table('jobs')->count();
        
        $this->line("   Активных викторин: {$totalActive}");
        $this->line("   Всего ответов: {$totalResults}");
        $this->line("   Задач в очереди: {$totalJobs}");
        
        return Command::SUCCESS;
    }
}
