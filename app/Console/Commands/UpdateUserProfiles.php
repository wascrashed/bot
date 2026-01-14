<?php

namespace App\Console\Commands;

use App\Models\UserProfile;
use App\Models\UserScore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateUserProfiles extends Command
{
    protected $signature = 'profiles:update {--all : Обновить всех пользователей, даже без очков}';
    protected $description = 'Создать/обновить профили пользователей и пересчитать ранги';

    public function handle(): int
    {
        try {
            // Проверяем, существует ли таблица
            if (!DB::getSchemaBuilder()->hasTable('user_profiles')) {
                $this->error('Таблица user_profiles не существует. Выполните миграции:');
                $this->line('php artisan migrate');
                return Command::FAILURE;
            }

            $this->info('🔄 Начинаю обновление профилей пользователей...');

            // Получить всех уникальных пользователей с очками
            $userIds = UserScore::distinct()->pluck('user_id');
            
            if ($this->option('all')) {
                // Если опция --all, также создаем профили для пользователей без очков
                // (например, тех, кто только зарегистрировался)
                $this->info('📋 Режим: обновление всех пользователей');
            } else {
                $this->info("📋 Найдено пользователей с очками: {$userIds->count()}");
            }

            $updated = 0;
            $created = 0;
            $bar = $this->output->createProgressBar($userIds->count());
            $bar->start();

            foreach ($userIds as $userId) {
                try {
                    $profile = UserProfile::firstOrNew(['user_id' => $userId]);
                    $wasNew = !$profile->exists;
                    
                    // Обновляем очки и пересчитываем ранг
                    $profile->updateTotalPoints();
                    
                    if ($wasNew) {
                        $created++;
                    } else {
                        $updated++;
                    }
                } catch (\Exception $e) {
                    $this->newLine();
                    $this->warn("⚠️  Ошибка для user_id {$userId}: " . $e->getMessage());
                }
                
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            $this->info("✅ Обновление завершено!");
            $this->line("   Создано профилей: {$created}");
            $this->line("   Обновлено профилей: {$updated}");
            $this->line("   Всего обработано: " . ($created + $updated));

            // Показываем статистику по рангам
            $this->newLine();
            $this->info('📊 Статистика по рангам:');
            
            $rankStats = UserProfile::select('rank_tier', DB::raw('COUNT(*) as count'))
                ->groupBy('rank_tier')
                ->orderByRaw("FIELD(rank_tier, 'recruit', 'guardian', 'knight', 'hero', 'legend', 'overlord', 'deity', 'titan')")
                ->get();

            $rankNames = [
                'recruit' => 'Рекрут',
                'guardian' => 'Страж',
                'knight' => 'Рыцарь',
                'hero' => 'Герой',
                'legend' => 'Легенда',
                'overlord' => 'Властилин',
                'deity' => 'Божество',
                'titan' => 'Титан',
            ];

            foreach ($rankStats as $stat) {
                $rankName = $rankNames[$stat->rank_tier] ?? $stat->rank_tier;
                $this->line("   {$rankName}: {$stat->count}");
            }

            // Показываем топ 5 Титанов
            $topTitans = UserProfile::where('rank_tier', UserProfile::RANK_TITAN)
                ->where('rank_points', '>=', UserProfile::TITAN_MIN_FOR_NUMBERS)
                ->orderBy('rank_points', 'desc')
                ->take(5)
                ->get();

            if ($topTitans->count() > 0) {
                $this->newLine();
                $this->info('🏆 Топ 5 Титанов:');
                foreach ($topTitans as $index => $titan) {
                    $position = $titan->getTitanLeaderboardPosition();
                    $name = $titan->game_nickname ?? "User {$titan->user_id}";
                    $this->line("   #{$position} - {$name} ({$titan->rank_points} очков)");
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Ошибка: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
