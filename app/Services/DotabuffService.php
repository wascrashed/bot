<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DotabuffService
{
    /**
     * Получить данные игрока с Dotabuff
     * 
     * @param string $dotabuffUrl URL профиля Dotabuff (например, https://www.dotabuff.com/players/123456789)
     * @return array|null Данные игрока или null при ошибке
     */
    public function getPlayerData(string $dotabuffUrl): ?array
    {
        try {
            // Извлекаем player ID из URL
            $playerId = $this->extractPlayerId($dotabuffUrl);
            
            if (!$playerId) {
                Log::warning('Invalid Dotabuff URL', ['url' => $dotabuffUrl]);
                return null;
            }
            
            // Проверяем кеш (кешируем на 1 час)
            $cacheKey = "dotabuff_player_{$playerId}";
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
            
            // Парсим страницу Dotabuff
            $html = $this->fetchDotabuffPage($dotabuffUrl);
            
            if (!$html) {
                return null;
            }
            
            $data = $this->parseDotabuffPage($html);
            
            // Кешируем результат
            if ($data) {
                Cache::put($cacheKey, $data, now()->addHour());
            }
            
            return $data;
        } catch (\Exception $e) {
            Log::error('Error fetching Dotabuff data', [
                'url' => $dotabuffUrl,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Извлечь Player ID из URL Dotabuff
     */
    private function extractPlayerId(string $url): ?string
    {
        // Формат: https://www.dotabuff.com/players/123456789
        if (preg_match('/\/players\/(\d+)/', $url, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Получить HTML страницы Dotabuff
     */
    private function fetchDotabuffPage(string $url): ?string
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ])
                ->get($url);
            
            if ($response->successful()) {
                return $response->body();
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Failed to fetch Dotabuff page', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Парсить HTML страницы Dotabuff для извлечения данных
     */
    private function parseDotabuffPage(string $html): ?array
    {
        try {
            $data = [
                'mmr' => null,
                'rank' => null,
                'rank_icon' => null,
                'nickname' => null,
            ];
            
            // Парсим ник пользователя (обычно в заголовке страницы или в h1)
            // Ищем в различных местах страницы
            // Вариант 1: В заголовке h1 внутри player-header
            if (preg_match('/<header[^>]*class="[^"]*player-header[^"]*"[^>]*>.*?<h1[^>]*>([^<]+)<\/h1>/is', $html, $matches)) {
                $data['nickname'] = trim($matches[1]);
            }
            // Вариант 2: Прямой h1
            elseif (preg_match('/<h1[^>]*class="[^"]*header-content-title[^"]*"[^>]*>([^<]+)<\/h1>/i', $html, $matches)) {
                $data['nickname'] = trim($matches[1]);
            }
            // Вариант 3: В title страницы
            elseif (preg_match('/<title>([^<]+)<\/title>/i', $html, $matches)) {
                $title = trim($matches[1]);
                // Формат: "Player Name - Dotabuff" или "Player Name - Overview - Dotabuff"
                if (preg_match('/^([^-]+?)(?:\s*-\s*(?:Overview|Dotabuff))?$/i', $title, $titleMatches)) {
                    $data['nickname'] = trim($titleMatches[1]);
                }
            }
            // Вариант 4: В мета-тегах или других местах
            elseif (preg_match('/<meta[^>]*property="og:title"[^>]*content="([^"]+)"/i', $html, $matches)) {
                $ogTitle = trim($matches[1]);
                if (preg_match('/^([^-]+)/', $ogTitle, $ogMatches)) {
                    $data['nickname'] = trim($ogMatches[1]);
                }
            }
            
            // Логируем результат парсинга ника
            if ($data['nickname']) {
                Log::info('Dotabuff nickname parsed', ['nickname' => $data['nickname']]);
            } else {
                Log::warning('Dotabuff nickname not found', ['html_length' => strlen($html)]);
            }
            
            // Парсим MMR (если доступен)
            if (preg_match('/Solo MMR[^>]*>(\d+)/i', $html, $matches)) {
                $data['mmr'] = (int)$matches[1];
            }
            
            // Парсим ранг (если доступен) - ищем в различных форматах
            // Вариант 1: В атрибуте oldtitle или title у rank-tier
            if (preg_match('/<div[^>]*class="[^"]*rank-tier[^"]*"[^>]*(?:oldtitle|title)="([^"]+)"/i', $html, $matches)) {
                $rankText = trim($matches[1]);
                // Формат: "Место: Рыцарь III" или просто "Рыцарь III"
                if (preg_match('/:\s*([^"]+)/', $rankText, $rankMatches)) {
                    $data['rank'] = trim($rankMatches[1]);
                } else {
                    $data['rank'] = $rankText;
                }
            }
            // Вариант 2: В тексте рядом с rank-tier
            elseif (preg_match('/rank-tier[^>]*>.*?([А-Яа-яЁё\s]+(?:I{1,3}|[IVX]+))[^<]*</is', $html, $matches)) {
                $data['rank'] = trim($matches[1]);
            }
            // Вариант 3: Стандартный формат в тексте
            elseif (preg_match('/Rank[^>]*>([^<]+)</i', $html, $matches)) {
                $data['rank'] = trim($matches[1]);
            }
            
            // Логируем результат парсинга ранга
            if ($data['rank']) {
                Log::info('Dotabuff rank parsed', ['rank' => $data['rank']]);
            } else {
                Log::warning('Dotabuff rank not found');
            }
            
            // Парсим иконку ранга (если доступна)
            if (preg_match('/rank-icon[^>]*src="([^"]+)"/i', $html, $matches)) {
                $data['rank_icon'] = $matches[1];
            } elseif (preg_match('/rank-tier[^>]*src="([^"]+)"/i', $html, $matches)) {
                $data['rank_icon'] = $matches[1];
            }
            
            return $data;
        } catch (\Exception $e) {
            Log::error('Error parsing Dotabuff page', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Валидация URL Dotabuff
     */
    public function validateUrl(string $url): bool
    {
        return (bool)preg_match('/^https?:\/\/(www\.)?dotabuff\.com\/players\/\d+/', $url);
    }
}
