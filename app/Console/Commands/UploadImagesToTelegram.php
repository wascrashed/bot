<?php

namespace App\Console\Commands;

use App\Models\Question;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UploadImagesToTelegram extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:upload-to-telegram {--question-id= : ID конкретного вопроса}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Загрузить изображения вопросов в Telegram и получить file_id для оптимизации';

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegramService): int
    {
        $testChatId = config('telegram.test_chat_id');
        
        if (!$testChatId) {
            $this->error('TELEGRAM_TEST_CHAT_ID не настроен в .env');
            $this->info('Добавьте в .env: TELEGRAM_TEST_CHAT_ID=ваш_личный_chat_id');
            $this->info('Чтобы узнать свой chat_id, напишите боту @userinfobot');
            return Command::FAILURE;
        }

        $query = Question::where('question_type', Question::TYPE_IMAGE)
            ->whereNull('image_file_id')
            ->whereNotNull('image_url');

        if ($this->option('question-id')) {
            $query->where('id', $this->option('question-id'));
        }

        $questions = $query->get();

        if ($questions->isEmpty()) {
            $this->info('Нет вопросов с изображениями без file_id');
            return Command::SUCCESS;
        }

        $this->info("Найдено {$questions->count()} вопросов для обработки");

        $successCount = 0;
        $failedCount = 0;

        foreach ($questions as $question) {
            try {
                $imagePath = null;
                
                // Определить путь к файлу
                if (strpos($question->image_url, 'storage/questions/') === 0) {
                    $imagePath = storage_path('app/public/' . $question->image_url);
                } elseif (filter_var($question->image_url, FILTER_VALIDATE_URL)) {
                    // Если это внешний URL, скачать временно
                    $tempPath = storage_path('app/temp_' . basename($question->image_url));
                    file_put_contents($tempPath, file_get_contents($question->image_url));
                    $imagePath = $tempPath;
                }

                if (!$imagePath || !file_exists($imagePath)) {
                    $this->warn("Вопрос #{$question->id}: файл не найден");
                    $failedCount++;
                    continue;
                }

                // Загрузить в Telegram
                $result = $telegramService->sendPhoto($testChatId, $imagePath, 'Upload for file_id');
                
                // sendPhoto возвращает массив message, в котором есть photo
                if ($result && isset($result['photo']) && is_array($result['photo'])) {
                    $photos = $result['photo'];
                    $largestPhoto = end($photos);
                    $fileId = $largestPhoto['file_id'] ?? null;
                    
                    if ($fileId) {
                        $question->image_file_id = $fileId;
                        $question->save();
                        $this->info("Вопрос #{$question->id}: file_id получен");
                        $successCount++;
                    } else {
                        $this->warn("Вопрос #{$question->id}: file_id не найден в ответе");
                        $failedCount++;
                    }
                } else {
                    $this->warn("Вопрос #{$question->id}: не удалось загрузить");
                    $failedCount++;
                }

                // Удалить временный файл, если был скачан
                if (isset($tempPath) && file_exists($tempPath)) {
                    unlink($tempPath);
                }

                // Небольшая задержка для соблюдения rate limits
                usleep(200000); // 0.2 секунды

            } catch (\Exception $e) {
                $this->error("Вопрос #{$question->id}: ошибка - " . $e->getMessage());
                $failedCount++;
                Log::error('Upload image to Telegram error', [
                    'question_id' => $question->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("\nГотово! Успешно: {$successCount}, Ошибок: {$failedCount}");

        return Command::SUCCESS;
    }
}
