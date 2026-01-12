<?php

namespace App\Http\Controllers;

use App\Models\ActiveQuiz;
use App\Services\QuizService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
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
        try {
            $update = $request->all();

            // Логировать только важные события, чтобы не засорять логи
            if (isset($update['message']) || isset($update['callback_query'])) {
                $chatId = null;
                if (isset($update['message']['chat']['id'])) {
                    $chatId = $update['message']['chat']['id'];
                } elseif (isset($update['callback_query']['message']['chat']['id'])) {
                    $chatId = $update['callback_query']['message']['chat']['id'];
                }
                
                try {
                    Log::info('Telegram webhook received', [
                        'has_message' => isset($update['message']),
                        'has_callback' => isset($update['callback_query']),
                        'chat_id' => $chatId,
                    ]);
                } catch (\Exception $logError) {
                    // Игнорируем ошибки логирования, чтобы не прерывать выполнение
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
            Log::error('Webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Обработка входящего сообщения
     */
    private function handleMessage(array $message): void
    {
        // Проверить, что это сообщение из группы или супергруппы
        $chat = $message['chat'] ?? null;
        if (!$chat) {
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
                
                // Если пользователь написал /chatid или /id, отправить ID чата
                $text = trim($message['text'] ?? '');
                if (!empty($text) && preg_match('/^\/(chatid|id|getid)(@\w+)?\s*$/i', $text)) {
                    $telegramService = new \App\Services\TelegramService();
                    $telegramService->sendMessage(
                        $chat['id'],
                        "🆔 <b>Ваш Chat ID:</b> <code>{$chat['id']}</code>\n\n💡 <i>Это ваш личный Chat ID</i>",
                        ['parse_mode' => 'HTML']
                    );
                }
            }
            return; // Не обрабатываем личные сообщения дальше
        }
        
        if (!in_array($chatType, ['group', 'supergroup'])) {
            return; // Игнорируем каналы
        }

        $chatId = $chat['id'];
        
        // Если пользователь написал /chatid или /id в группе, отправить ID чата
        $text = trim($message['text'] ?? '');
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
        
        $from = $message['from'] ?? null;
        
        // Игнорировать сообщения от ботов
        if ($from && ($from['is_bot'] ?? false)) {
            return;
        }

        // Логировать все сообщения из групп для диагностики
        Log::info('Message received in group', [
            'chat_id' => $chatId,
            'chat_type' => $chatType,
            'has_text' => !empty($message['text'] ?? ''),
            'text' => $message['text'] ?? null,
        ]);

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
            // Нет активной викторины - логируем только если есть текст (потенциальный ответ)
            if (!empty($message['text'] ?? '')) {
                // Логирование уже есть ниже в коде, здесь не нужно
            }
            \App\Models\ChatStatistics::getOrCreate($chatId, $chatType, $chat['title'] ?? null);
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
}
