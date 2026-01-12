<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class TelegramService
{
    private Client $client;
    private string $botToken;
    private string $apiUrl;
    
    // Rate limiting: Telegram позволяет 30 сообщений в секунду
    private const RATE_LIMIT_REQUESTS = 30;
    private const RATE_LIMIT_WINDOW = 1; // секунд

    public function __construct()
    {
        $this->botToken = config('telegram.bot_token');
        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}";
        $this->client = new Client([
            'timeout' => 30,
            'verify' => true,
        ]);
    }

    /**
     * Проверить и применить rate limiting
     */
    private function checkRateLimit(string $endpoint): void
    {
        $key = "telegram_rate_limit_{$endpoint}";
        $current = Cache::get($key, 0);
        
        if ($current >= self::RATE_LIMIT_REQUESTS) {
            $ttl = Cache::get("{$key}_ttl", self::RATE_LIMIT_WINDOW);
            sleep(1); // Ждем 1 секунду
            Cache::forget($key);
            Cache::forget("{$key}_ttl");
        }
        
        Cache::put($key, $current + 1, now()->addSeconds(self::RATE_LIMIT_WINDOW));
        Cache::put("{$key}_ttl", self::RATE_LIMIT_WINDOW, now()->addSeconds(self::RATE_LIMIT_WINDOW));
    }

    /**
     * Выполнить запрос к Telegram API с обработкой rate limiting
     */
    private function makeRequest(string $method, array $params, int $retries = 3): ?array
    {
        $endpoint = explode('?', parse_url($method, PHP_URL_PATH))[0];
        $this->checkRateLimit($endpoint);
        
        for ($attempt = 0; $attempt < $retries; $attempt++) {
            try {
                $startTime = microtime(true);
                
                $response = $this->client->post("{$this->apiUrl}/{$method}", [
                    'json' => $params,
                ]);

                $responseTime = (microtime(true) - $startTime) * 1000; // в мс
                
                if ($responseTime > 1000) {
                    Log::warning("Slow Telegram API response", [
                        'method' => $method,
                        'response_time_ms' => $responseTime,
                    ]);
                }

                $body = $response->getBody()->getContents();
                $result = json_decode($body, true);
                
                // Проверка на ошибку декодирования JSON
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::error('Telegram API JSON decode error', [
                        'method' => $method,
                        'body' => $body,
                        'json_error' => json_last_error_msg(),
                    ]);
                    if ($attempt < $retries - 1) {
                        continue;
                    }
                    return null;
                }
                
                if (is_array($result) && isset($result['ok']) && $result['ok'] === true) {
                    $responseResult = $result['result'] ?? [];
                    // Убеждаемся, что возвращаем массив, а не bool
                    return is_array($responseResult) ? $responseResult : [];
                }

                // Обработка ошибки 429 (Too Many Requests)
                if (is_array($result) && isset($result['error_code']) && $result['error_code'] == 429) {
                    $retryAfter = $result['parameters']['retry_after'] ?? (2 ** $attempt);
                    Log::warning("Rate limit exceeded", [
                        'method' => $method,
                        'retry_after' => $retryAfter,
                        'attempt' => $attempt + 1,
                    ]);
                    
                    if ($attempt < $retries - 1) {
                        sleep($retryAfter);
                        continue;
                    }
                }

                Log::error('Telegram API error', [
                    'method' => $method,
                    'response' => $result,
                ]);
                
                return null;

            } catch (\Exception $e) {
                Log::error('Telegram API request error', [
                    'method' => $method,
                    'error' => $e->getMessage(),
                    'attempt' => $attempt + 1,
                ]);
                
                if ($attempt < $retries - 1) {
                    sleep(2 ** $attempt); // Exponential backoff
                }
            }
        }
        
        return null;
    }

    /**
     * Отправить сообщение в чат
     */
    public function sendMessage(int $chatId, string $text, array $options = []): ?array
    {
        $params = array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ], $options);

        return $this->makeRequest('sendMessage', $params);
    }

    /**
     * Отправить изображение с подписью в чат
     */
    public function sendPhoto(int $chatId, string $photo, string $caption = '', array $options = []): ?array
    {
        // Проверить, является ли это локальным файлом (URL содержит storage/questions/)
        if (strpos($photo, 'storage/questions/') !== false) {
            // Извлечь путь к файлу из URL
            $parsedUrl = parse_url($photo);
            $filePath = $parsedUrl['path'] ?? $photo;
            
            // Убрать начальный слеш и storage/, добавить storage/app/public/
            $filePath = ltrim($filePath, '/');
            if (strpos($filePath, 'storage/questions/') === 0) {
                $filePath = 'storage/app/public/' . substr($filePath, 8); // убрать 'storage/'
            }
            
            $fullPath = base_path($filePath);
            
            if (file_exists($fullPath)) {
                // Отправить файл через multipart/form-data
                return $this->sendPhotoFile($chatId, $fullPath, $caption, $options);
            }
        }
        
        // Если это URL или file_id
        $params = array_merge([
            'chat_id' => $chatId,
            'photo' => $photo, // URL или file_id
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ], $options);

        return $this->makeRequest('sendPhoto', $params);
    }

    /**
     * Отправить изображение как файл (multipart/form-data)
     */
    private function sendPhotoFile(int $chatId, string $filePath, string $caption = '', array $options = []): ?array
    {
        try {
            $endpoint = 'sendPhoto';
            $this->checkRateLimit($endpoint);
            
            $params = array_merge([
                'chat_id' => $chatId,
                'caption' => $caption,
                'parse_mode' => 'HTML',
            ], $options);
            
            $multipart = [];
            foreach ($params as $key => $value) {
                $multipart[] = [
                    'name' => $key,
                    'contents' => $value,
                ];
            }
            
            // Добавить файл
            $multipart[] = [
                'name' => 'photo',
                'contents' => fopen($filePath, 'r'),
                'filename' => basename($filePath),
            ];
            
            $response = $this->client->post("{$this->apiUrl}/{$endpoint}", [
                'multipart' => $multipart,
            ]);
            
            $result = json_decode($response->getBody()->getContents(), true);
            
            if (is_array($result) && isset($result['ok']) && $result['ok']) {
                return $result['result'] ?? [];
            }
            
            Log::error('Telegram API error sending photo file', [
                'method' => $endpoint,
                'response' => $result,
            ]);
            
            return null;
        } catch (\Exception $e) {
            Log::error('Telegram API request error sending photo file', [
                'method' => 'sendPhoto',
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Отправить сообщение с inline кнопками
     */
    public function sendMessageWithButtons(int $chatId, string $text, array $buttons, array $options = []): ?array
    {
        $inlineKeyboard = [];
        
        foreach ($buttons as $row) {
            $inlineKeyboard[] = array_map(function($button) {
                return [
                    'text' => $button['text'],
                    'callback_data' => $button['callback_data'] ?? '',
                ];
            }, $row);
        }

        $params = array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => [
                'inline_keyboard' => $inlineKeyboard,
            ],
        ], $options);

        return $this->makeRequest('sendMessage', $params);
    }

    /**
     * Ответить на callback query (нажатие на кнопку)
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): ?array
    {
        $params = [
            'callback_query_id' => $callbackQueryId,
        ];

        if ($text !== null) {
            $params['text'] = $text;
            $params['show_alert'] = $showAlert;
        }

        return $this->makeRequest('answerCallbackQuery', $params);
    }

    /**
     * Редактировать сообщение с кнопками
     */
    public function editMessageReplyMarkup(int $chatId, int $messageId, array $buttons): ?array
    {
        $inlineKeyboard = [];
        
        foreach ($buttons as $row) {
            $inlineKeyboard[] = array_map(function($button) {
                return [
                    'text' => $button['text'],
                    'callback_data' => $button['callback_data'] ?? '',
                ];
            }, $row);
        }

        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => [
                'inline_keyboard' => $inlineKeyboard,
            ],
        ];

        return $this->makeRequest('editMessageReplyMarkup', $params);
    }

    /**
     * Редактировать сообщение
     */
    public function editMessageText(int $chatId, int $messageId, string $text, array $options = []): ?array
    {
        $params = array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ], $options);

        return $this->makeRequest('editMessageText', $params);
    }

    /**
     * Получить информацию о члене чата
     */
    public function getChatMember(int $chatId, int $userId): ?array
    {
        $params = [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ];

        return $this->makeRequest('getChatMember', $params);
    }

    /**
     * Получить информацию о чате
     */
    public function getChat(int $chatId): ?array
    {
        $params = [
            'chat_id' => $chatId,
        ];

        return $this->makeRequest('getChat', $params);
    }

    /**
     * Проверить, является ли бот администратором чата
     */
    public function isBotAdmin(int $chatId): bool
    {
        try {
            $botInfo = $this->getMe();
            if (!$botInfo) {
                return false;
            }

            $botId = $botInfo['id'];
            $member = $this->getChatMember($chatId, $botId);

            if (!$member) {
                return false;
            }

            $status = $member['status'] ?? null;
            return in_array($status, ['administrator', 'creator']);
        } catch (\Exception $e) {
            Log::error('Check bot admin error', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Установить вебхук
     */
    public function setWebhook(string $url): bool
    {
        $params = [
            'url' => $url,
            'allowed_updates' => ['message', 'callback_query'],
        ];

        $result = $this->makeRequest('setWebhook', $params);
        return $result !== null;
    }

    /**
     * Получить информацию о боте
     */
    public function getMe(): ?array
    {
        return $this->makeRequest('getMe', []);
    }
}
