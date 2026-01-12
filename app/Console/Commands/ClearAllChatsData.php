<?php

namespace App\Console\Commands;

use App\Models\ActiveQuiz;
use App\Models\ChatStatistics;
use App\Models\QuizResult;
use App\Models\UserScore;
use App\Models\QuestionHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearAllChatsData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:clear-all {--force : Пропустить подтверждение}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Полностью удалить все данные всех чатов из базы данных (статистика, викторины, результаты, очки)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Подсчитать данные для удаления
        $chatsCount = ChatStatistics::count();
        $activeQuizzesCount = ActiveQuiz::count();
        $quizResultsCount = QuizResult::count();
        $userScoresCount = UserScore::count();
        $questionHistoryCount = QuestionHistory::count();
        
        $this->error("=== ⚠️ ПОЛНАЯ ОЧИСТКА ВСЕХ ДАННЫХ ВСЕХ ЧАТОВ ⚠️ ===");
        $this->info('');
        $this->warn("Будет удалено:");
        $this->line("  • Статистика чатов: {$chatsCount} записей");
        $this->line("  • Активные викторины: {$activeQuizzesCount} записей");
        $this->line("  • Результаты викторин: {$quizResultsCount} записей");
        $this->line("  • Очки пользователей: {$userScoresCount} записей");
        $this->line("  • История вопросов: {$questionHistoryCount} записей");
        $this->info('');
        $this->error("⚠️ ВНИМАНИЕ: Это действие необратимо!");
        $this->error("⚠️ Будет удалена ВСЯ история всех чатов!");
        $this->error("⚠️ Все викторины, результаты, очки пользователей будут потеряны!");
        $this->info('');
        $this->info('После удаления чаты можно будет зарегистрировать заново как новые.');
        $this->info('');

        // Подтверждение
        if (!$this->option('force')) {
            $this->warn('Для подтверждения введите "DELETE ALL" (без кавычек):');
            $confirmation = $this->ask('Подтверждение');
            
            if ($confirmation !== 'DELETE ALL') {
                $this->info('Операция отменена. Неверное подтверждение.');
                return Command::SUCCESS;
            }
        }

        $this->info('Удаление всех данных...');

        try {
            DB::beginTransaction();

            // 1. Получить ID всех викторин ПЕРЕД удалением
            $quizIds = ActiveQuiz::pluck('id');
            
            // 2. Удалить результаты викторин (связаны через active_quiz_id)
            if ($quizIds->isNotEmpty()) {
                $deletedResults = QuizResult::whereIn('active_quiz_id', $quizIds)->delete();
                $this->line("  ✓ Удалено результатов викторин: {$deletedResults}");
            } else {
                $this->line("  ✓ Результатов викторин не найдено");
            }

            // 3. Удалить активные викторины
            $deletedQuizzes = ActiveQuiz::query()->delete();
            $this->line("  ✓ Удалено активных викторин: {$deletedQuizzes}");

            // 4. Удалить очки пользователей
            $deletedScores = UserScore::query()->delete();
            $this->line("  ✓ Удалено записей очков: {$deletedScores}");

            // 5. Удалить историю вопросов
            $deletedHistory = QuestionHistory::query()->delete();
            $this->line("  ✓ Удалено записей истории: {$deletedHistory}");

            // 6. Удалить статистику всех чатов
            $deletedChats = ChatStatistics::query()->delete();
            $this->line("  ✓ Удалена статистика чатов: {$deletedChats}");

            DB::commit();

            $this->info('');
            $this->info("✅ Все данные всех чатов успешно удалены!");
            $this->info('');
            $this->info('💡 Теперь вы можете:');
            $this->line('   1. Добавить ботов обратно в группы');
            $this->line('   2. Отправить сообщения в группы');
            $this->line('   3. Чаты зарегистрируются как новые (без истории)');
            $this->line('   Или используйте: php artisan chat:register <chat_id> для каждого чата');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Ошибка при удалении данных: ' . $e->getMessage());
            $this->error('Откат изменений...');
            return Command::FAILURE;
        }
    }
}
