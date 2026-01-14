<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Meme extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'media_type', // 'photo' или 'video'
        'media_url',
        'file_id', // Telegram file_id для оптимизации
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Типы медиа
    const TYPE_PHOTO = 'photo';
    const TYPE_VIDEO = 'video';

    /**
     * Получить случайный активный мем
     */
    public static function getRandom(): ?self
    {
        return self::where('is_active', true)
            ->inRandomOrder()
            ->first();
    }
}
