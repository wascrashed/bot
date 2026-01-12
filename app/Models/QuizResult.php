<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'active_quiz_id',
        'user_id',
        'username',
        'first_name',
        'answer',
        'is_correct',
        'response_time_ms',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    /**
     * Связь с активной викториной
     */
    public function activeQuiz()
    {
        return $this->belongsTo(ActiveQuiz::class, 'active_quiz_id');
    }

    /**
     * Получить текст ответа (для вопросов с выбором - по индексу из answers_order)
     */
    public function getAnswerText(): string
    {
        // Если answer - это число (индекс), получить текст из answers_order
        if (is_numeric($this->answer) && $this->activeQuiz) {
            $answersOrder = $this->activeQuiz->answers_order ?? [];
            $index = (int)$this->answer;
            if (isset($answersOrder[$index])) {
                return $answersOrder[$index];
            }
        }
        
        // Иначе вернуть сам answer (для текстовых вопросов)
        return $this->answer;
    }
}
