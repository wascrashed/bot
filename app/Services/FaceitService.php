<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class FaceitService
{
    private const API_BASE_URL = 'https://open.faceit.com/data/v4';

    /**
     * Получить данные игрока с Faceit
     * 
     * @param string $username Faceit username
     * @return array|null Данные игрока или null при ошибке
     */
    public function getPlayerData(string $username): ?array
    {
        try {
            // Проверяем кеш (кешируем на 30 минут)
            $cacheKey = "faceit_player_{$username}";
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
            
            // Получаем player ID по username
            $playerId = $this->getPlayerId($username);
            
            if (!$playerId) {
                Log::warning('Faceit player not found', ['username' => $username]);
                return null;
            }
            
            // Получаем данные игрока
            $data = $this->fetchPlayerData($playerId);
            
            // Кешируем результат
            if ($data) {
                Cache::put($cacheKey, $data, now()->addMinutes(30));
            }
            
            return $data;
        } catch (\Exception $e) {
            Log::error('Error fetching Faceit data', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Получить Player ID по username
     */
    private function getPlayerId(string $username): ?string
    {
        try {
            $apiKey = config('services.faceit.api_key');
            
            if (!$apiKey) {
                Log::warning('Faceit API key not configured');
                return null;
            }
            
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                ])
                ->get(self::API_BASE_URL . "/players", [
                    'nickname' => $username,
                ]);
            
            if ($response->successful()) {
                $data = $response->json();
                return $data['player_id'] ?? null;
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Error getting Faceit player ID', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Получить данные игрока по Player ID
     */
    private function fetchPlayerData(string $playerId): ?array
    {
        try {
            $apiKey = config('services.faceit.api_key');
            
            if (!$apiKey) {
                return null;
            }
            
            // Получаем общую информацию
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                ])
                ->get(self::API_BASE_URL . "/players/{$playerId}");
            
            if (!$response->successful()) {
                return null;
            }
            
            $playerData = $response->json();
            
            // Получаем статистику CS2
            $cs2Stats = $this->getCs2Stats($playerId);
            
            return [
                'player_id' => $playerId,
                'username' => $playerData['nickname'] ?? null,
                'avatar' => $playerData['avatar'] ?? null,
                'country' => $playerData['country'] ?? null,
                'cs2_level' => $cs2Stats['level'] ?? null,
                'cs2_elo' => $cs2Stats['elo'] ?? null,
                'cs2_rank' => $cs2Stats['rank'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Error fetching Faceit player data', [
                'player_id' => $playerId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Получить статистику CS2
     */
    private function getCs2Stats(string $playerId): ?array
    {
        try {
            $apiKey = config('services.faceit.api_key');
            
            if (!$apiKey) {
                return null;
            }
            
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                ])
                ->get(self::API_BASE_URL . "/players/{$playerId}/games/cs2");
            
            if ($response->successful()) {
                $data = $response->json();
                
                return [
                    'level' => $data['skill_level'] ?? null,
                    'elo' => $data['faceit_elo'] ?? null,
                    'rank' => $data['faceit_elo'] ?? null, // Можно добавить маппинг ELO -> ранг
                ];
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Error fetching CS2 stats', [
                'player_id' => $playerId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
