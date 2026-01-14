<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemeSuggestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'username',
        'first_name',
        'media_type',
        'media_url',
        'file_id',
        'status',
        'admin_comment',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    // Статусы
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    // Типы медиа
    const TYPE_PHOTO = 'photo';
    const TYPE_VIDEO = 'video';

    /**
     * Связь с админом, который рассмотрел
     */
    public function reviewer()
    {
        return $this->belongsTo(\App\Models\AdminUser::class, 'reviewed_by');
    }

    /**
     * Получить все ожидающие рассмотрения предложения
     */
    public static function getPending()
    {
        return self::where('status', self::STATUS_PENDING)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Получить количество ожидающих предложений
     */
    public static function getPendingCount(): int
    {
        return self::where('status', self::STATUS_PENDING)->count();
    }
}
