<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class ResetAndSeed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:reset-seed {--fresh : Полный сброс БД (drop all tables)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Перезапустить миграции и добавить вопросы с ответами';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔄 Перезапуск миграций и добавление вопросов...');
        $this->newLine();
        
        // 1. Откатить последние миграции
        $this->info('1. Откат последних миграций...');
        try {
            Artisan::call('migrate:rollback', ['--step' => 2]);
            $this->info('   ✅ Миграции откачены');
        } catch (\Exception $e) {
            $this->warn('   ⚠️ Ошибка при откате: ' . $e->getMessage());
            $this->info('   💡 Продолжаем...');
        }
        
        $this->newLine();
        
        // 2. Применить миграции заново
        $this->info('2. Применение миграций...');
        try {
            Artisan::call('migrate', ['--force' => true]);
            $this->info('   ✅ Миграции применены');
        } catch (\Exception $e) {
            $this->error('   ❌ Ошибка при применении миграций: ' . $e->getMessage());
            return Command::FAILURE;
        }
        
        $this->newLine();
        
        // 3. Конвертировать существующие вопросы (если есть)
        $this->info('3. Конвертация существующих вопросов...');
        try {
            $questionsCount = DB::table('questions')->whereNull('correct_answer_text')->count();
            if ($questionsCount > 0) {
                Artisan::call('questions:convert-to-index');
                $this->info("   ✅ Конвертировано вопросов: {$questionsCount}");
            } else {
                $this->info('   ℹ️ Нет вопросов для конвертации');
            }
        } catch (\Exception $e) {
            $this->warn('   ⚠️ Ошибка при конвертации: ' . $e->getMessage());
        }
        
        $this->newLine();
        
        // 4. Добавить вопросы через seeder
        $this->info('4. Добавление вопросов...');
        try {
            $existingCount = DB::table('questions')->count();
            $this->info("   Текущее количество вопросов: {$existingCount}");
            
            if ($existingCount > 0) {
                if (!$this->confirm('   Вопросы уже есть. Добавить еще?', true)) {
                    $this->info('   Пропущено');
                } else {
                    Artisan::call('db:seed', ['--class' => 'Dota2QuestionsSeeder', '--force' => true]);
                    $newCount = DB::table('questions')->count();
                    $added = $newCount - $existingCount;
                    $this->info("   ✅ Добавлено новых вопросов: {$added}");
                }
            } else {
                Artisan::call('db:seed', ['--class' => 'Dota2QuestionsSeeder', '--force' => true]);
                $newCount = DB::table('questions')->count();
                $this->info("   ✅ Добавлено вопросов: {$newCount}");
            }
        } catch (\Exception $e) {
            $this->error('   ❌ Ошибка при добавлении вопросов: ' . $e->getMessage());
            return Command::FAILURE;
        }
        
        $this->newLine();
        
        // 5. Итоговая статистика
        $this->info('5. Итоговая статистика:');
        $totalQuestions = DB::table('questions')->count();
        $questionsWithIndex = DB::table('questions')
            ->whereNotNull('correct_answer_text')
            ->where('correct_answer', '>=', 0)
            ->count();
        
        $this->line("   Всего вопросов: {$totalQuestions}");
        $this->line("   С правильной структурой (индекс + текст): {$questionsWithIndex}");
        
        if ($questionsWithIndex < $totalQuestions) {
            $this->warn("   ⚠️ Некоторые вопросы требуют конвертации");
            $this->info('   💡 Запустите: php artisan questions:convert-to-index');
        }
        
        $this->newLine();
        $this->info('✅ Готово!');
        
        return Command::SUCCESS;
    }
}
