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

            Log::debug('Telegram webhook received', ['update' => $update]);

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
        
        $from = $message['from'] ?? null;
        
        // Игнорировать сообщения от ботов
        if ($from && ($from['is_bot'] ?? false)) {
            return;
        }

        // Сохранить информацию о чате, создав запись ActiveQuiz, если её еще нет
        // Это позволит команде quiz:start-random находить активные чаты
        // Создадим "фиктивную" запись, если нет активной викторины, чтобы пометить чат как активный
        $activeQuiz = ActiveQuiz::where('chat_id', $chatId)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->first();

        if ($activeQuiz) {
            // Есть активная викторина - обработать ответ
            $text = $message['text'] ?? '';
            if (!empty($text)) {
                $userId = $from['id'] ?? 0;
                $username = $from['username'] ?? null;
                $firstName = $from['first_name'] ?? '';

                $this->quizService->processAnswer(
                    $activeQuiz->id,
                    $userId,
                    $username,
                    $firstName,
                    $text
                );
            }
        } else {
            // Нет активной викторины - обновить статистику чата для отслеживания
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
            return;
        }

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
}
