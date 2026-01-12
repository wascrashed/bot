<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionHistory extends Model
{
    use HasFactory;

    protected $table = 'question_history';

    protected $fillable = [
        'chat_id',
        'question_id',
        'asked_at',
    ];

    protected $casts = [
        'asked_at' => 'datetime',
    ];

    /**
     * Связь с вопросом
     */
    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * Проверить, был ли вопрос задан в чате за последние N часов
     */
    public static function wasAskedRecently(int $chatId, int $questionId, int $hours = 24): bool
    {
        return self::where('chat_id', $chatId)
            ->where('question_id', $questionId)
            ->where('asked_at', '>=', now()->subHours($hours))
            ->exists();
    }

    /**
     * Получить список использованных вопросов за последние N часов
     */
    public static function getRecentQuestionIds(int $chatId, int $hours = 24): array
    {
        return self::where('chat_id', $chatId)
            ->where('asked_at', '>=', now()->subHours($hours))
            ->pluck('question_id')
            ->toArray();
    }
}
