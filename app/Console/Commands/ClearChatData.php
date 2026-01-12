<?php

namespace App\Console\Commands;

use App\Models\ActiveQuiz;
use App\Models\ChatStatistics;
use App\Models\QuizResult;
use App\Models\UserScore;
use App\Models\QuestionHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearChatData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:clear {chat_id : ID чата для полной очистки} {--force : Пропустить подтверждение}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Полностью удалить все данные чата из базы данных (статистика, викторины, результаты, очки)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $chatId = $this->argument('chat_id');
        
        // Проверить, что chat_id - число
        if (!is_numeric($chatId)) {
            $this->error('❌ Chat ID должен быть числом');
            return Command::FAILURE;
        }

        $chatId = (int) $chatId;
        
        // Найти чат
        $chat = ChatStatistics::where('chat_id', $chatId)->first();
        
        if (!$chat) {
            $this->warn("⚠️ Чат с ID {$chatId} не найден в базе данных");
            $this->info('Возможно, данные уже удалены или чат никогда не был зарегистрирован.');
            return Command::SUCCESS;
        }

        $chatTitle = $chat->chat_title ?? "Chat {$chatId}";
        
        // Подсчитать данные для удаления
        $activeQuizzesCount = ActiveQuiz::where('chat_id', $chatId)->count();
        $quizResultsCount = QuizResult::whereHas('activeQuiz', function($query) use ($chatId) {
            $query->where('chat_id', $chatId);
        })->count();
        $userScoresCount = UserScore::where('chat_id', $chatId)->count();
        $questionHistoryCount = QuestionHistory::where('chat_id', $chatId)->count();
        
        $this->info("=== Полная очистка данных чата ===");
        $this->line("ID чата: {$chatId}");
        $this->line("Название: {$chatTitle}");
        $this->info('');
        $this->warn("Будет удалено:");
        $this->line("  • Статистика чата: 1 запись");
        $this->line("  • Активные викторины: {$activeQuizzesCount} записей");
        $this->line("  • Результаты викторин: {$quizResultsCount} записей");
        $this->line("  • Очки пользователей: {$userScoresCount} записей");
        $this->line("  • История вопросов: {$questionHistoryCount} записей");
        $this->info('');
        $this->error("⚠️ ВНИМАНИЕ: Это действие необратимо!");
        $this->info('После удаления чат можно будет зарегистрировать заново как новый.');
        $this->info('');

        // Подтверждение
        if (!$this->option('force')) {
            if (!$this->confirm('Вы уверены, что хотите удалить все данные этого чата?')) {
                $this->info('Операция отменена.');
                return Command::SUCCESS;
            }
        }

        $this->info('Удаление данных...');

        try {
            DB::beginTransaction();

            // 1. Получить ID всех викторин этого чата ПЕРЕД удалением
            $quizIds = ActiveQuiz::where('chat_id', $chatId)->pluck('id');
            
            // 2. Удалить результаты викторин (связаны через active_quiz_id)
            if ($quizIds->isNotEmpty()) {
                $deletedResults = QuizResult::whereIn('active_quiz_id', $quizIds)->delete();
                $this->line("  ✓ Удалено результатов викторин: {$deletedResults}");
            } else {
                $this->line("  ✓ Результатов викторин не найдено");
            }

            // 3. Удалить активные викторины
            $deletedQuizzes = ActiveQuiz::where('chat_id', $chatId)->delete();
            $this->line("  ✓ Удалено активных викторин: {$deletedQuizzes}");

            // 4. Удалить очки пользователей
            $deletedScores = UserScore::where('chat_id', $chatId)->delete();
            $this->line("  ✓ Удалено записей очков: {$deletedScores}");

            // 5. Удалить историю вопросов
            $deletedHistory = QuestionHistory::where('chat_id', $chatId)->delete();
            $this->line("  ✓ Удалено записей истории: {$deletedHistory}");

            // 6. Удалить статистику чата
            $chat->delete();
            $this->line("  ✓ Удалена статистика чата");

            DB::commit();

            $this->info('');
            $this->info("✅ Все данные чата успешно удалены!");
            $this->info('');
            $this->info('💡 Теперь вы можете:');
            $this->line('   1. Добавить бота обратно в группу');
            $this->line('   2. Отправить любое сообщение в группу');
            $this->line('   3. Чат зарегистрируется как новый (без истории)');
            $this->line('   Или используйте: php artisan chat:register ' . $chatId);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Ошибка при удалении данных: ' . $e->getMessage());
            $this->error('Откат изменений...');
            return Command::FAILURE;
        }
    }
}
