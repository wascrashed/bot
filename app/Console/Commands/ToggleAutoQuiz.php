<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ToggleAutoQuiz extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quiz:auto-toggle {status? : on или off}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Включить/выключить автоматические викторины';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $status = $this->argument('status');
        
        // Используем кеш для хранения состояния (можно также использовать .env или базу данных)
        $currentStatus = Cache::get('auto_quiz_enabled', config('telegram.auto_quiz_enabled', true));
        
        if ($status === null) {
            // Показать текущий статус
            $statusText = $currentStatus ? 'включены' : 'выключены';
            $this->info("Автоматические викторины: {$statusText}");
            $this->info("Использование: php artisan quiz:auto-toggle on|off");
            return Command::SUCCESS;
        }
        
        $newStatus = in_array(strtolower($status), ['on', '1', 'true', 'yes', 'включить', 'enable']);
        
        Cache::forever('auto_quiz_enabled', $newStatus);
        
        $statusText = $newStatus ? 'включены' : 'выключены';
        $this->info("Автоматические викторины {$statusText}");
        
        // Очистить кеш конфигурации, чтобы изменения применились
        $this->call('config:clear');
        
        return Command::SUCCESS;
    }
}
