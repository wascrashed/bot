<?php

namespace App\Http\Controllers;

use App\Models\ActiveQuiz;
use App\Services\QuizService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
                
                Log::info('Telegram webhook received', [
                    'has_message' => isset($update['message']),
                    'has_callback' => isset($update['callback_query']),
                    'chat_id' => $chatId,
                ]);
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
            }
            return; // Не обрабатываем личные сообщения дальше
        }
        
        if (!in_array($chatType, ['group', 'supergroup'])) {
            return; // Игнорируем каналы
        }

        $chatId = $chat['id'];
        
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
        ]);
        
        $activeQuiz = null;
        $now = \Carbon\Carbon::now();
        
        foreach ($activeQuizzes as $quiz) {
            // КРИТИЧЕСКАЯ ПРОВЕРКА: если expires_at раньше started_at, исправить
            if ($quiz->expires_at->lessThan($quiz->started_at)) {
                Log::error('CRITICAL: Found quiz with invalid expires_at, fixing...', [
                    'active_quiz_id' => $quiz->id,
                    'started_at' => $quiz->started_at->format('Y-m-d H:i:s'),
                    'expires_at_before' => $quiz->expires_at->format('Y-m-d H:i:s'),
                ]);
                
                // Пересчитать expires_at правильно
                $correctExpiresAt = $quiz->started_at->copy()->addSeconds(20);
                $quiz->update(['expires_at' => $correctExpiresAt]);
                $quiz->refresh();
                
                Log::info('Fixed quiz expires_at', [
                    'active_quiz_id' => $quiz->id,
                    'expires_at_after' => $quiz->expires_at->format('Y-m-d H:i:s'),
                ]);
            }
            
            // Проверить, что викторина еще не истекла
            // Использовать прямое сравнение Carbon объектов для правильной работы с часовыми поясами
            $expiresAt = $quiz->expires_at;
            $isNotExpired = $expiresAt->isFuture();
            
            // Логировать для диагностики
            Log::debug('Checking quiz expiration', [
                'active_quiz_id' => $quiz->id,
                'started_at' => $quiz->started_at->format('Y-m-d H:i:s T'),
                'expires_at' => $expiresAt->format('Y-m-d H:i:s T'),
                'now' => $now->format('Y-m-d H:i:s T'),
                'is_future' => $isNotExpired,
                'is_expired_method' => $quiz->isExpired(),
            ]);
            
            if ($isNotExpired) {
                $activeQuiz = $quiz;
                Log::info('Active quiz found for message', [
                    'active_quiz_id' => $quiz->id,
                    'chat_id' => $chatId,
                    'started_at' => $quiz->started_at->format('Y-m-d H:i:s'),
                    'expires_at' => $quiz->expires_at->format('Y-m-d H:i:s'),
                    'now' => now()->format('Y-m-d H:i:s'),
                ]);
                break; // Нашли активную викторину
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
            
            Log::info('No active quiz found for message', [
                'chat_id' => $chatId,
                'has_text' => !empty($message['text'] ?? ''),
                'now' => $now->format('Y-m-d H:i:s T'),
                'recent_quizzes' => $quizInfo,
            ]);
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
                    $this->quizService->processAnswer(
                        $activeQuiz->id,
                        $userId,
                        $username,
                        $firstName,
                        $text
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
                Log::debug('Message has no text, skipping', [
                    'chat_id' => $chatId,
                    'message_keys' => array_keys($message),
                ]);
            }
        } else {
            // Нет активной викторины - обновить статистику чата для отслеживания
            Log::debug('No active quiz found for message', [
                'chat_id' => $chatId,
                'has_text' => !empty($message['text'] ?? ''),
            ]);
            \App\Models\ChatStatistics::getOrCreate($chatId, $chatType, $chat['title'] ?? null);
        }
    }

    /**
     * Обработка callback_query (нажатие на кнопки)
     */
    private function handleCallbackQuery(array $callbackQuery): void
    {
        $from = $callbackQuery['from'] ?? null;
        $message = $callbackQuery['message'] ?? null;
        $data = $callbackQuery['data'] ?? '';
        $callbackQueryId = $callbackQuery['id'] ?? null;

        if (!$from || !$message || !$data || !$callbackQueryId) {
            return;
        }

        // Игнорировать нажатия от ботов
        if ($from['is_bot'] ?? false) {
            return;
        }

        $chat = $message['chat'] ?? null;
        if (!$chat) {
            return;
        }

        $chatType = $chat['type'] ?? null;
        if (!in_array($chatType, ['group', 'supergroup'])) {
            return;
        }

        $chatId = $chat['id'];
        $userId = $from['id'] ?? 0;
        $username = $from['username'] ?? null;
        $firstName = $from['first_name'] ?? '';

        // Проверить, есть ли активная викторина в этом чате
        $activeQuiz = ActiveQuiz::where('chat_id', $chatId)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->first();

        if (!$activeQuiz) {
            // Отвечаем на callback, что викторина уже завершена
            $telegram = new \App\Services\TelegramService();
            $telegram->answerCallbackQuery($callbackQueryId, 'Время на ответ истекло!', false);
            Log::warning('Callback query for inactive quiz', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'callback_data' => $data,
            ]);
            return;
        }

        // Логировать обработку callback
        Log::info('Processing callback answer', [
            'active_quiz_id' => $activeQuiz->id,
            'chat_id' => $chatId,
            'user_id' => $userId,
            'callback_data' => $data,
        ]);

        // Обработать ответ через callback
        $this->quizService->processAnswerWithCallback(
            $activeQuiz->id,
            $userId,
            $username,
            $firstName,
            $data, // callback_data для парсинга ответа
            $callbackQueryId // callback_query_id для ответа на callback
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
