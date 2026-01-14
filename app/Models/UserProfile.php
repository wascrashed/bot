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

    // Ранги
    const RANK_RECRUIT = 'recruit';        // Рекрут
    const RANK_GUARDIAN = 'guardian';      // Страж
    const RANK_KNIGHT = 'knight';          // Рыцарь
    const RANK_HERO = 'hero';              // Герой
    const RANK_LEGEND = 'legend';          // Легенда
    const RANK_OVERLORD = 'overlord';      // Властилин
    const RANK_DEITY = 'deity';            // Божество
    const RANK_TITAN = 'titan';            // Титан

    // Пороги очков для каждого ранга
    const RANK_THRESHOLDS = [
        self::RANK_RECRUIT => [0, 770],        // 0-770 (1-5 звезд)
        self::RANK_GUARDIAN => [770, 1540],    // 770-1540 (1-5 звезд)
        self::RANK_KNIGHT => [1540, 2310],     // 1540-2310 (1-5 звезд)
        self::RANK_HERO => [2310, 3080],       // 2310-3080 (1-5 звезд)
        self::RANK_LEGEND => [3080, 3850],     // 3080-3850 (1-5 звезд)
        self::RANK_OVERLORD => [3850, 4620],   // 3850-4620 (1-5 звезд)
        self::RANK_DEITY => [4620, 5500],      // 4620-5500 (1-5 звезд)
        self::RANK_TITAN => [5500, PHP_INT_MAX], // 5500+ (без звезд, с цифрами если > 7000)
    ];
    
    const TITAN_MIN_FOR_NUMBERS = 7000; // Минимум очков для отображения цифр у Титана
    
    /**
     * Получить позицию в топе для Титана (если >= 7000 очков)
     */
    public function getTitanLeaderboardPosition(): ?int
    {
        if ($this->rank_tier !== self::RANK_TITAN || $this->rank_points < self::TITAN_MIN_FOR_NUMBERS) {
            return null;
        }
        
        // Подсчитываем, сколько Титанов с >= 7000 очков имеют больше очков
        $position = self::where('rank_tier', self::RANK_TITAN)
            ->where('rank_points', '>=', self::TITAN_MIN_FOR_NUMBERS)
            ->where('rank_points', '>', $this->rank_points)
            ->count() + 1;
        
        return $position;
    }

    /**
     * Получить или создать профиль пользователя
     */
    public static function getOrCreate(int $userId): self
    {
            return self::firstOrCreate(
            ['user_id' => $userId],
            [
                'rank_tier' => self::RANK_RECRUIT,
                'rank_stars' => 1,
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
                
                // Для Титана: если очки >= 7000, используем цифры вместо звезд
                if ($tier === self::RANK_TITAN && $points >= self::TITAN_MIN_FOR_NUMBERS) {
                    // Для Титана с цифрами: rank_stars хранит количество очков выше 7000
                    // Например, 7500 очков = rank_stars = 500
                    $this->rank_stars = $points - self::TITAN_MIN_FOR_NUMBERS;
                } else {
                    // Для остальных рангов и Титана < 7000: рассчитываем звезды (1-5)
                    if ($tier === self::RANK_TITAN) {
                        // Титан до 7000: без звезд
                        $this->rank_stars = 0;
                    } else {
                        $range = $threshold[1] - $threshold[0];
                        $positionInRange = $points - $threshold[0];
                        $this->rank_stars = min(5, max(1, (int)ceil(($positionInRange / $range) * 5)));
                    }
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
            self::RANK_RECRUIT => 'Рекрут',
            self::RANK_GUARDIAN => 'Страж',
            self::RANK_KNIGHT => 'Рыцарь',
            self::RANK_HERO => 'Герой',
            self::RANK_LEGEND => 'Легенда',
            self::RANK_OVERLORD => 'Властилин',
            self::RANK_DEITY => 'Божество',
            self::RANK_TITAN => 'Титан',
        ];
        
        return $names[$this->rank_tier] ?? 'Неизвестно';
    }

    /**
     * Получить эмодзи ранга
     */
    public function getRankEmoji(): string
    {
        $emojis = [
            self::RANK_RECRUIT => '🟤',
            self::RANK_GUARDIAN => '🟢',
            self::RANK_KNIGHT => '🟡',
            self::RANK_HERO => '🔵',
            self::RANK_LEGEND => '🟣',
            self::RANK_OVERLORD => '🟠',
            self::RANK_DEITY => '🔴',
            self::RANK_TITAN => '⚪',
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
        
        // Для Титана: если очки >= 7000, показываем позицию в топе (как в Dota 2)
        if ($this->rank_tier === self::RANK_TITAN) {
            if ($this->rank_points >= self::TITAN_MIN_FOR_NUMBERS) {
                // Показываем позицию в лидерборде (например, Титан #1, Титан #2)
                $position = $this->getTitanLeaderboardPosition();
                if ($position !== null) {
                    return "{$emoji} {$rankName} #{$position}";
                }
                // Fallback: если не удалось получить позицию, показываем цифры
                return "{$emoji} {$rankName} +{$this->rank_stars}";
            } else {
                // Титан до 7000: без звезд и цифр
                return "{$emoji} {$rankName}";
            }
        }
        
        // У остальных рангов есть звезды
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
        
        // Для Титана: если очки >= 7000, показываем позицию в топе, иначе без звезд
        if ($this->rank_tier === self::RANK_TITAN) {
            if ($this->rank_points >= self::TITAN_MIN_FOR_NUMBERS) {
                $position = $this->getTitanLeaderboardPosition();
                if ($position !== null) {
                    return "{$icon} {$userName} ({$rankName} #{$position})";
                }
                // Fallback
                return "{$icon} {$userName} ({$rankName} +{$this->rank_stars})";
            } else {
                return "{$icon} {$userName} ({$rankName})";
            }
        }
        
        // У остальных рангов есть звезды
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
