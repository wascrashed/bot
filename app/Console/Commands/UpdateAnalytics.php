<?php

namespace App\Console\Commands;

use App\Services\AnalyticsService;
use Illuminate\Console\Command;

class UpdateAnalytics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analytics:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update daily bot analytics';

    /**
     * Execute the console command.
     */
    public function handle(AnalyticsService $analytics): int
    {
        $this->info('Updating analytics...');
        
        try {
            $analytics->updateDailyAnalytics();
            $todayStats = $analytics->getTodayAnalytics();
            
            $this->info('Analytics updated successfully:');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Active Chats', $todayStats['active_chats'] ?? 0],
                    ['Total Participants', $todayStats['total_participants'] ?? 0],
                    ['Total Quizzes', $todayStats['total_quizzes'] ?? 0],
                    ['Total Answers', $todayStats['total_answers'] ?? 0],
                    ['Correct Answers', $todayStats['correct_answers'] ?? 0],
                    ['Errors Count', $todayStats['errors_count'] ?? 0],
                    ['Avg Response Time (ms)', $todayStats['avg_response_time_ms'] ?? 0],
                    ['Uptime %', $todayStats['uptime_percentage'] ?? 100],
                ]
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error updating analytics: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
