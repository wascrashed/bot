<?php

namespace App\Console\Commands;

use App\Models\ActiveQuiz;
use App\Models\ChatStatistics;
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
            // Получить список всех чатов из ChatStatistics (не только с is_active = true)
            // Это чаты, где бот был добавлен
            $activeChats = ChatStatistics::select('chat_id', 'chat_type')
                ->get();

            // Если нет чатов в статистике, попробуем найти чаты с викторинами за последние 24 часа
            if ($activeChats->isEmpty()) {
                $recentChats = ActiveQuiz::where('started_at', '>=', now()->subDay())
                    ->select('chat_id', 'chat_type')
                    ->distinct()
                    ->get();
                
                if ($recentChats->isEmpty()) {
                    $this->warn('No active chats found. Bot should be added to groups first.');
                    $this->info('To start quizzes, add bot to a group and send any message to it.');
                    return Command::SUCCESS;
                }
                $activeChats = $recentChats;
            }
            
            $this->info("Found {$activeChats->count()} chat(s) to process");

            $successCount = 0;

            // Оптимизация для работы с 50+ чатами: обработка батчами с задержкой
            if ($activeChats->count() > 50) {
                $this->info("Processing {$activeChats->count()} chats in batches to respect rate limits...");
                foreach ($activeChats->chunk(10) as $chunkIndex => $chunk) {
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
                    if (($chunkIndex + 1) * 10 < $activeChats->count()) {
                        usleep(500000); // 0.5 секунды
                    }
                }
            } else {
                foreach ($activeChats as $chat) {
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
