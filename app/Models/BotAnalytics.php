<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BotAnalytics extends Model
{
    use HasFactory;

    protected $table = 'bot_analytics';

    protected $fillable = [
        'date',
        'active_chats',
        'total_participants',
        'total_quizzes',
        'total_answers',
        'correct_answers',
        'errors_count',
        'avg_response_time_ms',
        'uptime_percentage',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /**
     * Обновить аналитику за сегодня
     */
    public static function updateToday(array $data): void
    {
        $data['date'] = $data['date'] ?? today();
        self::updateOrCreate(
            ['date' => today()],
            $data
        );
    }

    /**
     * Получить аналитику за сегодня
     */
    public static function getToday(): ?self
    {
        return self::where('date', today())->first();
    }
}
