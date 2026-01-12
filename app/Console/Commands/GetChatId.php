<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class GetChatId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:get-id {chat_username? : Username чата (например: @mygroup или mygroup)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Получить Chat ID по username (работает только для публичных групп/каналов)';

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegramService): int
    {
        $username = $this->argument('chat_username');
        
        if (!$username) {
            $this->error('❌ Укажите username чата');
            $this->info('');
            $this->info('💡 Способы узнать Chat ID:');
            $this->line('   1. Отправьте боту команду /chatid в группе');
            $this->line('   2. Посмотрите в админке → Чаты');
            $this->line('   3. Используйте: php artisan chat:list');
            $this->info('');
            $this->info('💡 Для публичных групп/каналов можно использовать:');
            $this->line('   php artisan chat:get-id @username');
            return Command::FAILURE;
        }

        // Убрать @ если есть
        $username = ltrim($username, '@');
        
        $this->info("Получение информации о чате: @{$username}");

        try {
            // Попробовать получить информацию о чате через API
            $chatInfo = $telegramService->getChat('@' . $username);
            
            if ($chatInfo) {
                $chatId = $chatInfo['id'] ?? null;
                $chatTitle = $chatInfo['title'] ?? 'Без названия';
                $chatType = $chatInfo['type'] ?? 'unknown';
                
                if ($chatId) {
                    $this->info("✅ Чат найден:");
                    $this->line("   ID: {$chatId}");
                    $this->line("   Название: {$chatTitle}");
                    $this->line("   Тип: {$chatType}");
                    $this->line("   Username: @{$username}");
                    $this->info('');
                    $this->info('💡 Для регистрации чата используйте:');
                    $this->line("   php artisan chat:register {$chatId}");
                    return Command::SUCCESS;
                } else {
                    $this->error('❌ Не удалось получить ID чата');
                    return Command::FAILURE;
                }
            } else {
                $this->error('❌ Чат не найден или недоступен');
                $this->warn('Примечание: Этот метод работает только для публичных групп/каналов');
                $this->info('');
                $this->info('💡 Для приватных групп используйте команду /chatid в группе');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('❌ Ошибка: ' . $e->getMessage());
            $this->warn('Примечание: Этот метод работает только для публичных групп/каналов');
            $this->info('');
            $this->info('💡 Для приватных групп используйте команду /chatid в группе');
            return Command::FAILURE;
        }
    }
}
