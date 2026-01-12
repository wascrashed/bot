<?php

namespace App\Console\Commands;

use App\Models\ActiveQuiz;
use App\Services\QuizService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class StartRandomQuiz extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quiz:start-random';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start a random quiz in all active group chats';

    /**
     * Execute the console command.
     */
    public function handle(QuizService $quizService): int
    {
        $this->info('Starting random quiz...');

        try {
            // Получить список уникальных чатов с активными викторинами или недавно проводившимися
            // Для простоты, можно использовать список известных чатов или получать из базы
            
            // Альтернативный подход: получать чаты из активных викторин за последние 24 часа
            $recentChats = ActiveQuiz::where('started_at', '>=', now()->subDay())
                ->select('chat_id', 'chat_type')
                ->distinct()
                ->get();

            if ($recentChats->isEmpty()) {
                $this->warn('No active chats found. Bot should be added to groups first.');
                $this->info('To start quizzes, add bot to a group and send any message to it.');
                return Command::SUCCESS;
            }

            $successCount = 0;

            // Оптимизация для работы с 50+ чатами: обработка батчами с задержкой
            if ($recentChats->count() > 50) {
                $this->info("Processing {$recentChats->count()} chats in batches to respect rate limits...");
                foreach ($recentChats->chunk(10) as $chunkIndex => $chunk) {
                    foreach ($chunk as $chat) {
                        try {
                            if ($quizService->startQuiz($chat->chat_id, $chat->chat_type)) {
                                $successCount++;
                                $this->info("Quiz started in chat {$chat->chat_id} ({$chat->chat_type})");
                            } else {
                                $this->warn("Failed to start quiz in chat {$chat->chat_id}");
                            }
                        } catch (\Exception $e) {
                            $this->error("Error starting quiz in chat {$chat->chat_id}: " . $e->getMessage());
                            Log::error("Error starting quiz", [
                                'chat_id' => $chat->chat_id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                    // Задержка между батчами для соблюдения rate limits (кроме последнего батча)
                    if (($chunkIndex + 1) * 10 < $recentChats->count()) {
                        usleep(500000); // 0.5 секунды
                    }
                }
            } else {
                foreach ($recentChats as $chat) {
                    try {
                        if ($quizService->startQuiz($chat->chat_id, $chat->chat_type)) {
                            $successCount++;
                            $this->info("Quiz started in chat {$chat->chat_id} ({$chat->chat_type})");
                        } else {
                            $this->warn("Failed to start quiz in chat {$chat->chat_id}");
                        }
                    } catch (\Exception $e) {
                        $this->error("Error starting quiz in chat {$chat->chat_id}: " . $e->getMessage());
                        Log::error("Error starting quiz", [
                            'chat_id' => $chat->chat_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            $this->info("Successfully started {$successCount} quiz(es)");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            Log::error('Start random quiz error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }
}
