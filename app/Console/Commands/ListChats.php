<?php

namespace App\Console\Commands;

use App\Models\ChatStatistics;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class ListChats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:list {--active : Показать только активные чаты (где бот присутствует)} {--check : Проверить через Telegram API, действительно ли бот в чате}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Показать список чатов, где находится бот';

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegramService): int
    {
        $showActiveOnly = $this->option('active');
        $checkViaApi = $this->option('check');

        $this->info('=== Список чатов ===');

        // Если включена проверка через API, сначала найдем чаты через API
        if ($checkViaApi) {
            $this->info('Поиск чатов через Telegram API...');
            $this->warn('⚠️ Примечание: Telegram API не предоставляет метод для получения списка всех чатов бота.');
            $this->info('Используются чаты из базы данных. Для регистрации нового чата отправьте сообщение в группу.\n');
        }

        $query = ChatStatistics::query();
        
        if ($showActiveOnly) {
            $query->where('is_active', true);
        }

        $chats = $query->orderBy('last_quiz_at', 'desc')->get();

        if ($chats->isEmpty()) {
            $this->warn('⚠️ Чаты не найдены в базе данных');
            $this->info('');
            $this->info('💡 Чтобы зарегистрировать чат:');
            $this->line('   1. Убедитесь, что бот добавлен в группу');
            $this->line('   2. Отправьте любое сообщение в группу');
            $this->line('   3. Или используйте команду: php artisan chat:register <chat_id>');
            $this->info('');
            $this->info('💡 Чтобы узнать ID чата, отправьте боту команду /chatid в группе');
            return Command::SUCCESS;
        }

        $this->info("Найдено чатов: {$chats->count()}\n");

        $tableData = [];
        $verifiedCount = 0;
        $notVerifiedCount = 0;

        foreach ($chats as $chat) {
            $status = $chat->is_active ? '✅ Активен' : '❌ Неактивен';
            $lastQuiz = $chat->last_quiz_at 
                ? $chat->last_quiz_at->format('d.m.Y H:i') 
                : 'Никогда';

            $row = [
                'ID' => $chat->chat_id,
                'Название' => $chat->chat_title ?? 'Без названия',
                'Тип' => $chat->chat_type,
                'Статус' => $status,
                'Викторин' => $chat->total_quizzes,
                'Последняя' => $lastQuiz,
            ];

            // Если нужно проверить через API
            if ($checkViaApi) {
                $this->line("Проверяю чат {$chat->chat_id}...");
                
                try {
                    $isMember = $telegramService->isBotMember($chat->chat_id);
                    
                    if ($isMember) {
                        $row['Проверка'] = '✅ В чате';
                        $verifiedCount++;
                        
                        // Обновить статус в БД, если он был неактивен
                        if (!$chat->is_active) {
                            $chat->is_active = true;
                            $chat->save();
                            $row['Статус'] = '✅ Активен (обновлен)';
                        }
                        
                        // Обновить название чата, если оно изменилось
                        $chatInfo = $telegramService->getChat($chat->chat_id);
                        if ($chatInfo && isset($chatInfo['title']) && $chatInfo['title'] !== $chat->chat_title) {
                            $chat->chat_title = $chatInfo['title'];
                            $chat->save();
                            $row['Название'] = $chatInfo['title'] . ' (обновлено)';
                        }
                    } else {
                        $row['Проверка'] = '❌ Не в чате';
                        $notVerifiedCount++;
                        
                        // Обновить статус в БД, если он был активен
                        if ($chat->is_active) {
                            $chat->is_active = false;
                            $chat->save();
                            $row['Статус'] = '❌ Неактивен (обновлен)';
                        }
                    }
                } catch (\Exception $e) {
                    $row['Проверка'] = '⚠️ Ошибка: ' . $e->getMessage();
                }
            }

            $tableData[] = $row;
        }

        $this->table(
            array_keys($tableData[0] ?? []),
            $tableData
        );

        if ($checkViaApi) {
            $this->info("\n📊 Результаты проверки:");
            $this->line("✅ Бот присутствует: {$verifiedCount}");
            $this->line("❌ Бот отсутствует: {$notVerifiedCount}");
        }

        $activeCount = $chats->where('is_active', true)->count();
        $inactiveCount = $chats->where('is_active', false)->count();
        
        $this->info("\n📈 Статистика:");
        $this->line("✅ Активных чатов: {$activeCount}");
        $this->line("❌ Неактивных чатов: {$inactiveCount}");

        return Command::SUCCESS;
    }
}
