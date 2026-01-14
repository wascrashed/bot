<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'game_nickname',
        'dotabuff_url',
        'total_points',
        'rank_points',
        'rank_tier',
        'rank_stars',
        'show_rank_in_name',
        'dotabuff_data',
        'dotabuff_last_sync',
    ];

    protected $casts = [
        'total_points' => 'integer',
        'rank_points' => 'integer',
        'rank_stars' => 'integer',
        'show_rank_in_name' => 'boolean',
        'dotabuff_data' => 'array',
        'dotabuff_last_sync' => 'datetime',
    ];

    // Ранги Dota 2 стиль
    const RANK_HERALD = 'herald';
    const RANK_GUARDIAN = 'guardian';
    const RANK_CRUSADER = 'crusader';
    const RANK_ARCHON = 'archon';
    const RANK_LEGEND = 'legend';
    const RANK_ANCIENT = 'ancient';
    const RANK_DIVINE = 'divine';
    const RANK_IMMORTAL = 'immortal';

    // Пороги очков для каждого ранга (как в Dota 2)
    const RANK_THRESHOLDS = [
        self::RANK_HERALD => [0, 770],      // 0-770 (1-5 звезд)
        self::RANK_GUARDIAN => [770, 1540],  // 770-1540 (1-5 звезд)
        self::RANK_CRUSADER => [1540, 2310], // 1540-2310 (1-5 звезд)
        self::RANK_ARCHON => [2310, 3080],   // 2310-3080 (1-5 звезд)
        self::RANK_LEGEND => [3080, 3850],    // 3080-3850 (1-5 звезд)
        self::RANK_ANCIENT => [3850, 4620],  // 3850-4620 (1-5 звезд)
        self::RANK_DIVINE => [4620, 5500],    // 4620-5500 (1-5 звезд)
        self::RANK_IMMORTAL => [5500, PHP_INT_MAX], // 5500+ (без звезд)
    ];

    /**
     * Получить или создать профиль пользователя
     */
    public static function getOrCreate(int $userId): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId],
            [
                'rank_tier' => self::RANK_HERALD,
                'rank_stars' => 0,
                'total_points' => 0,
                'rank_points' => 0,
            ]
        );
    }

    /**
     * Рассчитать ранг на основе очков
     */
    public function calculateRank(): void
    {
        $points = $this->rank_points;
        
        foreach (self::RANK_THRESHOLDS as $tier => $threshold) {
            if ($points >= $threshold[0] && $points < $threshold[1]) {
                $this->rank_tier = $tier;
                
                // Для Immortal нет звезд
                if ($tier === self::RANK_IMMORTAL) {
                    $this->rank_stars = 0;
                } else {
                    // Рассчитываем звезды (1-5) внутри ранга
                    $range = $threshold[1] - $threshold[0];
                    $positionInRange = $points - $threshold[0];
                    $this->rank_stars = min(5, max(1, (int)ceil(($positionInRange / $range) * 5)));
                }
                
                break;
            }
        }
        
        $this->save();
    }

    /**
     * Добавить очки и пересчитать ранг
     */
    public function addPoints(int $points): void
    {
        $this->total_points += $points;
        $this->rank_points += $points;
        $this->calculateRank();
    }

    /**
     * Получить название ранга на русском
     */
    public function getRankNameRu(): string
    {
        $names = [
            self::RANK_HERALD => 'Рекрут',
            self::RANK_GUARDIAN => 'Страж',
            self::RANK_CRUSADER => 'Крестоносец',
            self::RANK_ARCHON => 'Архонт',
            self::RANK_LEGEND => 'Легенда',
            self::RANK_ANCIENT => 'Древний',
            self::RANK_DIVINE => 'Божественный',
            self::RANK_IMMORTAL => 'Бессмертный',
        ];
        
        return $names[$this->rank_tier] ?? 'Неизвестно';
    }

    /**
     * Получить эмодзи ранга
     */
    public function getRankEmoji(): string
    {
        $emojis = [
            self::RANK_HERALD => '🟤',
            self::RANK_GUARDIAN => '🟢',
            self::RANK_CRUSADER => '🟡',
            self::RANK_ARCHON => '🔵',
            self::RANK_LEGEND => '🟣',
            self::RANK_ANCIENT => '🟠',
            self::RANK_DIVINE => '🔴',
            self::RANK_IMMORTAL => '⚪',
        ];
        
        return $emojis[$this->rank_tier] ?? '⚫';
    }

    /**
     * Получить форматированное отображение ранга
     */
    public function getFormattedRank(): string
    {
        $rankName = $this->getRankNameRu();
        $emoji = $this->getRankEmoji();
        
        if ($this->rank_tier === self::RANK_IMMORTAL) {
            return "{$emoji} {$rankName}";
        }
        
        return "{$emoji} {$rankName} {$this->rank_stars}⭐";
    }

    /**
     * Получить общие очки пользователя по всем чатам
     */
    public function updateTotalPoints(): void
    {
        $total = UserScore::where('user_id', $this->user_id)
            ->sum('total_points');
        
        $this->total_points = $total;
        $this->rank_points = $total; // Используем общие очки для ранга
        $this->calculateRank();
        $this->save();
    }

    /**
     * Получить значок ранга (из Dotabuff или локальный)
     */
    public function getRankIcon(): string
    {
        // Сначала проверяем значок из Dotabuff
        if ($this->dotabuff_data && isset($this->dotabuff_data['rank_icon'])) {
            return $this->dotabuff_data['rank_icon'];
        }
        
        // Если нет, используем локальный эмодзи ранга
        return $this->getRankEmoji();
    }

    /**
     * Форматировать имя пользователя с значком ранга
     */
    public function formatNameWithRank(string $userName): string
    {
        if (!$this->show_rank_in_name) {
            return $userName;
        }
        
        // Если есть данные из Dotabuff, показываем ранг из Dotabuff
        if ($this->dotabuff_data && isset($this->dotabuff_data['rank'])) {
            $dotabuffRank = $this->dotabuff_data['rank'];
            // Используем эмодзи ранга бота + ранг из Dotabuff
            $icon = $this->getRankEmoji();
            return "{$icon} {$userName} (Dota: {$dotabuffRank})";
        }
        
        // Иначе показываем локальный ранг бота
        $icon = $this->getRankEmoji();
        $rankName = $this->getRankNameRu();
        
        if ($this->rank_tier === self::RANK_IMMORTAL) {
            return "{$icon} {$userName} ({$rankName})";
        }
        
        return "{$icon} {$userName} ({$rankName} {$this->rank_stars}⭐)";
    }

    /**
     * Статический метод для форматирования имени пользователя с рангом
     */
    public static function formatUserName(int $userId, string $userName): string
    {
        $profile = self::where('user_id', $userId)->first();
        
        if (!$profile || !$profile->show_rank_in_name) {
            return $userName;
        }
        
        return $profile->formatNameWithRank($userName);
    }
}
