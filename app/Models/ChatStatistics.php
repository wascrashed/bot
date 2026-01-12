<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatStatistics extends Model
{
    use HasFactory;

    protected $table = 'chat_statistics';

    protected $fillable = [
        'chat_id',
        'chat_type',
        'chat_title',
        'total_quizzes',
        'total_participants',
        'total_answers',
        'correct_answers',
        'first_quiz_at',
        'last_quiz_at',
        'is_active',
    ];

    protected $casts = [
        'first_quiz_at' => 'datetime',
        'last_quiz_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Обновить статистику после викторины
     */
    public function updateAfterQuiz(int $totalAnswers, int $correctAnswers, int $uniqueParticipants): void
    {
        $this->total_quizzes++;
        $this->total_answers += $totalAnswers;
        $this->correct_answers += $correctAnswers;
        $this->total_participants = max($this->total_participants, $uniqueParticipants);
        $this->last_quiz_at = now();
        
        if (!$this->first_quiz_at) {
            $this->first_quiz_at = now();
        }
        
        $this->is_active = true;
        $this->save();
    }

    /**
     * Получить или создать статистику для чата
     */
    public static function getOrCreate(int $chatId, string $chatType = 'group', ?string $chatTitle = null): self
    {
        return self::firstOrCreate(
            ['chat_id' => $chatId],
            [
                'chat_type' => $chatType,
                'chat_title' => $chatTitle,
                'is_active' => true,
            ]
        );
    }
}
