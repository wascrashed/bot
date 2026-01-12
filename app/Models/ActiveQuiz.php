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
        return Carbon::now()->greaterThan($this->expires_at);
    }

    /**
     * Получить оставшееся время в секундах
     */
    public function getRemainingSeconds(): int
    {
        $remaining = Carbon::now()->diffInSeconds($this->expires_at, false);
        return max(0, $remaining);
    }
}
