<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ActiveQuiz extends Model
{
    use HasFactory;

    protected $table = 'active_quizzes';

    protected $fillable = [
        'chat_id',
        'chat_type',
        'question_id',
        'message_id',
        'answers_order',
        'correct_answer_index',
        'started_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'answers_order' => 'array',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Связь с вопросом
     */
    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * Связь с результатами
     */
    public function results()
    {
        return $this->hasMany(QuizResult::class, 'active_quiz_id');
    }

    /**
     * Проверить, истекло ли время на ответ
     */
    public function isExpired(): bool
    {
        // Убедиться, что используем UTC для сравнения
        $expiresAt = Carbon::parse($this->expires_at)->setTimezone('UTC');
        $now = Carbon::now('UTC');
        // ВАЖНО: использовать lessThanOrEqualTo для точной проверки
        return $expiresAt->lessThanOrEqualTo($now);
    }

    /**
     * Получить оставшееся время в секундах
     */
    public function getRemainingSeconds(): int
    {
        $expiresAt = Carbon::parse($this->expires_at)->setTimezone('UTC');
        $now = Carbon::now('UTC');
        $remaining = $now->diffInSeconds($expiresAt, false);
        return max(0, $remaining);
    }
}
