<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CheckWebhookErrors extends Command
{
    protected $signature = 'webhook:check-errors {--lines=50 : Количество строк для проверки}';
    protected $description = 'Проверить последние ошибки webhook (500 ошибки)';

    public function handle(): int
    {
        $lines = (int)$this->option('lines');
        
        $this->info("=== Проверка ошибок webhook (последние {$lines} строк) ===\n");
        
        // Проверить основной лог
        $logPath = storage_path('logs/laravel.log');
        if (file_exists($logPath)) {
            $this->info("1. Ошибки в laravel.log:");
            $fileLines = file($logPath);
            $lastLines = array_slice($fileLines, -$lines);
            
            $errors = [];
            foreach ($lastLines as $line) {
                if (stripos($line, 'WEBHOOK ERROR') !== false ||
                    stripos($line, 'webhook error') !== false ||
                    stripos($line, '500') !== false ||
                    stripos($line, 'exception') !== false ||
                    stripos($line, 'fatal') !== false) {
                    $errors[] = trim($line);
                }
            }
            
            if (empty($errors)) {
                $this->info("   ✅ Ошибок не найдено");
            } else {
                $this->warn("   ⚠️ Найдено ошибок: " . count($errors));
                $this->line("   Последние 5 ошибок:");
                foreach (array_slice($errors, -5) as $error) {
                    $this->line("   " . substr($error, 0, 200));
                }
            }
        } else {
            $this->warn("   ⚠️ Файл laravel.log не найден");
        }
        
        $this->newLine();
        
        // Проверить webhook_errors.log
        $errorLogPath = storage_path('logs/webhook_errors.log');
        if (file_exists($errorLogPath)) {
            $this->info("2. Ошибки в webhook_errors.log:");
            $errorLines = file($errorLogPath);
            $lastErrors = array_slice($errorLines, -10);
            
            foreach ($lastErrors as $error) {
                $this->line("   " . trim($error));
            }
        } else {
            $this->info("   ℹ️ Файл webhook_errors.log не найден (ошибок не было)");
        }
        
        $this->newLine();
        
        // Проверить webhook_debug.log
        $debugLogPath = storage_path('logs/webhook_debug.log');
        if (file_exists($debugLogPath)) {
            $this->info("3. Отладочная информация в webhook_debug.log:");
            $debugLines = file($debugLogPath);
            $lastDebug = array_slice($debugLines, -5);
            
            foreach ($lastDebug as $debug) {
                $this->line("   " . trim($debug));
            }
        }
        
        return Command::SUCCESS;
    }
}
