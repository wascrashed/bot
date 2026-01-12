<?php

namespace App\Services;

use App\Models\BotAnalytics;
use App\Models\ChatStatistics;
use App\Models\UserScore;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AnalyticsService
{
    /**
     * Логировать запуск викторины
     */
    public function logQuizStarted(int $chatId, float $responseTimeMs): void
    {
        try {
            $today = BotAnalytics::getToday();
            if ($today) {
                $today->increment('total_quizzes');
                
                // Обновить среднее время ответа
                $avgResponseTime = ($today->avg_response_time_ms * ($today->total_quizzes - 1) + $responseTimeMs) / $today->total_quizzes;
                $today->update(['avg_response_time_ms' => (int) $avgResponseTime]);
            } else {
                BotAnalytics::updateToday([
                    'total_quizzes' => 1,
                    'active_chats' => ChatStatistics::where('is_active', true)->count(),
                    'avg_response_time_ms' => (int) $responseTimeMs,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Analytics log quiz started error', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Логировать ответ пользователя
     */
    public function logAnswer(int $chatId, int $userId, bool $isCorrect, int $responseTimeMs): void
    {
        try {
            $today = BotAnalytics::getToday();
            if ($today) {
                $today->increment('total_answers');
                if ($isCorrect) {
                    $today->increment('correct_answers');
                }
                
                // Обновить среднее время ответа
                $avgResponseTime = ($today->avg_response_time_ms * ($today->total_answers - 1) + $responseTimeMs) / $today->total_answers;
                $today->update(['avg_response_time_ms' => (int) $avgResponseTime]);
            }
        } catch (\Exception $e) {
            Log::error('Analytics log answer error', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Логировать ошибку
     */
    public function logError(string $error): void
    {
        try {
            $today = BotAnalytics::getToday();
            if ($today) {
                $today->increment('errors_count');
            } else {
                BotAnalytics::updateToday([
                    'errors_count' => 1,
                ]);
            }
            
            Log::error('Bot error logged', ['error' => $error]);
        } catch (\Exception $e) {
            Log::error('Analytics log error failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Обновить ежедневную аналитику
     */
    public function updateDailyAnalytics(): void
    {
        try {
            $activeChats = ChatStatistics::where('is_active', true)->count();
            $totalParticipants = UserScore::distinct('user_id')->count('user_id');
            
            BotAnalytics::updateToday([
                'active_chats' => $activeChats,
                'total_participants' => $totalParticipants,
            ]);
        } catch (\Exception $e) {
            Log::error('Update daily analytics error', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Получить аналитику за сегодня
     */
    public function getTodayAnalytics(): array
    {
        try {
            $analytics = BotAnalytics::getToday();
            
            if (!$analytics) {
                return [
                    'active_chats' => 0,
                    'total_participants' => 0,
                    'total_quizzes' => 0,
                    'total_answers' => 0,
                    'correct_answers' => 0,
                    'errors_count' => 0,
                    'avg_response_time_ms' => 0,
                    'uptime_percentage' => 100,
                ];
            }
            
            return [
                'active_chats' => $analytics->active_chats,
                'total_participants' => $analytics->total_participants,
                'total_quizzes' => $analytics->total_quizzes,
                'total_answers' => $analytics->total_answers,
                'correct_answers' => $analytics->correct_answers,
                'errors_count' => $analytics->errors_count,
                'avg_response_time_ms' => $analytics->avg_response_time_ms,
                'uptime_percentage' => $analytics->uptime_percentage,
            ];
        } catch (\Exception $e) {
            Log::error('Get today analytics error', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
}
