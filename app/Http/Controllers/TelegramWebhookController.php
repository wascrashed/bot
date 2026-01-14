<?php

namespace App\Http\Controllers;

use App\Models\ActiveQuiz;
use App\Models\Meme;
use App\Models\MemeSuggestion;
use App\Models\UserProfile;
use App\Services\QuizService;
use App\Services\TelegramService;
use App\Services\DotabuffService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class TelegramWebhookController extends Controller
{
    private QuizService $quizService;

    public function __construct(QuizService $quizService)
    {
        $this->quizService = $quizService;
    }

    /**
     * Обработка входящих обновлений от Telegram
     */
    public function handle(Request $request)
    {
        // ВАЖНО: Логируем ВСЕ входящие обновления для диагностики на проде
        try {
            $update = $request->all();
            
            // Логировать ВСЕ обновления (даже пустые) для диагностики
            try {
                $updateType = 'unknown';
                $chatId = null;
                
                if (isset($update['message'])) {
                    $updateType = 'message';
                    $chatId = $update['message']['chat']['id'] ?? null;
                } elseif (isset($update['callback_query'])) {
                    $updateType = 'callback_query';
                    $chatId = $update['callback_query']['message']['chat']['id'] ?? null;
                } elseif (isset($update['edited_message'])) {
                    $updateType = 'edited_message';
                    $chatId = $update['edited_message']['chat']['id'] ?? null;
                } elseif (!empty($update)) {
                    $updateType = 'other';
                    $updateType .= ' (' . implode(', ', array_keys($update)) . ')';
                }
                
                Log::info('🔵 WEBHOOK UPDATE RECEIVED', [
                    'type' => $updateType,
                    'chat_id' => $chatId,
                    'has_message' => isset($update['message']),
                    'has_callback' => isset($update['callback_query']),
                    'update_keys' => array_keys($update),
                ]);
            } catch (\Exception $logError) {
                // Если логирование не работает, попробуем записать в файл напрямую
                try {
                    $logFile = storage_path('logs/webhook_debug.log');
                    file_put_contents($logFile, date('Y-m-d H:i:s') . ' - Webhook received but Log::info failed: ' . $logError->getMessage() . "\n", FILE_APPEND);
                } catch (\Exception $fileError) {
                    // Игнорируем ошибки записи в файл
                }
            }

            // Обработка callback_query (нажатия на кнопки)
            if (isset($update['callback_query'])) {
                $this->handleCallbackQuery($update['callback_query']);
            }
            
            // Обработка сообщений
            if (isset($update['message'])) {
                $this->handleMessage($update['message']);
            }

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            // Критически важно логировать ошибку перед возвратом 500
            try {
                Log::error('❌ WEBHOOK ERROR 500', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
            } catch (\Exception $logError) {
                // Если логирование не работает, записать в файл напрямую
                try {
                    $logFile = storage_path('logs/webhook_errors.log');
                    $errorMsg = date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . 
                                " in " . $e->getFile() . ":" . $e->getLine() . "\n";
                    file_put_contents($logFile, $errorMsg, FILE_APPEND);
                } catch (\Exception $fileError) {
                    // Игнорируем ошибки записи в файл
                }
            }
            
            // Возвращаем 500, но с минимальной информацией для безопасности
            return response()->json([
                'ok' => false, 
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Обработка входящего сообщения
     */
    private function handleMessage(array $message): void
    {
        // ВАЖНО: Логируем получение сообщения для диагностики
        try {
            Log::info('📨 handleMessage called', [
                'has_chat' => isset($message['chat']),
                'chat_type' => $message['chat']['type'] ?? null,
                'has_text' => isset($message['text']),
                'text_preview' => isset($message['text']) ? substr($message['text'], 0, 50) : null,
            ]);
        } catch (\Exception $logError) {
            // Игнорируем ошибки логирования
        }
        
        // Проверить, что это сообщение из группы или супергруппы
        $chat = $message['chat'] ?? null;
        if (!$chat) {
            try {
                Log::warning('❌ handleMessage: chat is null');
            } catch (\Exception $logError) {
                // Игнорируем ошибки логирования
            }
            return;
        }

        $chatType = $chat['type'] ?? null;
        
        // Обработать личные сообщения для сохранения chat_id владельца
        if ($chatType === 'private') {
            $from = $message['from'] ?? null;
            if ($from && !($from['is_bot'] ?? false)) {
                $username = $from['username'] ?? null;
                if ($username) {
                    $telegramService = new \App\Services\TelegramService();
                    $telegramService->saveOwnerChatId($chat['id'], $username);
                }
                
                // Обработка команд в личном чате
                $text = trim($message['text'] ?? '');
                $userId = $from['id'] ?? null;
                
                // Сбрасываем флаг предложения мема при любой команде (кроме /suggest_mem)
                if (!empty($text) && preg_match('/^\/(\w+)(@\w+)?\s*$/i', $text)) {
                    $command = strtolower(trim(explode('@', $text)[0], '/'));
                    if ($command !== 'suggest_mem' && $command !== 'предложить_мем' && $command !== 'предложить') {
                        if ($userId && \Illuminate\Support\Facades\Cache::has("meme_suggestion_active_{$userId}")) {
                            \Illuminate\Support\Facades\Cache::forget("meme_suggestion_active_{$userId}");
                            Log::info('🔄 Meme suggestion flag cleared due to command', [
                                'user_id' => $userId,
                                'command' => $command,
                            ]);
                        }
                    }
                }
                
                // Команда /chatid или /id
                if (!empty($text) && preg_match('/^\/(chatid|id|getid)(@\w+)?\s*$/i', $text)) {
                    $telegramService = new \App\Services\TelegramService();
                    $telegramService->sendMessage(
                        $chat['id'],
                        "🆔 <b>Ваш Chat ID:</b> <code>{$chat['id']}</code>\n\n💡 <i>Это ваш личный Chat ID</i>",
                        ['parse_mode' => 'HTML']
                    );
                }
                
                // Команда /status в личном чате (показываем общую статистику по всем чатам)
                if (!empty($text) && preg_match('/^\/(status|статус)(@\w+)?\s*$/i', $text)) {
                    $this->handleStatusCommandPrivate($chat['id'], $from);
                }
                
                // Команда /mem в личном чате
                if (!empty($text) && preg_match('/^\/(mem|мем)(@\w+)?\s*$/i', $text)) {
                    $this->handleMemCommand($chat['id'], 'private');
                }
                
                // Команда /suggest_mem в личном чате
                if (!empty($text) && preg_match('/^\/(suggest_mem|предложить_мем|предложить)(@\w+)?\s*$/i', $text)) {
                    try {
                        Log::info('📤 /suggest_mem command in private chat', [
                            'chat_id' => $chat['id'],
                            'user_id' => $from['id'] ?? null,
                        ]);
                    } catch (\Exception $logError) {
                        // Игнорируем ошибки логирования
                    }
                    $this->handleSuggestMemCommand($chat['id'], $from);
                }
                
                // Команда /profile в личном чате
                if (!empty($text) && preg_match('/^\/(profile|профиль)(@\w+)?\s*$/i', $text)) {
                    $this->handleProfileCommand($chat['id'], $from);
                }
                
                // Команда /top в личном чате
                if (!empty($text) && preg_match('/^\/(top|топ|лидеры)(@\w+)?\s*$/i', $text)) {
                    $this->handleTopCommand($chat['id'], $from);
                }
                
                // Обработка ввода данных для профиля (если пользователь ожидает ввода)
                $userId = $from['id'] ?? null;
                if ($userId && !empty($text)) {
                    // Проверяем, ожидает ли пользователь ввода ника
                    if (\Illuminate\Support\Facades\Cache::has("profile_waiting_nickname_{$userId}")) {
                        \Illuminate\Support\Facades\Cache::forget("profile_waiting_nickname_{$userId}");
                        $profile = UserProfile::getOrCreate($userId);
                        $profile->game_nickname = $text;
                        $profile->save();
                        
                        $telegramService = new \App\Services\TelegramService();
                        $telegramService->sendMessage(
                            $chat['id'],
                            "✅ Ник в игре установлен: <b>{$text}</b>",
                            ['parse_mode' => 'HTML']
                        );
                        // Показываем обновленный профиль
                        $this->handleProfileCommand($chat['id'], $from);
                        return;
                    }
                    
                    // Проверяем, ожидает ли пользователь ввода Dotabuff URL
                    if (\Illuminate\Support\Facades\Cache::has("profile_waiting_dotabuff_{$userId}")) {
                        \Illuminate\Support\Facades\Cache::forget("profile_waiting_dotabuff_{$userId}");
                        
                        $dotabuffService = new \App\Services\DotabuffService();
                        if (!$dotabuffService->validateUrl($text)) {
                            $telegramService = new \App\Services\TelegramService();
                            $telegramService->sendMessage(
                                $chat['id'],
                                "❌ Неверный URL Dotabuff.\n\nФормат: https://www.dotabuff.com/players/123456789"
                            );
                            return;
                        }
                        
                        $profile = UserProfile::getOrCreate($userId);
                        $profile->dotabuff_url = $text;
                        
                        // Синхронизируем данные сразу
                        $dotabuffData = $dotabuffService->getPlayerData($text);
                        if ($dotabuffData) {
                            $profile->dotabuff_data = $dotabuffData;
                            $profile->dotabuff_last_sync = now();
                        }
                        $profile->save();
                        
                        $telegramService = new \App\Services\TelegramService();
                        $telegramService->sendMessage(
                            $chat['id'],
                            "✅ Ссылка на Dotabuff установлена и синхронизирована!",
                            ['parse_mode' => 'HTML']
                        );
                        // Показываем обновленный профиль
                        $this->handleProfileCommand($chat['id'], $from);
                        return;
                    }
                }
                
                // Обработка предложенных мемов (фото/видео) в личном чате
                if (isset($message['photo']) || isset($message['video'])) {
                    $this->handleMemeSuggestion($message, $from, $chat['id']);
                }
            }
            return; // Не обрабатываем личные сообщения дальше
        }
        
        if (!in_array($chatType, ['group', 'supergroup'])) {
            return; // Игнорируем каналы
        }

        $chatId = $chat['id'];
        
        // ВАЖНО: Определить $from ДО использования
        $from = $message['from'] ?? null;
        
        // Обработка команд
        $text = trim($message['text'] ?? '');
        
        // Сбрасываем флаг предложения мема при любой команде (кроме /suggest_mem)
        if (!empty($text) && preg_match('/^\/(\w+)(@\w+)?\s*$/i', $text) && $from) {
            $userId = $from['id'] ?? null;
            $command = strtolower(trim(explode('@', $text)[0], '/'));
            if ($command !== 'suggest_mem' && $command !== 'предложить_мем' && $command !== 'предложить') {
                if ($userId && \Illuminate\Support\Facades\Cache::has("meme_suggestion_active_{$userId}")) {
                    \Illuminate\Support\Facades\Cache::forget("meme_suggestion_active_{$userId}");
                    Log::info('🔄 Meme suggestion flag cleared due to command in group', [
                        'user_id' => $userId,
                        'command' => $command,
                        'chat_id' => $chatId,
                    ]);
                }
            }
        }
        
        // Команда /chatid или /id
        if (!empty($text) && preg_match('/^\/(chatid|id|getid)(@\w+)?\s*$/i', $text)) {
            $telegramService = new \App\Services\TelegramService();
            $chatTitle = $chat['title'] ?? 'этой группы';
            $telegramService->sendMessage(
                $chatId,
                "🆔 <b>Chat ID {$chatTitle}:</b> <code>{$chatId}</code>\n\n💡 <i>Используйте этот ID для восстановления чата в админке</i>",
                ['parse_mode' => 'HTML']
            );
            return; // Не обрабатываем дальше
        }
        
        // Команда /status
        if (!empty($text) && preg_match('/^\/(status|статус)(@\w+)?\s*$/i', $text)) {
            try {
                Log::info('🔵 /status command received in group', [
                    'chat_id' => $chatId,
                    'user_id' => $from['id'] ?? null,
                    'username' => $from['username'] ?? null,
                ]);
            } catch (\Exception $logError) {
                // Игнорируем ошибки логирования
            }
            $this->handleStatusCommand($chatId, $from, $chat);
            return; // Не обрабатываем дальше
        }
        
        // Команда /mem
        if (!empty($text) && preg_match('/^\/(mem|мем)(@\w+)?\s*$/i', $text)) {
            $this->handleMemCommand($chatId, $chatType);
            return; // Не обрабатываем дальше
        }
        
        // Команда /suggest_mem или /предложить_мем
        if (!empty($text) && preg_match('/^\/(suggest_mem|предложить_мем|предложить)(@\w+)?\s*$/i', $text)) {
            $this->handleSuggestMemCommand($chatId, $from);
            return; // Не обрабатываем дальше
        }
        
        // Команда /profile в группе
        if (!empty($text) && preg_match('/^\/(profile|профиль)(@\w+)?\s*$/i', $text)) {
            $this->handleProfileCommand($chatId, $from);
            return; // Не обрабатываем дальше
        }
        
        // Команда /top в группе
        if (!empty($text) && preg_match('/^\/(top|топ|лидеры)(@\w+)?\s*$/i', $text)) {
            $this->handleTopCommand($chatId, $from);
            return; // Не обрабатываем дальше
        }
        
        // Обработка события добавления бота в группу
        if (isset($message['new_chat_member']) || isset($message['new_chat_members'])) {
            $newMembers = $message['new_chat_members'] ?? [$message['new_chat_member']];
            $telegramService = new \App\Services\TelegramService();
            $botInfo = $telegramService->getMe();
            
            if ($botInfo) {
                $botId = $botInfo['id'];
                foreach ($newMembers as $member) {
                    if (isset($member['id']) && $member['id'] == $botId) {
                        // Бот добавлен в группу - зарегистрировать чат
                        \App\Models\ChatStatistics::getOrCreate($chatId, $chatType, $chat['title'] ?? null);
                        Log::info("Bot added to chat", [
                            'chat_id' => $chatId,
                            'chat_type' => $chatType,
                            'chat_title' => $chat['title'] ?? null,
                        ]);
                        return;
                    }
                }
            }
        }
        
        // Обработка события удаления бота из группы
        if (isset($message['left_chat_member'])) {
            $leftMember = $message['left_chat_member'];
            $telegramService = new \App\Services\TelegramService();
            $botInfo = $telegramService->getMe();
            
            if ($botInfo && isset($leftMember['id']) && $leftMember['id'] == $botInfo['id']) {
                // Бот удален из группы - удалить чат из БД
                $this->removeChatFromDatabase($chatId);
                Log::info("Bot removed from chat", [
                    'chat_id' => $chatId,
                    'chat_type' => $chatType,
                    'chat_title' => $chat['title'] ?? null,
                ]);
                return;
            }
        }
        
        // $from уже определен выше для команд, но переопределяем для остальной логики
        if (!isset($from)) {
            $from = $message['from'] ?? null;
        }
        
        // Игнорировать сообщения от ботов
        if ($from && ($from['is_bot'] ?? false)) {
            return;
        }

        // ВАЖНО: Зарегистрировать чат при ЛЮБОМ сообщении из группы
        // Это гарантирует, что чат будет в базе данных, даже если бот был добавлен до реализации этой логики
        try {
            \App\Models\ChatStatistics::getOrCreate($chatId, $chatType, $chat['title'] ?? null);
        } catch (\Exception $e) {
            // Игнорируем ошибки регистрации, чтобы не прерывать обработку
            try {
                Log::warning('Failed to register chat', [
                    'chat_id' => $chatId,
                    'error' => $e->getMessage(),
                ]);
            } catch (\Exception $logError) {
                // Игнорируем ошибки логирования
            }
        }

        // Обработка предложенных мемов (фото/видео от пользователей)
        // Фото/видео не могут быть ответом на викторину, так что обрабатываем их как предложения мемов
        if (isset($message['photo']) || isset($message['video'])) {
            $this->handleMemeSuggestion($message, $from, $chatId);
            // НЕ возвращаемся, продолжаем обработку (на случай если нужно что-то еще)
        }
        
        // Логировать все сообщения из групп для диагностики
        try {
            Log::info('Message received in group', [
                'chat_id' => $chatId,
                'chat_type' => $chatType,
                'has_text' => !empty($message['text'] ?? ''),
                'has_photo' => isset($message['photo']),
                'has_video' => isset($message['video']),
                'text' => $message['text'] ?? null,
            ]);
        } catch (\Exception $logError) {
            // Игнорируем ошибки логирования
        }

        // Найти активную викторину для этого чата
        // Сначала найти все активные викторины для этого чата
        $activeQuizzes = ActiveQuiz::where('chat_id', $chatId)
            ->where('is_active', true)
            ->get();
        
        // Логировать количество найденных викторин
        Log::info('Searching for active quizzes', [
            'chat_id' => $chatId,
            'found_count' => $activeQuizzes->count(),
            'quiz_ids' => $activeQuizzes->pluck('id')->toArray(),
            'has_text' => !empty($message['text'] ?? ''),
            'text_preview' => substr($message['text'] ?? '', 0, 50),
        ]);
        
        $activeQuiz = null;
        $now = \Carbon\Carbon::now('UTC');
        
        foreach ($activeQuizzes as $quiz) {
            // Прочитать сырые значения из БД напрямую для точности
            $rawData = DB::table('active_quizzes')
                ->where('id', $quiz->id)
                ->first(['started_at', 'expires_at']);
            
            // Создать Carbon объекты из сырых строк, явно указав UTC
            $startedAt = Carbon::createFromFormat('Y-m-d H:i:s', $rawData->started_at, 'UTC');
            $expiresAt = Carbon::createFromFormat('Y-m-d H:i:s', $rawData->expires_at, 'UTC');
            
            // КРИТИЧЕСКАЯ ПРОВЕРКА: если expires_at раньше started_at, исправить
            if ($expiresAt->lessThanOrEqualTo($startedAt)) {
                Log::error('CRITICAL: Found quiz with invalid expires_at, fixing...', [
                    'active_quiz_id' => $quiz->id,
                    'started_at' => $startedAt->format('Y-m-d H:i:s T'),
                    'expires_at_before' => $expiresAt->format('Y-m-d H:i:s T'),
                ]);
                
                // Пересчитать expires_at правильно
                $correctExpiresAt = $startedAt->copy()->addSeconds(20);
                DB::table('active_quizzes')
                    ->where('id', $quiz->id)
                    ->update(['expires_at' => $correctExpiresAt->format('Y-m-d H:i:s')]);
                
                // Перечитать из БД
                $rawData = DB::table('active_quizzes')
                    ->where('id', $quiz->id)
                    ->first(['started_at', 'expires_at']);
                $startedAt = Carbon::createFromFormat('Y-m-d H:i:s', $rawData->started_at, 'UTC');
                $expiresAt = Carbon::createFromFormat('Y-m-d H:i:s', $rawData->expires_at, 'UTC');
                
                Log::info('Fixed quiz expires_at', [
                    'active_quiz_id' => $quiz->id,
                    'expires_at_after' => $expiresAt->format('Y-m-d H:i:s T'),
                    'time_diff_seconds' => $expiresAt->diffInSeconds($startedAt),
                ]);
            }
            
            // Проверить, что викторина еще не истекла
            // Использовать прямое сравнение Carbon объектов для правильной работы с часовыми поясами
            // ВАЖНО: использовать greaterThanOrEqualTo вместо isFuture, чтобы включить момент истечения
            $isNotExpired = $expiresAt->greaterThanOrEqualTo($now);
            
            // Логировать для диагностики (используем info вместо debug для гарантии записи)
            Log::info('Checking quiz expiration', [
                'active_quiz_id' => $quiz->id,
                'started_at' => $startedAt->format('Y-m-d H:i:s T'),
                'expires_at' => $expiresAt->format('Y-m-d H:i:s T'),
                'now' => $now->format('Y-m-d H:i:s T'),
                'is_not_expired' => $isNotExpired,
                'time_diff_seconds' => $now->diffInSeconds($expiresAt, false),
            ]);
            
            if ($isNotExpired) {
                // Обновить объект quiz для дальнейшего использования
                $quiz->started_at = $startedAt;
                $quiz->expires_at = $expiresAt;
                $activeQuiz = $quiz;
                Log::info('✅ Active quiz found for message - WILL PROCESS ANSWER', [
                    'active_quiz_id' => $quiz->id,
                    'chat_id' => $chatId,
                    'started_at' => $startedAt->format('Y-m-d H:i:s T'),
                    'expires_at' => $expiresAt->format('Y-m-d H:i:s T'),
                    'now' => $now->format('Y-m-d H:i:s T'),
                    'time_remaining_seconds' => max(0, $now->diffInSeconds($expiresAt, false)),
                ]);
                break; // Нашли активную викторину
            } else {
                Log::info('❌ Quiz expired, skipping', [
                    'active_quiz_id' => $quiz->id,
                    'expires_at' => $expiresAt->format('Y-m-d H:i:s T'),
                    'now' => $now->format('Y-m-d H:i:s T'),
                    'time_past_seconds' => abs($now->diffInSeconds($expiresAt, false)),
                ]);
            }
        }
        
        // Логировать результат поиска викторины
        if ($activeQuiz) {
            Log::info('Active quiz found for message', [
                'active_quiz_id' => $activeQuiz->id,
                'chat_id' => $chatId,
                'started_at' => $activeQuiz->started_at->format('Y-m-d H:i:s'),
                'expires_at' => $activeQuiz->expires_at->format('Y-m-d H:i:s'),
                'now' => now()->format('Y-m-d H:i:s'),
            ]);
        } else {
            // Логировать детально, почему викторина не найдена
            $allQuizzes = ActiveQuiz::where('chat_id', $chatId)->latest()->take(3)->get();
            $quizInfo = [];
            foreach ($allQuizzes as $q) {
                $quizInfo[] = [
                    'id' => $q->id,
                    'is_active' => $q->is_active,
                    'started_at' => $q->started_at->format('Y-m-d H:i:s'),
                    'expires_at' => $q->expires_at->format('Y-m-d H:i:s'),
                    'is_expired' => $q->isExpired(),
                    'expires_before_start' => $q->expires_at->lessThan($q->started_at),
                ];
            }
            
            try {
                Log::info('No active quiz found for message', [
                    'chat_id' => $chatId,
                    'has_text' => !empty($message['text'] ?? ''),
                    'now' => $now->format('Y-m-d H:i:s T'),
                    'recent_quizzes' => $quizInfo,
                ]);
            } catch (\Exception $logError) {
                // Игнорируем ошибки логирования, чтобы не прерывать выполнение
            }
        }

        if ($activeQuiz) {
            // Есть активная викторина - обработать ответ
            $text = $message['text'] ?? '';
            if (!empty($text)) {
                $userId = $from['id'] ?? 0;
                $username = $from['username'] ?? null;
                $firstName = $from['first_name'] ?? '';

                // Логировать обработку текстового ответа
                Log::info('Processing text answer', [
                    'active_quiz_id' => $activeQuiz->id,
                    'chat_id' => $chatId,
                    'user_id' => $userId,
                    'answer_text' => $text,
                    'quiz_started_at' => $activeQuiz->started_at->format('Y-m-d H:i:s'),
                    'quiz_expires_at' => $activeQuiz->expires_at->format('Y-m-d H:i:s'),
                ]);

                try {
                    // Передать message_id и chat_id для уведомлений
                    $messageId = $message['message_id'] ?? null;
                    $this->quizService->processAnswer(
                        $activeQuiz->id,
                        $userId,
                        $username,
                        $firstName,
                        $text,
                        $messageId,
                        $chatId
                    );
                    Log::info('Answer processed successfully', [
                        'active_quiz_id' => $activeQuiz->id,
                        'user_id' => $userId,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error processing answer', [
                        'active_quiz_id' => $activeQuiz->id,
                        'user_id' => $userId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            } else {
                // Пропускаем сообщения без текста (стикеры, фото и т.д.)
                // Не логируем, чтобы не засорять логи
            }
        } else {
            // Нет активной викторины - чат уже зарегистрирован выше при получении сообщения
            // Здесь ничего не делаем
        }
    }

    /**
     * Обработка callback_query (нажатие на кнопки)
     */
    private function handleCallbackQuery(array $callbackQuery): void
    {
        // ВАЖНО: Логируем ВСЕ callback query для диагностики
        try {
            Log::info('🔵 CALLBACK QUERY RECEIVED', [
                'has_from' => isset($callbackQuery['from']),
                'has_message' => isset($callbackQuery['message']),
                'has_data' => isset($callbackQuery['data']),
                'has_id' => isset($callbackQuery['id']),
                'data' => $callbackQuery['data'] ?? null,
                'callback_query_id' => $callbackQuery['id'] ?? null,
            ]);
        } catch (\Exception $logError) {
            // Игнорируем ошибки логирования, чтобы не прерывать выполнение
        }

        $from = $callbackQuery['from'] ?? null;
        $message = $callbackQuery['message'] ?? null;
        $data = $callbackQuery['data'] ?? '';
        $callbackQueryId = $callbackQuery['id'] ?? null;

        if (!$from || !$message || !$data || !$callbackQueryId) {
            try {
                Log::warning('❌ Callback query missing required fields', [
                    'has_from' => !empty($from),
                    'has_message' => !empty($message),
                    'has_data' => !empty($data),
                    'has_callback_query_id' => !empty($callbackQueryId),
                ]);
            } catch (\Exception $logError) {
                // Игнорируем ошибки логирования
            }
            return;
        }

        // Игнорировать нажатия от ботов
        if ($from['is_bot'] ?? false) {
            try {
                Log::info('⚠️ Callback query from bot, ignoring');
            } catch (\Exception $logError) {
                // Игнорируем ошибки логирования
            }
            return;
        }

        $chat = $message['chat'] ?? null;
        if (!$chat) {
            try {
                Log::warning('❌ Callback query message has no chat');
            } catch (\Exception $logError) {
                // Игнорируем ошибки логирования
            }
            return;
        }

        $chatType = $chat['type'] ?? null;
        $chatId = $chat['id'] ?? null;
        
        // Сбрасываем флаг предложения мема при любом другом callback query
        // (кроме suggest_mem_button, который устанавливает флаг)
        if ($data !== 'suggest_mem_button' && isset($from['id'])) {
            $userId = $from['id'];
            $hadFlag = \Illuminate\Support\Facades\Cache::has("meme_suggestion_active_{$userId}");
            if ($hadFlag) {
                \Illuminate\Support\Facades\Cache::forget("meme_suggestion_active_{$userId}");
                Log::info('🔄 Meme suggestion flag cleared due to other callback', [
                    'user_id' => $userId,
                    'callback_data' => $data,
                ]);
            }
        }
        
        // Обработка кнопок профиля
        if (strpos($data, 'profile_') === 0) {
            $this->handleProfileCallback($chatId, $from, $data, $callbackQueryId);
            return;
        }
        
        // Обработка кнопки "Предложить мем"
        if ($data === 'suggest_mem_button') {
            try {
                $userId = $from['id'] ?? null;
                
                Log::info('📤 suggest_mem_button clicked', [
                    'chat_id' => $chatId,
                    'chat_type' => $chatType,
                    'user_id' => $userId,
                ]);
                
                if (!$userId) {
                    Log::warning('No user ID for suggest_mem_button');
                    return;
                }
                
                // Устанавливаем флаг, что пользователь готов предложить мем (TTL 10 минут)
                \Illuminate\Support\Facades\Cache::put(
                    "meme_suggestion_active_{$userId}",
                    true,
                    now()->addMinutes(10)
                );
                
                Log::info('✅ Meme suggestion flag set for user', ['user_id' => $userId]);
                
                $telegramService = new TelegramService();
                $telegramService->answerCallbackQuery($callbackQueryId, 'Отправьте фото или видео для предложения мема');
                
                $message = "📤 <b>Предложить мем</b>\n\n";
                $message .= "Отправьте фото или видео, и ваш мем будет отправлен на модерацию.\n\n";
                $message .= "💡 <i>Администратор рассмотрит ваше предложение и либо добавит мем, либо отклонит его.</i>\n\n";
                $message .= "⚠️ <i>Максимум 5 предложений в час</i>\n";
                $message .= "⏱️ <i>У вас есть 10 минут, чтобы отправить мем</i>";
                
                $result = $telegramService->sendMessage($chatId, $message, ['parse_mode' => 'HTML']);
                
                if ($result) {
                    Log::info('✅ suggest_mem instruction sent', [
                        'chat_id' => $chatId,
                        'message_id' => $result['message_id'] ?? null,
                    ]);
                } else {
                    Log::error('❌ Failed to send suggest_mem instruction', [
                        'chat_id' => $chatId,
                    ]);
                }
            } catch (\Exception $e) {
                try {
                    Log::error('❌ Error handling suggest_mem_button', [
                        'chat_id' => $chatId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                } catch (\Exception $logError) {
                    // Игнорируем ошибки логирования
                }
            }
            return;
        }
        
        if (!in_array($chatType, ['group', 'supergroup'])) {
            try {
                Log::info('⚠️ Callback query from non-group chat', ['chat_type' => $chatType]);
            } catch (\Exception $logError) {
                // Игнорируем ошибки логирования
            }
            return;
        }

        $chatId = $chat['id'];
        $userId = $from['id'] ?? 0;
        $username = $from['username'] ?? null;
        $firstName = $from['first_name'] ?? '';

        // Проверить, есть ли активная викторина в этом чате
        // Используем ту же логику поиска, что и для текстовых сообщений
        $activeQuizzes = ActiveQuiz::where('chat_id', $chatId)
            ->where('is_active', true)
            ->get();
        
        $activeQuiz = null;
        $now = Carbon::now('UTC');
        
        foreach ($activeQuizzes as $quiz) {
            // Прочитать сырые значения из БД напрямую для точности
            $rawData = DB::table('active_quizzes')
                ->where('id', $quiz->id)
                ->first(['started_at', 'expires_at']);
            
            $startedAt = Carbon::createFromFormat('Y-m-d H:i:s', $rawData->started_at, 'UTC');
            $expiresAt = Carbon::createFromFormat('Y-m-d H:i:s', $rawData->expires_at, 'UTC');
            
            // Проверить, что викторина еще не истекла
            $isNotExpired = $expiresAt->greaterThanOrEqualTo($now);
            
            if ($isNotExpired) {
                $quiz->started_at = $startedAt;
                $quiz->expires_at = $expiresAt;
                $activeQuiz = $quiz;
                break;
            }
        }

        if (!$activeQuiz) {
            // Отвечаем на callback, что викторина уже завершена
            $telegram = new \App\Services\TelegramService();
            $telegram->answerCallbackQuery($callbackQueryId, '⏰ Время на ответ истекло! Ваш ответ не зарегистрирован.', true);
            Log::warning('Callback query for inactive quiz', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'callback_data' => $data,
            ]);
            return;
        }

        // Логировать обработку callback
        try {
            Log::info('✅ Processing callback answer', [
                'active_quiz_id' => $activeQuiz->id,
                'chat_id' => $chatId,
                'user_id' => $userId,
                'username' => $username,
                'callback_data' => $data,
                'callback_query_id' => $callbackQueryId,
            ]);
        } catch (\Exception $logError) {
            // Игнорируем ошибки логирования
        }

        // Обработать ответ через callback
        // Передать message_id и chat_id для уведомлений
        $messageId = $message['message_id'] ?? null;
        $this->quizService->processAnswerWithCallback(
            $activeQuiz->id,
            $userId,
            $username,
            $firstName,
            $data, // callback_data для парсинга ответа
            $callbackQueryId, // callback_query_id для ответа на callback
            $messageId, // message_id для уведомлений
            $chatId // chat_id для отправки сообщений в группу
        );
    }
    
    /**
     * Удалить чат из базы данных
     */
    private function removeChatFromDatabase(int $chatId): void
    {
        $telegramService = new \App\Services\TelegramService();
        $telegramService->removeChatFromDatabase($chatId);
    }

    /**
     * Обработка команды /status
     */
    private function handleStatusCommand(int $chatId, ?array $from, array $chat): void
    {
        try {
            Log::info('🔵 handleStatusCommand called', [
                'chat_id' => $chatId,
                'has_from' => !empty($from),
                'user_id' => $from['id'] ?? null,
            ]);
        } catch (\Exception $logError) {
            // Игнорируем ошибки логирования
        }
        
        if (!$from) {
            try {
                Log::warning('❌ handleStatusCommand: from is null');
            } catch (\Exception $logError) {
                // Игнорируем ошибки логирования
            }
            return;
        }

        $userId = $from['id'] ?? 0;
        $username = $from['username'] ?? null;
        $firstName = $from['first_name'] ?? 'Пользователь';

        // Получить статистику пользователя в этом чате
        $userScore = \App\Models\UserScore::where('chat_id', $chatId)
            ->where('user_id', $userId)
            ->first();

        // Получить место пользователя в рейтинге
        $position = null;
        if ($userScore) {
            $position = \App\Models\UserScore::where('chat_id', $chatId)
                ->where(function($query) use ($userScore) {
                    $query->where('total_points', '>', $userScore->total_points)
                        ->orWhere(function($q) use ($userScore) {
                            $q->where('total_points', '=', $userScore->total_points)
                              ->where('correct_answers', '>', $userScore->correct_answers);
                        });
                })
                ->count() + 1;
        }

        // Получить общее количество участников в чате
        $totalParticipants = \App\Models\UserScore::where('chat_id', $chatId)->count();

        // Формировать сообщение
        $telegramService = new \App\Services\TelegramService();
        $chatTitle = $chat['title'] ?? 'этой группы';
        
        if ($userScore) {
            $accuracy = $userScore->total_answers > 0 
                ? round(($userScore->correct_answers / $userScore->total_answers) * 100, 1)
                : 0;
            
            $message = "📊 <b>Ваша статистика в {$chatTitle}</b>\n\n";
            $message .= "👤 <b>Пользователь:</b> " . ($firstName ?? $username ?? "User {$userId}") . "\n";
            $message .= "🏆 <b>Очки:</b> " . number_format($userScore->total_points) . "\n";
            $message .= "✅ <b>Правильных ответов:</b> " . number_format($userScore->correct_answers) . "\n";
            $message .= "📝 <b>Всего ответов:</b> " . number_format($userScore->total_answers) . "\n";
            $message .= "🎯 <b>Точность:</b> {$accuracy}%\n";
            $message .= "🥇 <b>Первых мест:</b> " . number_format($userScore->first_place_count) . "\n";
            
            if ($position && $totalParticipants > 0) {
                $message .= "📍 <b>Место в рейтинге:</b> {$position} из {$totalParticipants}\n";
            }
            
            if ($userScore->last_activity_at) {
                $lastActivity = $userScore->last_activity_at->diffForHumans();
                $message .= "⏰ <b>Последняя активность:</b> {$lastActivity}\n";
            }
        } else {
            $message = "📊 <b>Ваша статистика в {$chatTitle}</b>\n\n";
            $message .= "👤 <b>Пользователь:</b> " . ($firstName ?? $username ?? "User {$userId}") . "\n";
            $message .= "❌ <b>У вас пока нет статистики в этом чате.</b>\n\n";
            $message .= "💡 <i>Участвуйте в викторинах, чтобы заработать очки!</i>";
        }

        try {
            Log::info('📤 Sending /status response', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'has_user_score' => !empty($userScore),
                'message_length' => strlen($message),
            ]);
            
            $result = $telegramService->sendMessage(
                $chatId,
                $message,
                ['parse_mode' => 'HTML']
            );
            
            if ($result === false || $result === null || !is_array($result)) {
                try {
                    Log::error('❌ /status response: sendMessage returned false/null or invalid result', [
                        'chat_id' => $chatId,
                        'user_id' => $userId,
                        'result_type' => gettype($result),
                        'message' => 'Bot may not have permission to send messages in this group, or Telegram API error',
                    ]);
                } catch (\Exception $logError) {
                    // Игнорируем ошибки логирования
                }
                return;
            }
            
            try {
                Log::info('✅ /status response sent successfully', [
                    'chat_id' => $chatId,
                    'user_id' => $userId,
                    'has_message_id' => isset($result['message_id']),
                    'message_id' => $result['message_id'] ?? null,
                    'result_keys' => array_keys($result),
                ]);
            } catch (\Exception $logError) {
                // Игнорируем ошибки логирования
            }
        } catch (\Exception $e) {
            try {
                Log::error('❌ Failed to send /status response', [
                    'chat_id' => $chatId,
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            } catch (\Exception $logError) {
                // Игнорируем ошибки логирования
            }
        }
    }

    /**
     * Обработка команды /mem (отправка случайного мема)
     */
    private function handleMemCommand(int $chatId, string $chatType): void
    {
        try {
            $meme = Meme::getRandom();
            
            if (!$meme) {
                $telegramService = new TelegramService();
                
                // В группе - просто текст, в личном чате - кнопка
                if (in_array($chatType, ['group', 'supergroup'])) {
                    $telegramService->sendMessage(
                        $chatId,
                        "😔 Пока нет мемов в базе.\n\n💡 Добавьте мемы через админ-панель или предложите свой мем в боте!",
                        ['parse_mode' => 'HTML']
                    );
                } else {
                    // Личный чат - кнопка
                    $suggestButton = [
                        [
                            [
                                'text' => '📤 Предложить мем',
                                'callback_data' => 'suggest_mem_button'
                            ]
                        ]
                    ];
                    
                    $telegramService->sendMessageWithButtons(
                        $chatId,
                        "😔 Пока нет мемов в базе.\n\n💡 Добавьте мемы через админ-панель или предложите свой мем!",
                        $suggestButton
                    );
                }
                return;
            }
            
            $telegramService = new TelegramService();
            
            // Использовать file_id если есть (оптимизация)
            $media = $meme->file_id ?? $meme->media_url;
            
            // Подготовить caption (название мема или пустая строка)
            $caption = !empty($meme->title) ? $meme->title : '';
            
            $result = null;
            if ($meme->media_type === Meme::TYPE_VIDEO) {
                // Отправить видео
                $result = $telegramService->sendVideo($chatId, $media, $caption);
            } else {
                // Отправить фото
                $result = $telegramService->sendPhoto($chatId, $media, $caption);
            }
            
            // В группе - просто текст, в личном чате - кнопка
            if (in_array($chatType, ['group', 'supergroup'])) {
                // В группе - только текст без кнопки
                $telegramService->sendMessage(
                    $chatId,
                    "💡 <i>Вы можете предложить свой мем в боте</i>",
                    ['parse_mode' => 'HTML']
                );
            } else {
                // Личный чат - кнопка
                $suggestButton = [
                    [
                        [
                            'text' => '📤 Предложить свой мем',
                            'callback_data' => 'suggest_mem_button'
                        ]
                    ]
                ];
                
                $telegramService->sendMessageWithButtons(
                    $chatId,
                    "💡 <i>Хотите предложить свой мем? Нажмите кнопку ниже!</i>",
                    $suggestButton
                );
            }
            
            // Сохранить file_id если его еще нет и результат получен
            if (!$meme->file_id && $result) {
                $fileId = null;
                if (isset($result['photo'])) {
                    $photos = $result['photo'];
                    $largestPhoto = end($photos);
                    $fileId = $largestPhoto['file_id'] ?? null;
                } elseif (isset($result['video'])) {
                    $fileId = $result['video']['file_id'] ?? null;
                }
                
                if ($fileId) {
                    $meme->file_id = $fileId;
                    $meme->save();
                }
            }
        } catch (\Exception $e) {
            try {
                Log::error('Failed to send meme', [
                    'chat_id' => $chatId,
                    'error' => $e->getMessage(),
                ]);
            } catch (\Exception $logError) {
                // Игнорируем ошибки логирования
            }
        }
    }

    /**
     * Обработка команды /suggest_mem (предложить мем)
     */
    private function handleSuggestMemCommand(int $chatId, ?array $from): void
    {
        try {
            $userId = $from['id'] ?? null;
            
            Log::info('📤 handleSuggestMemCommand called', [
                'chat_id' => $chatId,
                'from_id' => $userId,
            ]);
            
            $telegramService = new TelegramService();
            
            $message = "📤 <b>Предложить мем</b>\n\n";
            $message .= "Нажмите кнопку ниже, чтобы предложить мем.\n\n";
            $message .= "💡 <i>Администратор рассмотрит ваше предложение и либо добавит мем, либо отклонит его.</i>\n\n";
            $message .= "⚠️ <i>Максимум 5 предложений в час</i>";
            
            // Кнопка для предложения мема
            $buttons = [
                [
                    [
                        'text' => '📤 Предложить мем',
                        'callback_data' => 'suggest_mem_button'
                    ]
                ]
            ];
            
            $result = $telegramService->sendMessageWithButtons($chatId, $message, $buttons);
            
            if ($result) {
                Log::info('✅ suggest_mem message sent successfully', [
                    'chat_id' => $chatId,
                    'message_id' => $result['message_id'] ?? null,
                ]);
            } else {
                Log::error('❌ Failed to send suggest_mem message', [
                    'chat_id' => $chatId,
                ]);
            }
        } catch (\Exception $e) {
            try {
                Log::error('❌ Failed to handle suggest_mem command', [
                    'chat_id' => $chatId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            } catch (\Exception $logError) {
                // Игнорируем ошибки логирования
            }
        }
    }

    /**
     * Обработка предложенного мема (фото/видео от пользователя)
     */
    private function handleMemeSuggestion(array $message, ?array $from, int $chatId): void
    {
        try {
            Log::info('📤 handleMemeSuggestion called', [
                'chat_id' => $chatId,
                'user_id' => $from['id'] ?? null,
                'has_photo' => isset($message['photo']),
                'has_video' => isset($message['video']),
            ]);
            
            if (!$from) {
                Log::warning('handleMemeSuggestion: no from data');
                return;
            }
            
            $userId = $from['id'];
            
            // Проверяем, активировал ли пользователь режим предложения мема
            $isActive = \Illuminate\Support\Facades\Cache::get("meme_suggestion_active_{$userId}", false);
            
            if (!$isActive) {
                Log::info('⚠️ Meme suggestion ignored - user did not activate suggestion mode', [
                    'user_id' => $userId,
                    'chat_id' => $chatId,
                ]);
                // Не отправляем сообщение пользователю, чтобы не спамить
                return;
            }
            
            $telegramService = new TelegramService();
            $fileId = null;
            $mediaType = null;
            $caption = $message['caption'] ?? null;
            
            // Обработка фото
            if (isset($message['photo']) && is_array($message['photo'])) {
                $photos = $message['photo'];
                $largestPhoto = end($photos); // Берем самое большое фото
                $fileId = $largestPhoto['file_id'] ?? null;
                $mediaType = MemeSuggestion::TYPE_PHOTO;
            }
            
            // Обработка видео
            if (isset($message['video'])) {
                $fileId = $message['video']['file_id'] ?? null;
                $mediaType = MemeSuggestion::TYPE_VIDEO;
            }
            
            if (!$fileId || !$mediaType) {
                return; // Не фото и не видео
            }
            
            // Проверяем, не слишком ли много предложений от этого пользователя (защита от спама)
            $recentSuggestions = MemeSuggestion::where('user_id', $userId)
                ->where('created_at', '>=', now()->subHours(1))
                ->count();
            
            if ($recentSuggestions >= 5) {
                // Сбрасываем флаг при превышении лимита
                \Illuminate\Support\Facades\Cache::forget("meme_suggestion_active_{$userId}");
                
                $telegramService->sendMessage(
                    $chatId,
                    "⏳ Вы отправили слишком много предложений за последний час. Пожалуйста, подождите.",
                    ['parse_mode' => 'HTML']
                );
                return;
            }
            
            // Сохраняем предложение
            $suggestion = MemeSuggestion::create([
                'user_id' => $userId,
                'username' => $from['username'] ?? null,
                'first_name' => $from['first_name'] ?? null,
                'media_type' => $mediaType,
                'file_id' => $fileId,
                'status' => MemeSuggestion::STATUS_PENDING,
            ]);
            
            // Сбрасываем флаг после успешного создания предложения
            \Illuminate\Support\Facades\Cache::forget("meme_suggestion_active_{$userId}");
            
            Log::info('✅ Meme suggestion created and flag cleared', [
                'suggestion_id' => $suggestion->id,
                'user_id' => $userId,
            ]);
            
            // Уведомить админа о новом предложении
            $adminNotified = $this->notifyAdminAboutNewSuggestion($suggestion);
            
            // Отправить подтверждение пользователю после того, как админ получил уведомление
            if ($adminNotified) {
                $telegramService->sendMessage(
                    $chatId,
                    "✅ <b>Спасибо за предложение!</b>\n\nВаш мем отправлен на модерацию. Администратор рассмотрит его в ближайшее время.",
                    ['parse_mode' => 'HTML']
                );
            } else {
                // Если не удалось уведомить админа, все равно подтверждаем пользователю
                $telegramService->sendMessage(
                    $chatId,
                    "✅ <b>Спасибо за предложение!</b>\n\nВаш мем сохранен и будет рассмотрен администратором в ближайшее время.",
                    ['parse_mode' => 'HTML']
                );
            }
            
            try {
                Log::info('Meme suggestion received', [
                    'suggestion_id' => $suggestion->id,
                    'user_id' => $from['id'],
                    'media_type' => $mediaType,
                ]);
            } catch (\Exception $logError) {
                // Игнорируем ошибки логирования
            }
        } catch (\Exception $e) {
            try {
                Log::error('Failed to handle meme suggestion', [
                    'chat_id' => $chatId,
                    'error' => $e->getMessage(),
                ]);
            } catch (\Exception $logError) {
                // Игнорируем ошибки логирования
            }
        }
    }

    /**
     * Уведомить админа о новом предложении мема
     * @return bool true если уведомление успешно отправлено, false в противном случае
     */
    private function notifyAdminAboutNewSuggestion(MemeSuggestion $suggestion): bool
    {
        try {
            $telegramService = new TelegramService();
            $ownerChatId = $telegramService->getOwnerChatId();
            
            if (!$ownerChatId) {
                Log::warning('Cannot notify admin: owner chat ID not found');
                return false;
            }
            
            $userInfo = $suggestion->first_name ?? $suggestion->username ?? "ID: {$suggestion->user_id}";
            $mediaTypeText = $suggestion->media_type === MemeSuggestion::TYPE_VIDEO ? '🎥 Видео' : '📷 Фото';
            
            $message = "📥 <b>Новое предложение мема</b>\n\n";
            $message .= "👤 <b>От:</b> {$userInfo}\n";
            $message .= "📎 <b>Тип:</b> {$mediaTypeText}\n";
            $message .= "🆔 <b>ID предложения:</b> {$suggestion->id}\n\n";
            $message .= "💡 Проверьте в админ-панели: /admin/meme-suggestions";
            
            // Отправить превью мема админу
            $result = null;
            if ($suggestion->media_type === MemeSuggestion::TYPE_VIDEO) {
                $result = $telegramService->sendVideo($ownerChatId, $suggestion->file_id, $message);
            } else {
                $result = $telegramService->sendPhoto($ownerChatId, $suggestion->file_id, $message);
            }
            
            if ($result) {
                Log::info('✅ Admin notified about new meme suggestion', [
                    'suggestion_id' => $suggestion->id,
                    'owner_chat_id' => $ownerChatId,
                ]);
                return true;
            } else {
                Log::warning('Failed to send notification to admin', [
                    'suggestion_id' => $suggestion->id,
                    'owner_chat_id' => $ownerChatId,
                ]);
                return false;
            }
        } catch (\Exception $e) {
            try {
                Log::error('Failed to notify admin about new meme suggestion', [
                    'suggestion_id' => $suggestion->id,
                    'error' => $e->getMessage(),
                ]);
            } catch (\Exception $logError) {
                // Игнорируем ошибки логирования
            }
            return false;
        }
    }

    /**
     * Обработка команды /status в личном чате (общая статистика по всем чатам)
     */
    private function handleStatusCommandPrivate(int $chatId, ?array $from): void
    {
        if (!$from) {
            return;
        }

        $userId = $from['id'] ?? 0;
        $username = $from['username'] ?? null;
        $firstName = $from['first_name'] ?? 'Пользователь';

        // Получить общую статистику пользователя по всем чатам
        $totalStats = \App\Models\UserScore::where('user_id', $userId)
            ->selectRaw('SUM(total_points) as total_points, SUM(correct_answers) as correct_answers, SUM(total_answers) as total_answers, SUM(first_place_count) as first_place_count, COUNT(*) as chats_count')
            ->first();

        // Формировать сообщение
        $telegramService = new \App\Services\TelegramService();
        
        if ($totalStats && $totalStats->total_points > 0) {
            $accuracy = $totalStats->total_answers > 0 
                ? round(($totalStats->correct_answers / $totalStats->total_answers) * 100, 1)
                : 0;
            
            $message = "📊 <b>Ваша общая статистика</b>\n\n";
            $message .= "👤 <b>Пользователь:</b> " . ($firstName ?? $username ?? "User {$userId}") . "\n";
            $message .= "💬 <b>Активных чатов:</b> " . number_format($totalStats->chats_count) . "\n";
            $message .= "🏆 <b>Всего очков:</b> " . number_format($totalStats->total_points) . "\n";
            $message .= "✅ <b>Правильных ответов:</b> " . number_format($totalStats->correct_answers) . "\n";
            $message .= "📝 <b>Всего ответов:</b> " . number_format($totalStats->total_answers) . "\n";
            $message .= "🎯 <b>Точность:</b> {$accuracy}%\n";
            $message .= "🥇 <b>Первых мест:</b> " . number_format($totalStats->first_place_count) . "\n";
        } else {
            $message = "📊 <b>Ваша общая статистика</b>\n\n";
            $message .= "👤 <b>Пользователь:</b> " . ($firstName ?? $username ?? "User {$userId}") . "\n";
            $message .= "❌ <b>У вас пока нет статистики.</b>\n\n";
            $message .= "💡 <i>Добавьте бота в группу и участвуйте в викторинах, чтобы заработать очки!</i>";
        }

        try {
            $telegramService->sendMessage(
                $chatId,
                $message,
                ['parse_mode' => 'HTML']
            );
        } catch (\Exception $e) {
            try {
                Log::error('Failed to send status command response (private)', [
                    'chat_id' => $chatId,
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            } catch (\Exception $logError) {
                // Игнорируем ошибки логирования
            }
        }
    }

    /**
     * Обработка команды /profile (просмотр профиля)
     */
    private function handleProfileCommand(int $chatId, ?array $from): void
    {
        try {
            if (!$from) {
                return;
            }

            $userId = $from['id'] ?? null;
            if (!$userId) {
                return;
            }

            $profile = UserProfile::getOrCreate($userId);
            $profile->updateTotalPoints(); // Обновить очки перед показом

            $telegramService = new TelegramService();
            $dotabuffService = new DotabuffService();
            
            // Определяем тип чата (личный или группа)
            // В Telegram: личные чаты имеют положительный chat_id, группы - отрицательный
            $isPrivateChat = $chatId > 0;

            // Синхронизировать данные с Dotabuff (если прошло больше часа)
            if ($profile->dotabuff_url) {
                if (!$profile->dotabuff_last_sync || $profile->dotabuff_last_sync->addHour()->isPast()) {
                    $dotabuffData = $dotabuffService->getPlayerData($profile->dotabuff_url);
                    if ($dotabuffData) {
                        $profile->dotabuff_data = $dotabuffData;
                        $profile->dotabuff_last_sync = now();
                        $profile->save();
                    }
                }
            }

            // Формируем профиль (одинаковый для группы и личного чата)
            $message = "";

            // Имя
            $firstName = $from['first_name'] ?? 'Пользователь';
            $message .= "👤 <b>Имя:</b> {$firstName}\n";

            // Ник в игре
            $gameNickname = $profile->game_nickname ?? 'Не указан';
            $message .= "🎮 <b>Ник в игре:</b> {$gameNickname}\n";

            // Юзернейм
            $username = $from['username'] ?? null;
            if ($username) {
                $message .= "📱 <b>Username:</b> @{$username}\n";
            } else {
                $message .= "📱 <b>Username:</b> Не указан\n";
            }

            $message .= "\n";

            // Рейтинг в боте
            $message .= "🏆 <b>Рейтинг в боте:</b>\n";
            $message .= "{$profile->getFormattedRank()}\n";
            
            // MMR из Dotabuff или очки бота
            $mmrValue = null;
            if ($profile->dotabuff_data && isset($profile->dotabuff_data['mmr'])) {
                $mmrValue = $profile->dotabuff_data['mmr'];
            }
            
            // Показываем MMR из Dotabuff, если есть, иначе очки бота
            if ($mmrValue !== null) {
                $message .= "📈 <b>MMR:</b> " . number_format($mmrValue) . "\n";
            } else {
                $message .= "📈 <b>MMR:</b> " . number_format($profile->rank_points) . "\n";
            }

            // Dotabuff (кликабельный ник и звание)
            if ($profile->dotabuff_url) {
                // Показываем кликабельный ник вместо ссылки
                $dotabuffNickname = 'Профиль';
                if ($profile->dotabuff_data && isset($profile->dotabuff_data['nickname'])) {
                    $dotabuffNickname = $profile->dotabuff_data['nickname'];
                }
                
                // Используем HTML ссылку для кликабельного ника
                $message .= "🎮 <b>Dotabuff:</b> <a href=\"{$profile->dotabuff_url}\">{$dotabuffNickname}</a>";
                
                // Добавляем звание из Dotabuff, если есть
                if ($profile->dotabuff_data && isset($profile->dotabuff_data['rank'])) {
                    $message .= " ({$profile->dotabuff_data['rank']})";
                }
                $message .= "\n";
            } else {
                $message .= "🎮 <b>Dotabuff:</b> Не указан\n";
            }

            // В личном чате добавляем ID, настройки и кнопки
            if ($isPrivateChat) {
                $fullMessage = "👤 <b>Ваш профиль</b>\n\n";
                $fullMessage .= "🆔 <b>ID:</b> <code>{$userId}</code>\n\n";
                $fullMessage .= $message;
                $fullMessage .= "\n";
                
                // Настройки
                $fullMessage .= "⚙️ <b>Настройки:</b>\n";
                $showRankStatus = $profile->show_rank_in_name ? "✅ Включено" : "❌ Выключено";
                $fullMessage .= "Показывать рейтинг рядом с именем: {$showRankStatus}\n\n";

                // Получаем username бота для создания ссылки
                $botInfo = $telegramService->getMe();
                $botUsername = $botInfo['username'] ?? 'tajdota_quiz_bot';
                $botUrl = "https://t.me/{$botUsername}?start=profile";

                // Кнопки: Редактировать профиль (переход в личный чат) и переход на бота
                $buttons = [
                    [
                        [
                            'text' => '✏️ Редактировать профиль',
                            'url' => $botUrl
                        ]
                    ],
                    [
                        [
                            'text' => '🤖 Перейти к боту',
                            'url' => "https://t.me/{$botUsername}"
                        ]
                    ],
                ];
                
                $telegramService->sendMessageWithButtons($chatId, $fullMessage, $buttons, ['parse_mode' => 'HTML']);
            } else {
                // В группе - только профиль без ID, настроек и кнопок
                $telegramService->sendMessage($chatId, $message, ['parse_mode' => 'HTML']);
            }
        } catch (\Exception $e) {
            Log::error('Failed to handle profile command', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Обработка команды /setprofile (настройка профиля)
     */
    private function handleSetProfileCommand(int $chatId, ?array $from, string $text): void
    {
        try {
            if (!$from) {
                return;
            }

            $userId = $from['id'] ?? null;
            if (!$userId) {
                return;
            }

            $profile = UserProfile::getOrCreate($userId);
            $telegramService = new TelegramService();
            $dotabuffService = new DotabuffService();

            // Парсим команду: /setprofile <параметр> <значение>
            $parts = preg_split('/\s+/', trim($text), 3);
            
            if (count($parts) < 2) {
                $message = "⚙️ <b>Настройка профиля</b>\n\n";
                $message .= "Использование:\n";
                $message .= "/setprofile nickname <ник>\n";
                $message .= "/setprofile dotabuff <url>\n";
                $message .= "/setprofile showrank on/off\n\n";
                $message .= "Примеры:\n";
                $message .= "/setprofile nickname ProPlayer123\n";
                $message .= "/setprofile dotabuff https://www.dotabuff.com/players/123456789\n";
                $message .= "/setprofile showrank on";
                
                $telegramService->sendMessage($chatId, $message, ['parse_mode' => 'HTML']);
                return;
            }

            $param = strtolower($parts[1] ?? '');
            $value = $parts[2] ?? '';

            switch ($param) {
                case 'nickname':
                case 'ник':
                    $profile->game_nickname = $value;
                    $profile->save();
                    $telegramService->sendMessage($chatId, "✅ Ник в игре установлен: <b>{$value}</b>", ['parse_mode' => 'HTML']);
                    break;

                case 'dotabuff':
                    if (!$dotabuffService->validateUrl($value)) {
                        $telegramService->sendMessage($chatId, "❌ Неверный URL Dotabuff. Формат: https://www.dotabuff.com/players/123456789");
                        return;
                    }
                    
                    $profile->dotabuff_url = $value;
                    // Синхронизируем данные сразу
                    $dotabuffData = $dotabuffService->getPlayerData($value);
                    if ($dotabuffData) {
                        $profile->dotabuff_data = $dotabuffData;
                        $profile->dotabuff_last_sync = now();
                    }
                    $profile->save();
                    $telegramService->sendMessage($chatId, "✅ Ссылка на Dotabuff установлена и синхронизирована!", ['parse_mode' => 'HTML']);
                    break;


                case 'showrank':
                case 'показывать_рейтинг':
                    $valueLower = strtolower($value);
                    $profile->show_rank_in_name = in_array($valueLower, ['on', 'вкл', 'да', 'yes', '1', 'true']);
                    $profile->save();
                    $status = $profile->show_rank_in_name ? "включено" : "выключено";
                    $telegramService->sendMessage($chatId, "✅ Показ рейтинга рядом с именем: <b>{$status}</b>", ['parse_mode' => 'HTML']);
                    break;

                default:
                    $telegramService->sendMessage($chatId, "❌ Неизвестный параметр. Используйте /setprofile для справки.");
            }
        } catch (\Exception $e) {
            Log::error('Failed to handle setprofile command', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
            $telegramService = new TelegramService();
            $telegramService->sendMessage($chatId, "❌ Ошибка при настройке профиля. Попробуйте позже.");
        }
    }

    /**
     * Обработка callback queries для профиля
     */
    private function handleProfileCallback(int $chatId, ?array $from, string $data, string $callbackQueryId): void
    {
        try {
            if (!$from) {
                return;
            }

            $userId = $from['id'] ?? null;
            if (!$userId) {
                return;
            }

            $telegramService = new TelegramService();
            $telegramService->answerCallbackQuery($callbackQueryId);

            $profile = UserProfile::getOrCreate($userId);

            switch ($data) {
                case 'profile_set_nickname':
                    // Устанавливаем флаг ожидания ввода ника
                    \Illuminate\Support\Facades\Cache::put(
                        "profile_waiting_nickname_{$userId}",
                        true,
                        now()->addMinutes(5)
                    );
                    
                    $message = "✏️ <b>Настройка ника в игре</b>\n\n";
                    $message .= "Отправьте ваш ник в игре текстовым сообщением.\n\n";
                    $message .= "💡 <i>Например: ProPlayer123</i>";
                    
                    $telegramService->sendMessage($chatId, $message, ['parse_mode' => 'HTML']);
                    break;

                case 'profile_set_dotabuff':
                    // Устанавливаем флаг ожидания ввода URL
                    \Illuminate\Support\Facades\Cache::put(
                        "profile_waiting_dotabuff_{$userId}",
                        true,
                        now()->addMinutes(5)
                    );
                    
                    $message = "🔗 <b>Настройка Dotabuff</b>\n\n";
                    $message .= "Отправьте ссылку на ваш профиль Dotabuff.\n\n";
                    $message .= "💡 <i>Формат: https://www.dotabuff.com/players/123456789</i>\n\n";
                    $message .= "📋 <i>Скопируйте ссылку с вашего профиля на dotabuff.com</i>";
                    
                    $telegramService->sendMessage($chatId, $message, ['parse_mode' => 'HTML']);
                    break;

                case 'profile_toggle_rank':
                    // Переключаем показ рейтинга
                    $profile->show_rank_in_name = !$profile->show_rank_in_name;
                    $profile->save();
                    
                    $status = $profile->show_rank_in_name ? "включено" : "выключено";
                    $emoji = $profile->show_rank_in_name ? "✅" : "❌";
                    
                    $message = "{$emoji} Показ рейтинга рядом с именем: <b>{$status}</b>";
                    $telegramService->sendMessage($chatId, $message, ['parse_mode' => 'HTML']);
                    
                    // Обновляем профиль
                    $this->handleProfileCommand($chatId, $from);
                    break;

                case 'profile_refresh':
                    // Обновляем профиль
                    $this->handleProfileCommand($chatId, $from);
                    break;

                default:
                    $telegramService->sendMessage($chatId, "❌ Неизвестная команда профиля.");
            }
        } catch (\Exception $e) {
            Log::error('Failed to handle profile callback', [
                'chat_id' => $chatId,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Обработка команды /top (топ лидеров)
     */
    private function handleTopCommand(int $chatId, ?array $from): void
    {
        try {
            $telegramService = new TelegramService();
            
            // Получаем топ 10 пользователей по очкам
            $topUsers = UserProfile::orderBy('rank_points', 'desc')
                ->orderBy('total_points', 'desc')
                ->take(10)
                ->get();
            
            if ($topUsers->isEmpty()) {
                $telegramService->sendMessage(
                    $chatId,
                    "🏆 <b>Топ лидеров</b>\n\n😔 Пока нет лидеров в рейтинге.",
                    ['parse_mode' => 'HTML']
                );
                return;
            }
            
            $message = "🏆 <b>Топ лидеров</b>\n\n";
            
            foreach ($topUsers as $index => $user) {
                $place = $index + 1;
                $medal = $this->getMedalEmoji($place);
                $rank = $user->getFormattedRank();
                $points = number_format($user->rank_points);
                $name = $user->game_nickname ?? "User {$user->user_id}";
                
                $message .= "{$medal} <b>#{$place}</b> {$rank}\n";
                $message .= "   👤 {$name} - <b>{$points} очков</b>\n\n";
            }
            
            // Добавляем информацию о том, как посмотреть свой профиль
            $message .= "💡 <i>Используйте /profile чтобы посмотреть свой профиль</i>";
            
            $telegramService->sendMessage($chatId, $message, ['parse_mode' => 'HTML']);
        } catch (\Exception $e) {
            Log::error('Failed to handle top command', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Получить эмодзи медали для места
     */
    private function getMedalEmoji(int $place): string
    {
        switch ($place) {
            case 1: return '🥇';
            case 2: return '🥈';
            case 3: return '🥉';
            default: return '🏅';
        }
    }
}
