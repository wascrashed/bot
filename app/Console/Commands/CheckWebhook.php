<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class CheckWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:check-webhook';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Проверить настройки webhook и получить информацию о последних обновлениях';

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegram): int
    {
        $this->info('=== Проверка Webhook ===');
        
        // Получить информацию о webhook
        $this->info("\n1. Информация о webhook:");
        try {
            $webhookInfo = $telegram->getWebhookInfo();
            
            if ($webhookInfo) {
                $url = $webhookInfo['url'] ?? 'не установлен';
                $hasCustomCertificate = $webhookInfo['has_custom_certificate'] ?? false;
                $pendingUpdateCount = $webhookInfo['pending_update_count'] ?? 0;
                $lastErrorDate = $webhookInfo['last_error_date'] ?? null;
                $lastErrorMessage = $webhookInfo['last_error_message'] ?? null;
                $maxConnections = $webhookInfo['max_connections'] ?? null;
                $allowedUpdates = $webhookInfo['allowed_updates'] ?? [];
                
                $this->line("   URL: {$url}");
                $this->line("   Ожидающих обновлений: {$pendingUpdateCount}");
                $this->line("   Максимум соединений: " . ($maxConnections ?? 'не указано'));
                $this->line("   Разрешенные обновления: " . (empty($allowedUpdates) ? 'все' : implode(', ', $allowedUpdates)));
                
                if ($lastErrorDate) {
                    $errorDate = date('Y-m-d H:i:s', $lastErrorDate);
                    $this->error("   ❌ Последняя ошибка: {$errorDate}");
                    $this->error("   Сообщение: {$lastErrorMessage}");
                } else {
                    $this->info("   ✅ Ошибок нет");
                }
                
                if ($pendingUpdateCount > 0) {
                    $this->warn("   ⚠️ Есть {$pendingUpdateCount} необработанных обновлений!");
                    $this->info("   Это может означать, что webhook работает, но обновления не обрабатываются.");
                }
                
                if (empty($url) || $url === '') {
                    $this->error("   ❌ Webhook не установлен!");
                    $this->info("   Установите webhook: php artisan telegram:set-webhook <url>");
                }
            } else {
                $this->error("   ❌ Не удалось получить информацию о webhook");
            }
        } catch (\Exception $e) {
            $this->error("   ❌ Ошибка при проверке webhook: " . $e->getMessage());
        }
        
        // Проверить последние логи
        $this->info("\n2. Последние события webhook в логах:");
        $logPath = storage_path('logs/laravel.log');
        if (file_exists($logPath)) {
            $lines = file($logPath);
            $lastLines = array_slice($lines, -100);
            $webhookEvents = [];
            
            foreach ($lastLines as $line) {
                if (stripos($line, 'WEBHOOK UPDATE RECEIVED') !== false ||
                    stripos($line, 'webhook received') !== false ||
                    stripos($line, 'message received in group') !== false ||
                    stripos($line, '/status command') !== false ||
                    stripos($line, 'handleMessage called') !== false) {
                    $webhookEvents[] = trim($line);
                }
            }
            
            if (empty($webhookEvents)) {
                $this->warn("   ⚠️ Событий webhook не найдено в последних 100 строках");
                $this->warn("   Это означает, что бот НЕ получает обновления от Telegram!");
                $this->info('');
                $this->info('💡 Возможные причины:');
                $this->line('   1. Webhook не установлен или установлен неправильно');
                $this->line('   2. URL webhook недоступен с серверов Telegram');
                $this->line('   3. Privacy mode включен (но вы уже отключили)');
                $this->info('');
                $this->info('💡 Что проверить:');
                $this->line('   1. Проверьте webhook: php artisan telegram:check-webhook');
                $this->line('   2. Установите webhook: php artisan telegram:set-webhook <ваш_url>');
                $this->line('   3. Отправьте сообщение в группу и проверьте логи через 1-2 минуты');
            } else {
                $this->info("   ✅ Найдено событий: " . count($webhookEvents));
                $this->line("   Последние 5 событий:");
                foreach (array_slice($webhookEvents, -5) as $event) {
                    $this->line("   " . substr($event, 0, 150));
                }
            }
        } else {
            $this->warn("   ⚠️ Файл laravel.log не найден");
        }
        
        // Проверить webhook_debug.log если есть
        $debugLogPath = storage_path('logs/webhook_debug.log');
        if (file_exists($debugLogPath)) {
            $this->info("\n3. Дополнительные логи webhook:");
            $debugLines = file($debugLogPath);
            $lastDebugLines = array_slice($debugLines, -10);
            foreach ($lastDebugLines as $line) {
                $this->line("   " . trim($line));
            }
        }
        
        return Command::SUCCESS;
    }
}
