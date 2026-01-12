<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class GetTelegramBotInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:bot-info';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get Telegram bot information';

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegram): int
    {
        $this->info('Fetching bot information...');

        $info = $telegram->getMe();

        if ($info) {
            $this->info('Bot Information:');
            $this->line("ID: {$info['id']}");
            $this->line("Username: @{$info['username']}");
            $this->line("First Name: {$info['first_name']}");
            if (isset($info['last_name'])) {
                $this->line("Last Name: {$info['last_name']}");
            }
            if (isset($info['can_join_groups'])) {
                $this->line("Can Join Groups: " . ($info['can_join_groups'] ? 'Yes' : 'No'));
            }
            if (isset($info['can_read_all_group_messages'])) {
                $canRead = $info['can_read_all_group_messages'];
                $this->line("Can Read All Group Messages: " . ($canRead ? 'Yes' : 'No'));
                
                if (!$canRead) {
                    $this->warn('');
                    $this->warn('⚠️ ВНИМАНИЕ: Бот не может читать все сообщения в группах!');
                    $this->warn('Это означает, что бот НЕ будет получать сообщения пользователей.');
                    $this->info('');
                    $this->info('💡 Как исправить:');
                    $this->line('   1. Откройте @BotFather в Telegram');
                    $this->line('   2. Отправьте /mybots');
                    $this->line('   3. Выберите вашего бота');
                    $this->line('   4. Bot Settings → Group Privacy');
                    $this->line('   5. Выберите "Turn off" (отключить приватность)');
                    $this->info('');
                    $this->info('💡 После изменения настройки может потребоваться несколько минут для обновления.');
                    $this->info('   Проверьте еще раз через: php artisan telegram:bot-info');
                }
            }
            return Command::SUCCESS;
        } else {
            $this->error('Failed to get bot information. Check your bot token.');
            return Command::FAILURE;
        }
    }
}
