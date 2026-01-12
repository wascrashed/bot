<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserScore extends Model
{
    use HasFactory;

    protected $table = 'user_scores';

    protected $fillable = [
        'user_id',
        'chat_id',
        'username',
        'first_name',
        'total_points',
        'correct_answers',
        'total_answers',
        'first_place_count',
        'last_activity_at',
    ];

    protected $casts = [
        'last_activity_at' => 'datetime',
    ];

    /**
     * Добавить очки пользователю
     */
    public function addPoints(int $points, bool $isCorrect = true): void
    {
        $this->total_points += $points;
        $this->total_answers++;
        
        if ($isCorrect) {
            $this->correct_answers++;
        }
        
        $this->last_activity_at = now();
        $this->save();
    }

    /**
     * Увеличить счетчик первых мест
     */
    public function incrementFirstPlace(): void
    {
        $this->increment('first_place_count');
    }
}
