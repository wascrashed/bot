<?php

namespace App\Services;

use App\Models\Question;
use App\Models\ActiveQuiz;
use App\Models\QuizResult;
use App\Models\UserScore;
use App\Models\QuestionHistory;
use App\Models\ChatStatistics;
use App\Models\BotAnalytics;
use App\Services\TelegramService;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class QuizService
{
    private TelegramService $telegram;
    private AnalyticsService $analytics;

    public function __construct(TelegramService $telegram, AnalyticsService $analytics)
    {
        $this->telegram = $telegram;
        $this->analytics = $analytics;
    }

    /**
     * Начать викторину в чате
     */
    public function startQuiz(int $chatId, string $chatType = 'group'): bool
    {
        try {
            $startTime = microtime(true);
            
            // Проверить права администратора
            if (!$this->telegram->isBotAdmin($chatId)) {
                Log::warning("Bot is not admin in chat {$chatId}");
                $this->analytics->logError("Bot not admin in chat {$chatId}");
                
                // Получить информацию о чате для детального сообщения
                $chatInfo = $this->telegram->getChat($chatId);
                $chatTitle = $chatInfo['title'] ?? "группа";
                
                $errorMessage = "⚠️ <b>Не удалось запустить викторину</b>\n\n";
                $errorMessage .= "📊 <b>Группа:</b> {$chatTitle}\n";
                $errorMessage .= "🆔 <b>ID:</b> {$chatId}\n\n";
                $errorMessage .= "❌ <b>Причина:</b> Бот не является администратором группы\n\n";
                $errorMessage .= "💡 <b>Решение:</b> Пожалуйста, предоставьте боту права администратора, чтобы викторины могли запускаться автоматически.";
                
                $this->sendErrorNotification($chatId, $errorMessage);
                return false;
            }

            // Проверить, нет ли уже активной викторины в этом чате
            $existingQuiz = ActiveQuiz::where('chat_id', $chatId)
                ->where('is_active', true)
                ->first();

            if ($existingQuiz && !$existingQuiz->isExpired()) {
                Log::info("Quiz already active in chat {$chatId}");
                
                // Отправить уведомление в группу, что викторина уже активна
                $chatInfo = $this->telegram->getChat($chatId);
                $chatTitle = $chatInfo['title'] ?? "группа";
                
                $errorMessage = "ℹ️ <b>Викторина уже активна</b>\n\n";
                $errorMessage .= "📊 <b>Группа:</b> {$chatTitle}\n";
                $errorMessage .= "🆔 <b>ID:</b> {$chatId}\n\n";
                $errorMessage .= "⏱ В группе уже идет активная викторина. Дождитесь её завершения.";
                
                $this->sendErrorNotification($chatId, $errorMessage);
                return false;
            }

            // Получить случайный вопрос, исключая использованные за последние 24 часа
            $usedQuestionIds = QuestionHistory::getRecentQuestionIds($chatId, 24);
            $question = Question::whereNotIn('id', $usedQuestionIds)
                ->inRandomOrder()
                ->first();

            // Если все вопросы использованы, сбросить историю для этого чата
            if (!$question) {
                Log::info("All questions used in chat {$chatId}, resetting history");
                QuestionHistory::where('chat_id', $chatId)
                    ->where('asked_at', '<', now()->subHours(24))
                    ->delete();
                
                $question = Question::inRandomOrder()->first();
            }

            if (!$question) {
                Log::warning("No questions found in database");
                $this->analytics->logError("No questions in database");
                
                // Получить информацию о чате для детального сообщения
                $chatInfo = $this->telegram->getChat($chatId);
                $chatTitle = $chatInfo['title'] ?? "группа";
                
                $errorMessage = "⚠️ <b>Не удалось запустить викторину</b>\n\n";
                $errorMessage .= "📊 <b>Группа:</b> {$chatTitle}\n";
                $errorMessage .= "🆔 <b>ID:</b> {$chatId}\n\n";
                $errorMessage .= "❌ <b>Причина:</b> В базе данных нет вопросов\n\n";
                $errorMessage .= "💡 <b>Решение:</b> Обратитесь к администратору бота для добавления вопросов.";
                
                $this->sendErrorNotification($chatId, $errorMessage);
                $this->notifyOwnerAboutError($chatId, "Нет вопросов в базе", "В базе данных отсутствуют вопросы для викторины");
                return false;
            }

            // Создать активную викторину
            // ЯВНО использовать UTC для избежания проблем с часовыми поясами
            $startedAt = Carbon::now('UTC');
            $expiresAt = $startedAt->copy()->addSeconds(20); // 20 секунд на ответ
            
            // КРИТИЧЕСКАЯ ПРОВЕРКА: убедиться, что expires_at позже started_at
            if ($expiresAt->lessThanOrEqualTo($startedAt)) {
                Log::error('CRITICAL: expires_at calculation error!', [
                    'started_at' => $startedAt->format('Y-m-d H:i:s T'),
                    'expires_at_before_fix' => $expiresAt->format('Y-m-d H:i:s T'),
                ]);
                // Пересчитать правильно
                $expiresAt = $startedAt->copy()->addSeconds(20);
                Log::info('Recalculated expires_at', [
                    'expires_at_after_fix' => $expiresAt->format('Y-m-d H:i:s T'),
                ]);
            }
            
            // Дополнительная проверка: разница должна быть 20 секунд
            $diff = $expiresAt->diffInSeconds($startedAt);
            if ($diff !== 20) {
                Log::warning('Time difference is not 20 seconds, recalculating', [
                    'diff' => $diff,
                    'started_at' => $startedAt->format('Y-m-d H:i:s T'),
                    'expires_at' => $expiresAt->format('Y-m-d H:i:s T'),
                ]);
                $expiresAt = $startedAt->copy()->addSeconds(20);
            }
            $answersOrder = $this->prepareAnswersForQuestion($question);
            
            // Найти индекс правильного ответа в перемешанном массиве
            $correctAnswerIndex = null;
            if (!empty($answersOrder) && in_array($question->question_type, [Question::TYPE_MULTIPLE_CHOICE, Question::TYPE_TRUE_FALSE])) {
                if ($question->question_type === Question::TYPE_TRUE_FALSE) {
                    // Для Верно/Неверно: Верно = 0, Неверно = 1
                    // correct_answer хранит индекс: 0 = Верно, 1 = Неверно
                    $correctAnswerIndex = (int)$question->correct_answer;
                    // Но нужно найти этот ответ в перемешанном массиве
                    $correctText = $question->getCorrectAnswerText();
                    foreach ($answersOrder as $index => $answer) {
                        if (mb_strtolower(trim($answer)) === mb_strtolower(trim($correctText))) {
                            $correctAnswerIndex = $index;
                            break;
                        }
                    }
                } else {
                    // Для вопросов с выбором - correct_answer это индекс в исходном массиве
                    // Нужно найти текст правильного ответа и его индекс в перемешанном массиве
                    $correctText = $question->getCorrectAnswerText();
                    foreach ($answersOrder as $index => $answer) {
                        if (mb_strtolower(trim($answer)) === mb_strtolower(trim($correctText))) {
                            $correctAnswerIndex = $index;
                            break;
                        }
                    }
                }
            }

            // Логировать создание викторины с временем
            Log::info('Creating active quiz', [
                'chat_id' => $chatId,
                'question_id' => $question->id,
                'question_type' => $question->question_type,
                'answers_order' => $answersOrder,
                'answers_count' => count($answersOrder),
                'correct_answer_index' => $correctAnswerIndex,
                'correct_answer_text' => $question->getCorrectAnswerText(),
                'correct_answer_index_in_question' => (int)$question->correct_answer,
                'answers_with_indexes' => array_map(function($index, $answer) use ($correctAnswerIndex) {
                    return [
                        'index' => $index,
                        'text' => $answer,
                        'is_correct' => ($index === $correctAnswerIndex)
                    ];
                }, array_keys($answersOrder), $answersOrder),
                'started_at_raw' => $startedAt->format('Y-m-d H:i:s'),
                'expires_at_raw' => $expiresAt->format('Y-m-d H:i:s'),
                'timezone' => $startedAt->timezone->getName(),
            ]);

            // Сохранить время в UTC, используя явное форматирование для гарантии
            $activeQuiz = ActiveQuiz::create([
                'chat_id' => $chatId,
                'chat_type' => $chatType,
                'question_id' => $question->id,
                'answers_order' => $answersOrder,
                'correct_answer_index' => $correctAnswerIndex,
                // Явно форматировать время в UTC для сохранения в БД
                'started_at' => $startedAt->format('Y-m-d H:i:s'),
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                'is_active' => true,
            ]);
            
            // Проверить, что данные сохранились правильно
            $activeQuiz->refresh();
            
            // Прочитать сырые значения из БД напрямую
            $rawData = DB::table('active_quizzes')
                ->where('id', $activeQuiz->id)
                ->first(['started_at', 'expires_at']);
            
            // Создать Carbon объекты из сырых строк, явно указав UTC
            $savedStartedAt = Carbon::createFromFormat('Y-m-d H:i:s', $rawData->started_at, 'UTC');
            $savedExpiresAt = Carbon::createFromFormat('Y-m-d H:i:s', $rawData->expires_at, 'UTC');
            
            // Если время неправильное, обновить
            if ($savedExpiresAt->lessThanOrEqualTo($savedStartedAt)) {
                Log::warning('Detected invalid expires_at after save, fixing...', [
                    'active_quiz_id' => $activeQuiz->id,
                    'started_at_raw' => $rawData->started_at,
                    'expires_at_raw' => $rawData->expires_at,
                ]);
                
                $correctExpiresAt = $savedStartedAt->copy()->addSeconds(20);
                DB::table('active_quizzes')
                    ->where('id', $activeQuiz->id)
                    ->update(['expires_at' => $correctExpiresAt->format('Y-m-d H:i:s')]);
                
                $activeQuiz->refresh();
                $rawData = DB::table('active_quizzes')
                    ->where('id', $activeQuiz->id)
                    ->first(['started_at', 'expires_at']);
                $savedExpiresAt = Carbon::createFromFormat('Y-m-d H:i:s', $rawData->expires_at, 'UTC');
            }
            
            // Использовать правильные значения для логирования
            $activeQuiz->started_at = $savedStartedAt;
            $activeQuiz->expires_at = $savedExpiresAt;
            
            Log::info('Active quiz created', [
                'active_quiz_id' => $activeQuiz->id,
                'saved_answers_order' => $activeQuiz->answers_order,
                'saved_started_at' => $activeQuiz->started_at->format('Y-m-d H:i:s T'),
                'saved_expires_at' => $activeQuiz->expires_at->format('Y-m-d H:i:s T'),
                'now' => Carbon::now('UTC')->format('Y-m-d H:i:s T'),
                'is_expired_check' => $activeQuiz->isExpired(),
                'time_diff_seconds' => $activeQuiz->expires_at->diffInSeconds($activeQuiz->started_at),
            ]);
            
            // КРИТИЧЕСКАЯ ПРОВЕРКА: если expires_at раньше started_at, исправить
            if ($activeQuiz->expires_at->lessThanOrEqualTo($activeQuiz->started_at)) {
                Log::error('CRITICAL: expires_at is before or equal to started_at! Fixing...', [
                    'active_quiz_id' => $activeQuiz->id,
                    'started_at' => $activeQuiz->started_at->format('Y-m-d H:i:s T'),
                    'expires_at_before' => $activeQuiz->expires_at->format('Y-m-d H:i:s T'),
                ]);
                
                // Пересчитать expires_at правильно
                $correctExpiresAt = $activeQuiz->started_at->copy()->addSeconds(20);
                DB::table('active_quizzes')
                    ->where('id', $activeQuiz->id)
                    ->update(['expires_at' => $correctExpiresAt->format('Y-m-d H:i:s')]);
                $activeQuiz->refresh();
                
                // Снова прочитать из БД
                $rawData = DB::table('active_quizzes')
                    ->where('id', $activeQuiz->id)
                    ->first(['started_at', 'expires_at']);
                $activeQuiz->started_at = Carbon::createFromFormat('Y-m-d H:i:s', $rawData->started_at, 'UTC');
                $activeQuiz->expires_at = Carbon::createFromFormat('Y-m-d H:i:s', $rawData->expires_at, 'UTC');
                
                Log::info('Fixed expires_at', [
                    'active_quiz_id' => $activeQuiz->id,
                    'expires_at_after' => $activeQuiz->expires_at->format('Y-m-d H:i:s T'),
                    'time_diff_seconds' => $activeQuiz->expires_at->diffInSeconds($activeQuiz->started_at),
                ]);
            }

            // Сохранить историю вопроса
            QuestionHistory::create([
                'chat_id' => $chatId,
                'question_id' => $question->id,
                'asked_at' => $startedAt,
            ]);

            // Отправить вопрос в зависимости от типа
            $result = $this->sendQuestionByType($chatId, $question, $activeQuiz);

            if ($result && isset($result['message_id'])) {
                $activeQuiz->update(['message_id' => $result['message_id']]);
                
                // Запланировать проверку результатов через 20 секунд
                dispatch(new \App\Jobs\CheckQuizResults($activeQuiz->id))
                    ->delay(now()->addSeconds(20));

                // Обновить статистику чата
                $this->updateChatStatistics($chatId, $chatType);

                // Логировать время ответа
                $responseTime = (microtime(true) - $startTime) * 1000;
                if ($responseTime > 1000) {
                    Log::warning("Slow quiz start", [
                        'chat_id' => $chatId,
                        'response_time_ms' => $responseTime,
                    ]);
                }

                $this->analytics->logQuizStarted($chatId, $responseTime);
                
                return true;
            }

            // Если не удалось отправить, деактивировать викторину
            $activeQuiz->update(['is_active' => false]);
            $this->analytics->logError("Failed to send quiz in chat {$chatId}");
            
            // Получить информацию о чате для детального сообщения
            $chatInfo = $this->telegram->getChat($chatId);
            $chatTitle = $chatInfo['title'] ?? "группа";
            
            $errorMessage = "⚠️ <b>Не удалось запустить викторину</b>\n\n";
            $errorMessage .= "📊 <b>Группа:</b> {$chatTitle}\n";
            $errorMessage .= "🆔 <b>ID:</b> {$chatId}\n\n";
            $errorMessage .= "❌ <b>Причина:</b> Ошибка при отправке сообщения\n\n";
            $errorMessage .= "💡 <b>Решение:</b> Попробуйте позже или обратитесь к администратору бота.";
            
            $this->sendErrorNotification($chatId, $errorMessage);
            $this->notifyOwnerAboutError($chatId, "Ошибка отправки", "Не удалось отправить сообщение с викториной в группу");
            return false;

        } catch (\Exception $e) {
            Log::error('Start quiz error', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->analytics->logError("Start quiz error: " . $e->getMessage());
            
            // Получить информацию о чате для детального сообщения
            try {
                $chatInfo = $this->telegram->getChat($chatId);
                $chatTitle = $chatInfo['title'] ?? "группа";
            } catch (\Exception $chatError) {
                $chatTitle = "группа";
            }
            
            $errorMessage = "⚠️ <b>Не удалось запустить викторину</b>\n\n";
            $errorMessage .= "📊 <b>Группа:</b> {$chatTitle}\n";
            $errorMessage .= "🆔 <b>ID:</b> {$chatId}\n\n";
            $errorMessage .= "❌ <b>Причина:</b> Произошла техническая ошибка\n\n";
            $errorMessage .= "💡 <b>Решение:</b> Попробуйте позже или обратитесь к администратору бота.";
            
            $this->sendErrorNotification($chatId, $errorMessage);
            $this->notifyOwnerAboutError($chatId, "Исключение", $e->getMessage());
            return false;
        }
    }

    /**
     * Отправить уведомление об ошибке в группу
     */
    private function sendErrorNotification(int $chatId, string $message): void
    {
        try {
            $this->telegram->sendMessage($chatId, $message);
        } catch (\Exception $e) {
            // Если не удалось отправить уведомление, просто логируем
            Log::warning('Failed to send error notification to chat', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Отправить уведомление владельцу об ошибке на стороне бота
     */
    private function notifyOwnerAboutError(int $chatId, string $errorType, string $errorMessage): void
    {
        try {
            $chatInfo = $this->telegram->getChat($chatId);
            $chatTitle = $chatInfo['title'] ?? "Chat {$chatId}";
            
            $message = "🔴 <b>Ошибка при запуске викторины</b>\n\n";
            $message .= "📊 <b>Чат:</b> {$chatTitle} (ID: {$chatId})\n";
            $message .= "⚠️ <b>Тип ошибки:</b> {$errorType}\n";
            $message .= "📝 <b>Описание:</b> {$errorMessage}\n";
            $message .= "\n⏰ <b>Время:</b> " . now()->format('d.m.Y H:i:s');
            
            $this->telegram->sendMessageToOwner($message);
        } catch (\Exception $e) {
            Log::warning('Failed to notify owner about error', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Подготовить ответы для вопроса в зависимости от типа
     */
    private function prepareAnswersForQuestion(Question $question): array
    {
        switch ($question->question_type) {
            case Question::TYPE_MULTIPLE_CHOICE:
                return $question->getShuffledAnswers();
            
            case Question::TYPE_TRUE_FALSE:
                return ['Верно', 'Неверно'];
            
            case Question::TYPE_TEXT:
                return []; // Для текстовых вопросов не нужно сохранять порядок
            
            case Question::TYPE_IMAGE:
                // Для вопросов с изображением проверяем, есть ли варианты ответов
                $answers = $question->getShuffledAnswers();
                return !empty($answers) && count($answers) >= 2 ? $answers : [];
            
            default:
                return $question->getShuffledAnswers();
        }
    }

    /**
     * Отправить вопрос в зависимости от типа
     */
    private function sendQuestionByType(int $chatId, Question $question, ActiveQuiz $activeQuiz): ?array
    {
        $pointsText = match($question->difficulty) {
            Question::DIFFICULTY_EASY => '1 очко',
            Question::DIFFICULTY_MEDIUM => '3 очка',
            Question::DIFFICULTY_HARD => '5 очков',
            default => '3 очка',
        };

        switch ($question->question_type) {
            case Question::TYPE_MULTIPLE_CHOICE:
                return $this->sendMultipleChoiceQuestion($chatId, $question, $pointsText);
            
            case Question::TYPE_TRUE_FALSE:
                return $this->sendTrueFalseQuestion($chatId, $question, $pointsText);
            
            case Question::TYPE_IMAGE:
                return $this->sendImageQuestion($chatId, $question, $pointsText);
            
            case Question::TYPE_TEXT:
            default:
                return $this->sendTextQuestion($chatId, $question, $pointsText);
        }
    }

    /**
     * Отправить вопрос с выбором из вариантов (кнопки)
     */
    private function sendMultipleChoiceQuestion(int $chatId, Question $question, string $pointsText): ?array
    {
        $answers = $question->getShuffledAnswers();
        
        if (empty($answers) || count($answers) < 2) {
            // Если нет вариантов, отправить как текстовый вопрос
            return $this->sendTextQuestion($chatId, $question, $pointsText);
        }
        
        // Создать кнопки (по 2 кнопки в ряд для компактности)
        $buttons = [];
        $currentRow = [];
        foreach ($answers as $index => $answer) {
            $currentRow[] = [
                'text' => ($index + 1) . '. ' . $answer,
                'callback_data' => "quiz_answer_{$question->id}_{$index}",
            ];
            
            // Добавляем по 2 кнопки в ряд
            if (count($currentRow) >= 2 || $index === count($answers) - 1) {
                $buttons[] = $currentRow;
                $currentRow = [];
            }
        }

        $message = "<b>🎮 Вопрос по Dota 2!</b>\n\n";
        $message .= "❓ " . $question->question . "\n\n";
        $message .= "⏱ У вас есть <b>20 секунд</b> на ответ!\n";
        $message .= "💰 За правильный ответ: <b>{$pointsText}</b>";

        return $this->telegram->sendMessageWithButtons($chatId, $message, $buttons);
    }

    /**
     * Отправить вопрос Верно/Неверно
     */
    private function sendTrueFalseQuestion(int $chatId, Question $question, string $pointsText): ?array
    {
        $buttons = [
            [
                ['text' => '✅ Верно', 'callback_data' => "quiz_answer_{$question->id}_true"],
                ['text' => '❌ Неверно', 'callback_data' => "quiz_answer_{$question->id}_false"],
            ]
        ];

        $message = "<b>🎮 Вопрос по Dota 2!</b>\n\n";
        $message .= "❓ " . $question->question . "\n\n";
        $message .= "⏱ У вас есть <b>20 секунд</b> на ответ!\n";
        $message .= "💰 За правильный ответ: <b>{$pointsText}</b>";

        return $this->telegram->sendMessageWithButtons($chatId, $message, $buttons);
    }

    /**
     * Отправить вопрос с изображением
     */
    private function sendImageQuestion(int $chatId, Question $question, string $pointsText): ?array
    {
        // Приоритет: file_id > image_url
        $photo = $question->image_file_id ?? $question->image_url;
        
        if (!$photo) {
            // Если нет изображения, отправить как текстовый вопрос
            return $this->sendTextQuestion($chatId, $question, $pointsText);
        }

        // Если это локальный файл (относительный путь), преобразовать в полный URL
        if (strpos($photo, 'storage/questions/') === 0 && !filter_var($photo, FILTER_VALIDATE_URL)) {
            $photo = asset($photo);
        }

        $caption = "<b>🎮 Вопрос по Dota 2!</b>\n\n";
        $caption .= "❓ " . $question->question . "\n\n";
        $caption .= "⏱ У вас есть <b>20 секунд</b> на ответ!\n";
        $caption .= "💰 За правильный ответ: <b>{$pointsText}</b>";

        // Проверить, есть ли варианты ответов
        $answers = $question->getShuffledAnswers();
        
        if (!empty($answers) && count($answers) >= 2) {
            // Если есть варианты ответов, добавить кнопки
            $buttons = [];
            $currentRow = [];
            foreach ($answers as $index => $answer) {
                $currentRow[] = [
                    'text' => ($index + 1) . '. ' . $answer,
                    'callback_data' => "quiz_answer_{$question->id}_{$index}",
                ];
                
                // Добавляем по 2 кнопки в ряд
                if (count($currentRow) >= 2 || $index === count($answers) - 1) {
                    $buttons[] = $currentRow;
                    $currentRow = [];
                }
            }
            
            // Отправить изображение с кнопками
            return $this->telegram->sendPhotoWithButtons($chatId, $photo, $caption, $buttons);
        } else {
            // Если нет вариантов, отправить как текстовый вопрос (без кнопок)
            $caption .= "\n💬 Напишите ваш ответ текстом";
            return $this->telegram->sendPhoto($chatId, $photo, $caption);
        }
    }

    /**
     * Отправить текстовый вопрос
     */
    private function sendTextQuestion(int $chatId, Question $question, string $pointsText): ?array
    {
        $answers = $question->getShuffledAnswers();
        $answersText = '';
        
        if (!empty($answers)) {
            $answersText = "\n\nВарианты ответов:\n";
            foreach ($answers as $index => $answer) {
                $answersText .= ($index + 1) . ". " . $answer . "\n";
            }
        }

        $message = "<b>🎮 Вопрос по Dota 2!</b>\n\n";
        $message .= "❓ " . $question->question;
        $message .= $answersText . "\n";
        $message .= "⏱ У вас есть <b>20 секунд</b> на ответ!\n";
        $message .= "💬 Напишите номер ответа (1, 2, 3...) или сам ответ\n";
        $message .= "💰 За правильный ответ: <b>{$pointsText}</b>";

        return $this->telegram->sendMessage($chatId, $message);
    }

    /**
     * Обработать текстовый ответ пользователя
     */
    public function processAnswer(int $activeQuizId, int $userId, string $username, string $firstName, string $answerText, ?int $messageId = null, ?int $chatId = null): void
    {
        $this->processAnswerInternal($activeQuizId, $userId, $username, $firstName, $answerText, null, null, $messageId, $chatId);
    }

    /**
     * Обработать ответ через callback (кнопка)
     */
    public function processAnswerWithCallback(int $activeQuizId, int $userId, string $username, string $firstName, string $callbackData, string $callbackQueryId, ?int $messageId = null, ?int $chatId = null): void
    {
        $this->processAnswerInternal($activeQuizId, $userId, $username, $firstName, '', $callbackData, $callbackQueryId, $messageId, $chatId);
    }

    /**
     * Внутренний метод обработки ответа
     */
    private function processAnswerInternal(int $activeQuizId, int $userId, string $username, string $firstName, string $answerText, ?string $callbackData = null, ?string $callbackQueryId = null, ?int $messageId = null, ?int $chatId = null): void
    {
        Log::info('processAnswerInternal called', [
            'active_quiz_id' => $activeQuizId,
            'user_id' => $userId,
            'answer_text' => $answerText,
            'callback_data' => $callbackData,
        ]);
        
        try {
            $activeQuiz = ActiveQuiz::with('question')->find($activeQuizId);
            
            // Обновить correct_answer_index из БД, если он не загружен
            if ($activeQuiz && $activeQuiz->correct_answer_index === null) {
                $rawData = DB::table('active_quizzes')
                    ->where('id', $activeQuizId)
                    ->first(['correct_answer_index']);
                if ($rawData && $rawData->correct_answer_index !== null) {
                    $activeQuiz->correct_answer_index = $rawData->correct_answer_index;
                }
            }

            if (!$activeQuiz) {
                Log::warning('ActiveQuiz not found', ['active_quiz_id' => $activeQuizId]);
                $errorMessage = '❌ Викторина уже завершена. Ваш ответ не зарегистрирован.';
                if ($callbackQueryId) {
                    // Уже ответили выше, но отправляем уведомление об ошибке
                    try {
                        $this->telegram->answerCallbackQuery($callbackQueryId, $errorMessage, true);
                    } catch (\Exception $e) {
                        Log::debug('Callback query already answered', ['error' => $e->getMessage()]);
                    }
                } elseif ($chatId) {
                    try {
                        $this->telegram->sendMessage(
                            $chatId,
                            $errorMessage,
                            ['parse_mode' => 'HTML']
                        );
                    } catch (\Exception $e) {
                        Log::warning('Failed to send error notification', ['error' => $e->getMessage()]);
                    }
                }
                return;
            }

            // Прочитать сырые значения из БД для точной проверки времени
            $rawData = DB::table('active_quizzes')
                ->where('id', $activeQuizId)
                ->first(['started_at', 'expires_at', 'is_active']);
            
            $startedAt = Carbon::createFromFormat('Y-m-d H:i:s', $rawData->started_at, 'UTC');
            $expiresAt = Carbon::createFromFormat('Y-m-d H:i:s', $rawData->expires_at, 'UTC');
            $now = Carbon::now('UTC');
            
            // Обновить объект для дальнейшего использования
            $activeQuiz->started_at = $startedAt;
            $activeQuiz->expires_at = $expiresAt;
            $activeQuiz->is_active = (bool)$rawData->is_active;

            Log::info('Checking quiz status for answer', [
                'active_quiz_id' => $activeQuizId,
                'is_active' => $activeQuiz->is_active,
                'started_at' => $startedAt->format('Y-m-d H:i:s T'),
                'expires_at' => $expiresAt->format('Y-m-d H:i:s T'),
                'now' => $now->format('Y-m-d H:i:s T'),
                'is_expired' => $expiresAt->lessThanOrEqualTo($now),
                'time_remaining_seconds' => max(0, $now->diffInSeconds($expiresAt, false)),
            ]);

            if (!$activeQuiz->is_active) {
                Log::warning('ActiveQuiz is not active', [
                    'active_quiz_id' => $activeQuizId,
                    'is_active' => $activeQuiz->is_active,
                ]);
                $errorMessage = '❌ Викторина уже завершена. Ваш ответ не зарегистрирован.';
                if ($callbackQueryId) {
                    $this->telegram->answerCallbackQuery($callbackQueryId, $errorMessage, true);
                } elseif ($messageId && $chatId) {
                    try {
                        $this->telegram->sendMessage(
                            $chatId,
                            $errorMessage,
                            ['parse_mode' => 'HTML']
                        );
                    } catch (\Exception $e) {
                        Log::warning('Failed to send error notification', ['error' => $e->getMessage()]);
                    }
                }
                return;
            }

            // Проверить истечение времени с использованием UTC
            // ВАЖНО: использовать lessThanOrEqualTo вместо isPast, чтобы точно определить истечение
            // Викторина считается истекшей, если expires_at <= now
            $isExpired = $expiresAt->lessThanOrEqualTo($now);
            
            if ($isExpired) {
                Log::warning('❌ ActiveQuiz expired - ANSWER WILL NOT BE SAVED', [
                    'active_quiz_id' => $activeQuizId,
                    'expires_at' => $expiresAt->format('Y-m-d H:i:s T'),
                    'now' => $now->format('Y-m-d H:i:s T'),
                    'time_past_seconds' => abs($now->diffInSeconds($expiresAt, false)),
                ]);
                $errorMessage = '⏰ Время на ответ истекло! Ваш ответ не зарегистрирован.';
                if ($callbackQueryId) {
                    $this->telegram->answerCallbackQuery($callbackQueryId, $errorMessage, true);
                } elseif ($messageId && $chatId) {
                    try {
                        $this->telegram->sendMessage(
                            $chatId,
                            $errorMessage,
                            ['parse_mode' => 'HTML']
                        );
                    } catch (\Exception $e) {
                        Log::warning('Failed to send error notification', ['error' => $e->getMessage()]);
                    }
                }
                return;
            }
            
            Log::info('✅ Quiz is active - PROCEEDING WITH ANSWER PROCESSING', [
                'active_quiz_id' => $activeQuizId,
                'expires_at' => $expiresAt->format('Y-m-d H:i:s T'),
                'now' => $now->format('Y-m-d H:i:s T'),
                'time_remaining_seconds' => max(0, $now->diffInSeconds($expiresAt, false)),
            ]);

            // Проверить, не ответил ли уже этот пользователь
            $existingResult = QuizResult::where('active_quiz_id', $activeQuizId)
                ->where('user_id', $userId)
                ->first();

            if ($existingResult) {
                $errorMessage = '⚠️ Вы уже ответили на этот вопрос! Ваш ответ не зарегистрирован повторно.';
                if ($callbackQueryId) {
                    $this->telegram->answerCallbackQuery($callbackQueryId, $errorMessage, true);
                } elseif ($messageId && $chatId) {
                    try {
                        $this->telegram->sendMessage(
                            $chatId,
                            $errorMessage,
                            ['parse_mode' => 'HTML']
                        );
                    } catch (\Exception $e) {
                        Log::warning('Failed to send error notification', ['error' => $e->getMessage()]);
                    }
                }
                return;
            }

            $question = $activeQuiz->question;
            $answerText = trim($answerText);

            // Определить выбранный ответ и индекс (для вопросов с выбором)
            $selectedAnswer = null;
            $selectedAnswerIndex = null;
            
            if ($callbackData) {
                // Ответ через кнопку - получаем индекс напрямую
                $parsed = $this->parseCallbackAnswer($callbackData, $question, $activeQuiz);
                if ($parsed !== null) {
                    $selectedAnswerIndex = $parsed['index'];
                    $selectedAnswer = $parsed['answer'];
                    
                    // Логировать для отладки
                    try {
                        Log::info('Answer parsed from callback', [
                            'callback_data' => $callbackData,
                            'selected_index' => $selectedAnswerIndex,
                            'selected_answer' => $selectedAnswer,
                            'answers_order' => $activeQuiz->answers_order,
                        ]);
                    } catch (\Exception $logError) {
                        // Игнорируем ошибки логирования
                    }
                }
            } else {
                // Текстовый ответ
                $selectedAnswer = $this->parseTextAnswer($answerText, $question, $activeQuiz);
                // Для текстовых ответов находим индекс, если это вопрос с выбором
                if ($selectedAnswer && in_array($question->question_type, [Question::TYPE_MULTIPLE_CHOICE, Question::TYPE_TRUE_FALSE])) {
                    $answers = $activeQuiz->answers_order ?? [];
                    foreach ($answers as $index => $answer) {
                        if (mb_strtolower(trim($answer)) === mb_strtolower(trim($selectedAnswer))) {
                            $selectedAnswerIndex = $index;
                            break;
                        }
                    }
                }
            }

            if (!$selectedAnswer) {
                // Логировать неудачное распознавание ответа
                try {
                    Log::warning('Failed to parse quiz answer', [
                        'active_quiz_id' => $activeQuizId,
                        'user_id' => $userId,
                        'answer_text' => $answerText,
                        'question_type' => $question->question_type,
                        'callback_data' => $callbackData,
                    ]);
                } catch (\Exception $logError) {
                    // Игнорируем ошибки логирования
                }
                
                $errorMessage = '❌ Не удалось распознать ваш ответ. Ваш ответ не зарегистрирован.';
                if ($callbackQueryId) {
                    try {
                        $this->telegram->answerCallbackQuery($callbackQueryId, $errorMessage, true);
                    } catch (\Exception $e) {
                        // Игнорируем, если уже ответили
                    }
                } elseif ($messageId && $chatId) {
                    try {
                        $this->telegram->sendMessage(
                            $chatId,
                            $errorMessage,
                            ['parse_mode' => 'HTML']
                        );
                    } catch (\Exception $e) {
                        try {
                            Log::warning('Failed to send error notification', ['error' => $e->getMessage()]);
                        } catch (\Exception $logError) {
                            // Игнорируем ошибки логирования
                        }
                    }
                }
                return;
            }

            // Проверяем ответ по тексту (значению) - это надежнее, так как Telegram передает значение в том же формате
            // Сравниваем текст выбранного ответа с правильным ответом из БД
            $isCorrect = false;
            
            if (in_array($question->question_type, [Question::TYPE_MULTIPLE_CHOICE, Question::TYPE_TRUE_FALSE])) {
                // Для вопросов с выбором - сравниваем текст ответа с correct_answer_text
                // ВАЖНО: selectedAnswer уже содержит текст ответа из answers_order, который Telegram передал
                $correctAnswerText = $question->getCorrectAnswerText();
                
                // Нормализуем оба значения для сравнения (без учета регистра и пробелов)
                $selectedAnswerNormalized = mb_strtolower(trim($selectedAnswer));
                $correctAnswerNormalized = mb_strtolower(trim($correctAnswerText));
                
                $isCorrect = ($selectedAnswerNormalized === $correctAnswerNormalized);
                
                try {
                    Log::info('Answer check by text value', [
                        'selected_answer' => $selectedAnswer,
                        'selected_answer_normalized' => $selectedAnswerNormalized,
                        'selected_index' => $selectedAnswerIndex,
                        'correct_answer_text' => $correctAnswerText,
                        'correct_answer_normalized' => $correctAnswerNormalized,
                        'is_correct' => $isCorrect,
                        'type' => 'text_comparison',
                    ]);
                } catch (\Exception $logError) {
                    // Игнорируем ошибки логирования
                }
            } else {
                // Для текстовых вопросов сравниваем по тексту
                $isCorrect = $question->checkAnswer($selectedAnswer);
            }
            
            $responseTime = $now->diffInMilliseconds($startedAt);
            
            try {
                Log::info('Answer parsed and validated', [
                    'active_quiz_id' => $activeQuizId,
                    'user_id' => $userId,
                    'selected_answer' => $selectedAnswer,
                    'selected_answer_index' => $selectedAnswerIndex,
                    'correct_answer_text' => $question->getCorrectAnswerText(),
                    'is_correct' => $isCorrect,
                    'response_time_ms' => $responseTime,
                    'comparison_method' => 'by_text_value',
                ]);
            } catch (\Exception $logError) {
                // Игнорируем ошибки логирования
            }

            // ВАЖНО: Для callback query отправляем уведомление СРАЗУ с результатом
            // Это нужно сделать ДО сохранения в БД, чтобы убрать индикатор загрузки и показать результат
            // Это ускоряет отклик для пользователя
            if ($callbackQueryId) {
                $callbackText = $isCorrect 
                    ? "✅ Ваш ответ зарегистрирован! Правильно!"
                    : "❌ Ваш ответ зарегистрирован. Неправильно.";
                try {
                    // Отправляем уведомление с результатом СРАЗУ после проверки
                    // Это уберет индикатор загрузки и покажет результат пользователю
                    $this->telegram->answerCallbackQuery($callbackQueryId, $callbackText, true);
                } catch (\Exception $e) {
                    // Критическая ошибка - не удалось отправить уведомление
                    try {
                        Log::error('Failed to send callback notification', [
                            'callback_query_id' => $callbackQueryId,
                            'error' => $e->getMessage(),
                        ]);
                    } catch (\Exception $logError) {
                        // Игнорируем ошибки логирования
                    }
                }
            }

            // Сохранить результат (после отправки уведомления для ускорения)
            // Сохраняем текст ответа (значение) - это надежнее, так как Telegram передает значение в том же формате
            // Индекс сохраняем только для справки, но сравнение делаем по тексту
            $answerToSave = $selectedAnswer; // Сохраняем текст ответа
            
            $result = QuizResult::create([
                'active_quiz_id' => $activeQuizId,
                'user_id' => $userId,
                'username' => $username,
                'first_name' => $firstName,
                'answer' => $answerToSave,
                'is_correct' => $isCorrect,
                'response_time_ms' => $responseTime,
            ]);
            
            // Логировать сохранение ответа для отладки
            try {
                Log::info('Quiz answer saved', [
                    'active_quiz_id' => $activeQuizId,
                    'user_id' => $userId,
                    'answer_saved' => $answerToSave,
                    'answer_text' => $selectedAnswer,
                    'is_correct' => $isCorrect,
                    'result_id' => $result->id,
                ]);
            } catch (\Exception $logError) {
                // Игнорируем ошибки логирования
            }

            // Если это правильный ответ и первый в викторине
            if ($isCorrect) {
                $isFirstCorrect = QuizResult::where('active_quiz_id', $activeQuizId)
                    ->where('is_correct', true)
                    ->where('id', '<', $result->id)
                    ->doesntExist();

                if ($isFirstCorrect) {
                    // Начислить очки
                    $points = $question->getPointsForAnswer();
                    $this->addPointsToUser($activeQuiz->chat_id, $userId, $username, $firstName, $points);
                    
                    // Увеличить счетчик первых мест
                    $userScore = UserScore::where('chat_id', $activeQuiz->chat_id)
                        ->where('user_id', $userId)
                        ->first();
                    if ($userScore) {
                        $userScore->incrementFirstPlace();
                    }
                }
            }

            // Для текстовых ответов отправляем уведомление в группу
            if (!$callbackQueryId) {
                // Для текстовых ответов - простое уведомление, что ответ зарегистрирован
                // В Telegram нет способа показать всплывающее уведомление для текстовых сообщений
                // Поэтому отправляем короткое сообщение в группу
                try {
                    $emoji = $isCorrect ? '✅' : '❌';
                    $message = $isCorrect 
                        ? "{$emoji} Правильно!" 
                        : "{$emoji} Неправильно";
                    
                    // Отправить короткое сообщение в группу (без reply)
                    $this->telegram->sendMessage(
                        $activeQuiz->chat_id,
                        $message,
                        [
                            'parse_mode' => 'HTML',
                        ]
                    );
                } catch (\Exception $e) {
                    Log::warning('Failed to send text answer notification', [
                        'user_id' => $userId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Логировать ответ
            $this->analytics->logAnswer($activeQuiz->chat_id, $userId, $isCorrect, $responseTime);

        } catch (\Exception $e) {
            Log::error('Process answer error', [
                'active_quiz_id' => $activeQuizId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            $this->analytics->logError("Process answer error: " . $e->getMessage());
            
            // Отправить уведомление об ошибке пользователю
            $errorMessage = '❌ Произошла ошибка при регистрации ответа. Попробуйте еще раз.';
            try {
                if ($callbackQueryId) {
                    $this->telegram->answerCallbackQuery($callbackQueryId, $errorMessage, true);
                } elseif (isset($chatId) && $chatId) {
                    $this->telegram->sendMessage(
                        $chatId,
                        $errorMessage,
                        ['parse_mode' => 'HTML']
                    );
                }
            } catch (\Exception $notifyError) {
                Log::warning('Failed to send error notification to user', [
                    'error' => $notifyError->getMessage(),
                ]);
            }
        }
    }

    /**
     * Распознать ответ из callback_data
     * Возвращает массив с 'index' и 'answer' или null
     */
    private function parseCallbackAnswer(string $callbackData, Question $question, ActiveQuiz $activeQuiz): ?array
    {
        // Формат: quiz_answer_{question_id}_{answer_index} или quiz_answer_{question_id}_{true/false}
        if (preg_match('/quiz_answer_(\d+)_(.+)/', $callbackData, $matches)) {
            $questionId = (int) $matches[1];
            $answerPart = $matches[2];
            
            // Проверить, что это правильный вопрос
            if ($questionId !== $question->id) {
                return null;
            }
            
            if ($question->question_type === Question::TYPE_TRUE_FALSE) {
                // Для вопросов Верно/Неверно
                $answers = ['Верно', 'Неверно'];
                if ($answerPart === 'true') {
                    return ['index' => 0, 'answer' => 'Верно'];
                } elseif ($answerPart === 'false') {
                    return ['index' => 1, 'answer' => 'Неверно'];
                }
            } else {
                // Для вопросов с выбором - answerPart это индекс
                $answers = $activeQuiz->answers_order ?? $question->getShuffledAnswers();
                if (empty($answers)) {
                    $answers = $question->getShuffledAnswers();
                }
                $index = (int) $answerPart;
                if ($index >= 0 && $index < count($answers)) {
                    // ВАЖНО: Возвращаем ТЕКСТ ответа, который пользователь выбрал
                    // Это значение будет сравниваться с correct_answer_text
                    $answerText = $answers[$index];
                    return ['index' => $index, 'answer' => $answerText];
                }
            }
        }
        
        return null;
    }

    /**
     * Распознать текстовый ответ
     */
    private function parseTextAnswer(string $answerText, Question $question, ActiveQuiz $activeQuiz): ?string
    {
        $originalAnswerText = trim($answerText);
        $answerText = mb_strtolower($originalAnswerText);

        // Для вопросов Верно/Неверно
        if ($question->question_type === Question::TYPE_TRUE_FALSE) {
            if (in_array($answerText, ['верно', 'да', 'true', '1', 'да', '✓', '✅'])) {
                return 'Верно';
            } elseif (in_array($answerText, ['неверно', 'нет', 'false', '0', 'нет', '✗', '❌'])) {
                return 'Неверно';
            }
            Log::info('True/False answer not recognized', [
                'answer_text' => $originalAnswerText,
                'lowercase' => $answerText,
            ]);
            return null;
        }

        // Для вопросов с вариантами ответов
        // ВАЖНО: использовать сохраненный порядок из ActiveQuiz, а не генерировать новый!
        $answers = $activeQuiz->answers_order;
        
        // Если answers_order пустой или null, попробовать получить из вопроса
        if (empty($answers)) {
            Log::warning('answers_order is empty, using question shuffled answers', [
                'active_quiz_id' => $activeQuiz->id,
                'question_id' => $question->id,
            ]);
            $answers = $question->getShuffledAnswers();
        }
        
        // Логировать для диагностики
        Log::info('Parsing text answer', [
            'active_quiz_id' => $activeQuiz->id,
            'question_type' => $question->question_type,
            'answer_text' => $originalAnswerText,
            'answers_count' => count($answers),
            'answers' => $answers,
            'answers_order_from_db' => $activeQuiz->answers_order,
        ]);
        
        // Попытка найти по номеру (1, 2, 3...)
        if (is_numeric($answerText)) {
            $index = (int) $answerText - 1;
            if ($index >= 0 && $index < count($answers)) {
                Log::info('Answer found by number', [
                    'number' => $answerText,
                    'index' => $index,
                    'selected_answer' => $answers[$index],
                ]);
                return $answers[$index];
            } else {
                Log::info('Answer number out of range', [
                    'number' => $answerText,
                    'index' => $index,
                    'answers_count' => count($answers),
                ]);
            }
        }

        // Попытка найти по тексту (точное совпадение)
        foreach ($answers as $answer) {
            if (mb_strtolower(trim($answer)) === $answerText) {
                Log::info('Answer found by exact match', [
                    'user_answer' => $answerText,
                    'matched_answer' => $answer,
                ]);
                return $answer;
            }
        }

        // Попытка найти по частичному совпадению
        foreach ($answers as $answer) {
            if (mb_strpos(mb_strtolower($answer), $answerText) !== false) {
                Log::info('Answer found by partial match', [
                    'user_answer' => $answerText,
                    'matched_answer' => $answer,
                ]);
                return $answer;
            }
        }

        // Для текстовых вопросов - вернуть оригинальный текст ответа (не в нижнем регистре)
        if ($question->question_type === Question::TYPE_TEXT || $question->question_type === Question::TYPE_IMAGE) {
            // Вернуть оригинальный текст ответа пользователя (до преобразования в нижний регистр)
            // checkAnswer сам сделает нормализацию для сравнения
            Log::info('Returning original text for TEXT/IMAGE question', [
                'answer_text' => $originalAnswerText,
            ]);
            return $originalAnswerText;
        }

        Log::info('Answer not recognized', [
            'answer_text' => $originalAnswerText,
            'question_type' => $question->question_type,
            'answers' => $answers,
        ]);
        return null;
    }

    /**
     * Добавить очки пользователю
     */
    private function addPointsToUser(int $chatId, int $userId, ?string $username, ?string $firstName, int $points): void
    {
        $userScore = UserScore::firstOrCreate(
            [
                'user_id' => $userId,
                'chat_id' => $chatId,
            ],
            [
                'username' => $username,
                'first_name' => $firstName,
                'total_points' => 0,
                'correct_answers' => 0,
                'total_answers' => 0,
                'last_activity_at' => now(),
            ]
        );

        $userScore->addPoints($points, true);
        
        // Обновить профиль пользователя (ранг)
        try {
            $profile = \App\Models\UserProfile::getOrCreate($userId);
            $profile->updateTotalPoints();
        } catch (\Exception $e) {
            // Игнорируем ошибки обновления профиля
        }
    }

    /**
     * Завершить викторину и показать результаты
     */
    public function finishQuiz(int $activeQuizId): void
    {
        try {
            $activeQuiz = ActiveQuiz::with(['question', 'results'])->find($activeQuizId);

            if (!$activeQuiz || !$activeQuiz->is_active) {
                return;
            }

            // Деактивировать викторину
            $activeQuiz->update(['is_active' => false]);

            $question = $activeQuiz->question;
            
            // Перезагрузить результаты из БД с загрузкой activeQuiz для получения answers_order
            $results = QuizResult::with('activeQuiz')->where('active_quiz_id', $activeQuizId)->get();
            
            // Логировать количество найденных результатов
            Log::info('Finishing quiz', [
                'active_quiz_id' => $activeQuizId,
                'chat_id' => $activeQuiz->chat_id,
                'results_count' => $results->count(),
                'results' => $results->map(function($r) {
                    return [
                        'user_id' => $r->user_id,
                        'answer' => $r->answer,
                        'answer_text' => $r->getAnswerText(),
                        'is_correct' => $r->is_correct,
                    ];
                })->toArray(),
            ]);

            // Подсчитать статистику
            $totalAnswers = $results->count();
            $correctAnswers = $results->where('is_correct', true)->count();
            $firstCorrectUser = $results->where('is_correct', true)
                ->sortBy('response_time_ms')
                ->first();

            // Сформировать сообщение с результатами
            $message = "<b>⏱ Время вышло!</b>\n\n";
            try {
                $correctAnswerText = $question->getCorrectAnswerText();
                $message .= "<b>Правильный ответ:</b> " . $correctAnswerText . "\n\n";
            } catch (\Exception $e) {
                // Fallback если метод не работает
                try {
                    $correctAnswerText = $question->correct_answer_text ?? $question->correct_answer ?? 'Не указан';
                    $message .= "<b>Правильный ответ:</b> " . $correctAnswerText . "\n\n";
                } catch (\Exception $e2) {
                    $message .= "<b>Правильный ответ:</b> Не указан\n\n";
                }
            }

            if ($totalAnswers > 0) {
                $message .= "📊 <b>Статистика:</b>\n";
                $message .= "Всего ответов: {$totalAnswers}\n";
                $message .= "Правильных: {$correctAnswers}\n";
                $message .= "Неправильных: " . ($totalAnswers - $correctAnswers) . "\n\n";

                if ($firstCorrectUser) {
                    $timeSeconds = number_format($firstCorrectUser->response_time_ms / 1000, 2);
                    $userName = $firstCorrectUser->first_name ?? $firstCorrectUser->username ?? "Пользователь";
                    // Форматируем имя с рангом, если пользователь включил отображение
                    $userName = \App\Models\UserProfile::formatUserName($firstCorrectUser->user_id, $userName);
                    $points = $question->getPointsForAnswer();
                    $message .= "🏆 <b>Победитель (первый правильный ответ):</b>\n";
                    $message .= "{$userName} ({$timeSeconds} сек.) - получил <b>{$points} очков</b>\n\n";
                }

                // Показать всех, кто ответил правильно (топ 5)
                $correctUsers = $results->where('is_correct', true)
                    ->sortBy('response_time_ms')
                    ->take(5);
                    
                if ($correctUsers->count() > 0) {
                    $message .= "✅ <b>Правильно ответили (топ 5):</b>\n";
                    foreach ($correctUsers as $index => $result) {
                        $userName = $result->first_name ?? $result->username ?? "Пользователь";
                        // Форматируем имя с рангом, если пользователь включил отображение
                        $userName = \App\Models\UserProfile::formatUserName($result->user_id, $userName);
                        $timeSeconds = number_format($result->response_time_ms / 1000, 2);
                        $place = $index + 1;
                        $message .= "{$place}. {$userName} ({$timeSeconds} сек.)\n";
                    }
                }
            } else {
                $message .= "😔 Никто не успел ответить";
            }

            // Отправить результаты
            $this->telegram->sendMessage($activeQuiz->chat_id, $message);

            // Обновить статистику чата
            $this->updateChatStatisticsAfterQuiz($activeQuiz->chat_id, $totalAnswers, $correctAnswers, $results->pluck('user_id')->unique()->count());

        } catch (\Exception $e) {
            Log::error('Finish quiz error', [
                'active_quiz_id' => $activeQuizId,
                'error' => $e->getMessage(),
            ]);
            $this->analytics->logError("Finish quiz error: " . $e->getMessage());
        }
    }

    /**
     * Обновить статистику чата
     */
    private function updateChatStatistics(int $chatId, string $chatType): void
    {
        $chatInfo = $this->telegram->getChat($chatId);
        $chatTitle = $chatInfo['title'] ?? null;
        
        ChatStatistics::getOrCreate($chatId, $chatType, $chatTitle);
    }

    /**
     * Обновить статистику чата после викторины
     */
    private function updateChatStatisticsAfterQuiz(int $chatId, int $totalAnswers, int $correctAnswers, int $uniqueParticipants): void
    {
        $statistics = ChatStatistics::where('chat_id', $chatId)->first();
        if ($statistics) {
            $statistics->updateAfterQuiz($totalAnswers, $correctAnswers, $uniqueParticipants);
        }
    }
}
