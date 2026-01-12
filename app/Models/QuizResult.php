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
     * Получить текст ответа
     * Теперь answer всегда содержит текст ответа, а не индекс
     */
    public function getAnswerText(): string
    {
        // answer теперь всегда содержит текст ответа
        return $this->answer;
    }
}
