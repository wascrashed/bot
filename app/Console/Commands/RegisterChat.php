<?php

namespace App\Console\Commands;

use App\Models\ChatStatistics;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class RegisterChat extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:register {chat_id : ID чата для регистрации} {--type= : Тип чата (group/supergroup), по умолчанию определяется автоматически} {--title= : Название чата}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Зарегистрировать чат в базе данных (если бот в нем присутствует)';

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegramService): int
    {
        $chatId = $this->argument('chat_id');
        
        // Проверить, что chat_id - число
        if (!is_numeric($chatId)) {
            $this->error('❌ Chat ID должен быть числом');
            return Command::FAILURE;
        }

        $chatId = (int) $chatId;
        
        $this->info("Регистрация чата: {$chatId}");

        // Проверить через API, что бот действительно в чате
        $this->line('Проверяю через Telegram API...');
        $isMember = $telegramService->isBotMember($chatId);
        
        if (!$isMember) {
            $this->error('❌ Бот не является членом этого чата!');
            $this->warn('Добавьте бота в группу перед регистрацией.');
            return Command::FAILURE;
        }

        $this->info('✅ Бот присутствует в чате');

        // Получить информацию о чате через API
        $chatInfo = $telegramService->getChat($chatId);
        
        $chatType = $this->option('type');
        $chatTitle = $this->option('title');
        
        if (!$chatType && $chatInfo) {
            $chatType = $chatInfo['type'] ?? 'group';
        }
        $chatType = $chatType ?? 'group';
        
        if (!$chatTitle && $chatInfo) {
            $chatTitle = $chatInfo['title'] ?? null;
        }

        // Проверить, существует ли уже чат
        $existingChat = ChatStatistics::where('chat_id', $chatId)->first();
        
        if ($existingChat) {
            // Обновить существующий чат
            $existingChat->is_active = true;
            $existingChat->chat_type = $chatType;
            if ($chatTitle) {
                $existingChat->chat_title = $chatTitle;
            }
            $existingChat->save();
            
            $this->info("✅ Чат обновлен в базе данных");
            $this->line("   ID: {$existingChat->chat_id}");
            $this->line("   Название: " . ($existingChat->chat_title ?? 'Без названия'));
            $this->line("   Тип: {$existingChat->chat_type}");
            $this->line("   Статус: Активен");
        } else {
            // Создать новый чат
            $chat = ChatStatistics::create([
                'chat_id' => $chatId,
                'chat_type' => $chatType,
                'chat_title' => $chatTitle,
                'is_active' => true,
            ]);
            
            $this->info("✅ Чат зарегистрирован в базе данных");
            $this->line("   ID: {$chat->chat_id}");
            $this->line("   Название: " . ($chat->chat_title ?? 'Без названия'));
            $this->line("   Тип: {$chat->chat_type}");
            $this->line("   Статус: Активен");
        }

        return Command::SUCCESS;
    }
}
