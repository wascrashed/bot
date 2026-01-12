<?php

namespace App\Console\Commands;

use App\Models\UserScore;
use Illuminate\Console\Command;

class ShowLeaderboard extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quiz:leaderboard {chat_id? : Telegram chat ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show leaderboard for a chat or all chats';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $chatId = $this->argument('chat_id');

        if ($chatId) {
            $this->showChatLeaderboard((int) $chatId);
        } else {
            $this->showGlobalLeaderboard();
        }

        return Command::SUCCESS;
    }

    private function showChatLeaderboard(int $chatId): void
    {
        $scores = UserScore::where('chat_id', $chatId)
            ->orderBy('total_points', 'desc')
            ->orderBy('correct_answers', 'desc')
            ->take(10)
            ->get();

        if ($scores->isEmpty()) {
            $this->warn("No scores found for chat {$chatId}");
            return;
        }

        $this->info("Leaderboard for chat {$chatId}:");

        $data = [];
        foreach ($scores as $index => $score) {
            $data[] = [
                $index + 1,
                $score->first_name ?? $score->username ?? "User {$score->user_id}",
                $score->total_points,
                $score->correct_answers,
                $score->first_place_count,
            ];
        }

        $this->table(
            ['Place', 'User', 'Points', 'Correct', 'First Places'],
            $data
        );
    }

    private function showGlobalLeaderboard(): void
    {
        $scores = UserScore::selectRaw('user_id, username, first_name, SUM(total_points) as total_points, SUM(correct_answers) as correct_answers, SUM(first_place_count) as first_place_count')
            ->groupBy('user_id', 'username', 'first_name')
            ->orderByDesc('total_points')
            ->take(20)
            ->get();

        if ($scores->isEmpty()) {
            $this->warn("No scores found");
            return;
        }

        $this->info("Global leaderboard:");

        $data = [];
        foreach ($scores as $index => $score) {
            $data[] = [
                $index + 1,
                $score->first_name ?? $score->username ?? "User {$score->user_id}",
                $score->total_points,
                $score->correct_answers,
                $score->first_place_count,
            ];
        }

        $this->table(
            ['Place', 'User', 'Total Points', 'Correct Answers', 'First Places'],
            $data
        );
    }
}
