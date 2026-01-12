<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearBrokenJobs extends Command
{
    protected $signature = 'queue:clear-broken';
    protected $description = 'Очистить битые задачи из очереди';

    public function handle()
    {
        $this->info('=== Очистка битых задач из очереди ===');
        
        $jobs = DB::table('jobs')->get();
        $broken = 0;
        
        foreach ($jobs as $job) {
            try {
                $payload = json_decode($job->payload, true);
                if (!$payload || !isset($payload['displayName'])) {
                    $broken++;
                    DB::table('jobs')->where('id', $job->id)->delete();
                    $this->warn("Удалена битая задача #{$job->id}");
                    continue;
                }
                
                // Проверить, что класс существует
                $displayName = $payload['displayName'] ?? '';
                if (!class_exists($displayName)) {
                    $broken++;
                    DB::table('jobs')->where('id', $job->id)->delete();
                    $this->warn("Удалена задача с несуществующим классом: {$displayName}");
                }
            } catch (\Exception $e) {
                $broken++;
                DB::table('jobs')->where('id', $job->id)->delete();
                $this->warn("Удалена задача с ошибкой парсинга #{$job->id}: {$e->getMessage()}");
            }
        }
        
        if ($broken > 0) {
            $this->info("\n✅ Удалено битых задач: {$broken}");
        } else {
            $this->info("\n✅ Битых задач не найдено");
        }
        
        $remaining = DB::table('jobs')->count();
        $this->info("Осталось задач в очереди: {$remaining}");
        
        return Command::SUCCESS;
    }
}
