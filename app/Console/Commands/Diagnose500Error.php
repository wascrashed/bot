<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Diagnose500Error extends Command
{
    protected $signature = 'webhook:diagnose-500';
    protected $description = 'Диагностика ошибки 500 в webhook';

    public function handle(): int
    {
        $this->info('=== Диагностика ошибки 500 в webhook ===\n');
        
        // 1. Проверить структуру таблицы questions
        $this->info('1. Проверка структуры таблицы questions:');
        try {
            $hasCorrectAnswerText = DB::select("SHOW COLUMNS FROM questions LIKE 'correct_answer_text'");
            if (empty($hasCorrectAnswerText)) {
                $this->error('   ❌ Колонка correct_answer_text ОТСУТСТВУЕТ!');
                $this->warn('   💡 Это может быть причиной ошибки 500!');
                $this->info('   💡 Выполните: php artisan migrate');
            } else {
                $this->info('   ✅ Колонка correct_answer_text существует');
            }
            
            $hasCorrectAnswer = DB::select("SHOW COLUMNS FROM questions LIKE 'correct_answer'");
            if (empty($hasCorrectAnswer)) {
                $this->error('   ❌ Колонка correct_answer ОТСУТСТВУЕТ!');
            } else {
                $this->info('   ✅ Колонка correct_answer существует');
            }
        } catch (\Exception $e) {
            $this->error('   ❌ Ошибка проверки: ' . $e->getMessage());
        }
        
        $this->newLine();
        
        // 2. Проверить структуру таблицы active_quizzes
        $this->info('2. Проверка структуры таблицы active_quizzes:');
        try {
            $hasCorrectAnswerIndex = DB::select("SHOW COLUMNS FROM active_quizzes LIKE 'correct_answer_index'");
            if (empty($hasCorrectAnswerIndex)) {
                $this->error('   ❌ Колонка correct_answer_index ОТСУТСТВУЕТ!');
                $this->warn('   💡 Это может быть причиной ошибки 500!');
                $this->info('   💡 Выполните: php artisan migrate');
            } else {
                $this->info('   ✅ Колонка correct_answer_index существует');
            }
        } catch (\Exception $e) {
            $this->error('   ❌ Ошибка проверки: ' . $e->getMessage());
        }
        
        $this->newLine();
        
        // 3. Проверить метод getCorrectAnswerText
        $this->info('3. Проверка метода getCorrectAnswerText:');
        try {
            $question = \App\Models\Question::first();
            if ($question) {
                $text = $question->getCorrectAnswerText();
                $this->info("   ✅ Метод работает, возвращает: " . substr($text, 0, 50));
            } else {
                $this->warn('   ⚠️ Нет вопросов в БД для проверки');
            }
        } catch (\Exception $e) {
            $this->error('   ❌ Ошибка при вызове метода: ' . $e->getMessage());
            $this->error('   💡 Это может быть причиной ошибки 500!');
        }
        
        $this->newLine();
        
        // 4. Проверить последние ошибки
        $this->info('4. Последние ошибки в логах:');
        $logPath = storage_path('logs/laravel.log');
        if (file_exists($logPath)) {
            $lines = file($logPath);
            $lastLines = array_slice($lines, -100);
            $errors = [];
            
            foreach ($lastLines as $line) {
                if (stripos($line, 'WEBHOOK ERROR') !== false ||
                    stripos($line, 'exception') !== false ||
                    stripos($line, 'fatal') !== false ||
                    stripos($line, 'getCorrectAnswerText') !== false) {
                    $errors[] = trim($line);
                }
            }
            
            if (empty($errors)) {
                $this->info('   ℹ️ Ошибок не найдено в последних 100 строках');
            } else {
                $this->warn('   ⚠️ Найдено ошибок: ' . count($errors));
                $this->line('   Последние 3 ошибки:');
                foreach (array_slice($errors, -3) as $error) {
                    $this->line('   ' . substr($error, 0, 150));
                }
            }
        }
        
        $this->newLine();
        $this->info('💡 Рекомендации:');
        $this->line('   1. Убедитесь, что все миграции выполнены: php artisan migrate');
        $this->line('   2. Проверьте ошибки: php artisan webhook:check-errors');
        $this->line('   3. Проверьте webhook: php artisan telegram:check-webhook');
        
        return Command::SUCCESS;
    }
}
