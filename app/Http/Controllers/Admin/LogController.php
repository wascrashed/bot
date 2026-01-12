<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class LogController extends Controller
{
    /**
     * Display logs
     */
    public function index(Request $request)
    {
        $logType = $request->input('type', 'laravel'); // laravel или cron
        $logFile = $logType === 'cron' 
            ? storage_path('logs/cron.log') 
            : storage_path('logs/laravel.log');
        $lines = $request->input('lines', 200); // По умолчанию последние 200 строк
        $level = $request->input('level', 'all'); // Фильтр по уровню: all, error, warning, info
        
        $logs = [];
        $cronStatus = null;
        
        if ($logType === 'cron') {
            // Для cron логов - простой формат, просто читаем строки
            if (File::exists($logFile)) {
                $file = new \SplFileObject($logFile);
                $file->seek(PHP_INT_MAX);
                $totalLines = $file->key();
                
                $startLine = max(0, $totalLines - $lines);
                $file->seek($startLine);
                
                while (!$file->eof()) {
                    $line = trim($file->current());
                    if (!empty($line)) {
                        $logs[] = [
                            'timestamp' => $this->extractCronTimestamp($line),
                            'level' => $this->extractCronLevel($line),
                            'message' => $line,
                            'full' => $line,
                        ];
                    }
                    $file->next();
                }
                
                // Реверс, чтобы последние логи были первыми
                $logs = array_reverse($logs);
            }
            
            // Получить статус крона
            $cronStatus = $this->getCronStatus($logFile);
        } else {
            // Для Laravel логов - парсинг структурированных записей
            if (File::exists($logFile)) {
                $file = new \SplFileObject($logFile);
                $file->seek(PHP_INT_MAX);
                $totalLines = $file->key();
                
                // Читаем последние N строк
                $startLine = max(0, $totalLines - $lines);
                $file->seek($startLine);
                
                $currentLog = null;
                $buffer = '';
                
                while (!$file->eof()) {
                    $line = $file->current();
                    $file->next();
                    
                    // Определяем начало новой записи лога (формат Laravel: [YYYY-MM-DD HH:MM:SS])
                    if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                        // Сохраняем предыдущую запись
                        if ($currentLog !== null) {
                            if ($this->shouldIncludeLog($currentLog, $level)) {
                                $logs[] = $currentLog;
                            }
                        }
                        
                        // Начинаем новую запись
                        $currentLog = [
                            'timestamp' => $matches[1],
                            'level' => $this->extractLevel($line),
                            'message' => $line,
                            'full' => $line,
                        ];
                        $buffer = $line;
                    } elseif ($currentLog !== null) {
                        // Продолжение текущей записи
                        $currentLog['full'] .= "\n" . $line;
                        $currentLog['message'] .= "\n" . $line;
                        $buffer .= "\n" . $line;
                    }
                }
                
                // Добавляем последнюю запись
                if ($currentLog !== null && $this->shouldIncludeLog($currentLog, $level)) {
                    $logs[] = $currentLog;
                }
                
                // Реверс, чтобы последние логи были первыми
                $logs = array_reverse($logs);
            }
        }
        
        $logSize = File::exists($logFile) ? File::size($logFile) : 0;
        $logSizeFormatted = $this->formatBytes($logSize);
        
        return view('admin.logs.index', compact('logs', 'lines', 'level', 'logSizeFormatted', 'logType', 'cronStatus'));
    }
    
    /**
     * Извлечь уровень лога из строки
     */
    private function extractLevel(string $line): string
    {
        if (stripos($line, '.ERROR') !== false || stripos($line, 'error') !== false) {
            return 'error';
        } elseif (stripos($line, '.WARNING') !== false || stripos($line, 'warning') !== false) {
            return 'warning';
        } elseif (stripos($line, '.INFO') !== false || stripos($line, 'info') !== false) {
            return 'info';
        } elseif (stripos($line, '.DEBUG') !== false || stripos($line, 'debug') !== false) {
            return 'debug';
        }
        return 'info';
    }
    
    /**
     * Проверить, нужно ли включать лог в результат
     */
    private function shouldIncludeLog(array $log, string $level): bool
    {
        if ($level === 'all') {
            return true;
        }
        return $log['level'] === $level;
    }
    
    /**
     * Форматировать размер файла
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Очистить логи
     */
    public function clear(Request $request)
    {
        $logType = $request->input('type', 'laravel');
        $logFile = $logType === 'cron' 
            ? storage_path('logs/cron.log') 
            : storage_path('logs/laravel.log');
        
        if (File::exists($logFile)) {
            File::put($logFile, '');
        }
        
        return redirect()->route('admin.logs.index', ['type' => $logType])
            ->with('success', 'Логи успешно очищены.');
    }
    
    /**
     * Извлечь timestamp из cron лога
     */
    private function extractCronTimestamp(string $line): string
    {
        // Формат: [2024-01-01 12:00:00] или просто дата в начале строки
        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
            return $matches[1];
        }
        return date('Y-m-d H:i:s');
    }
    
    /**
     * Извлечь уровень из cron лога
     */
    private function extractCronLevel(string $line): string
    {
        if (stripos($line, 'error') !== false || stripos($line, 'failed') !== false || stripos($line, 'exit code: 1') !== false) {
            return 'error';
        } elseif (stripos($line, 'warning') !== false) {
            return 'warning';
        } elseif (stripos($line, 'success') !== false || stripos($line, 'exit code: 0') !== false) {
            return 'info';
        }
        return 'info';
    }
    
    /**
     * Получить статус крона
     */
    private function getCronStatus(string $logFile): array
    {
        $status = [
            'last_run' => null,
            'last_success' => null,
            'last_error' => null,
            'is_running' => false,
            'total_runs' => 0,
            'success_count' => 0,
            'error_count' => 0,
        ];
        
        if (!File::exists($logFile)) {
            return $status;
        }
        
        $file = new \SplFileObject($logFile);
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();
        
        // Читаем последние 100 строк для анализа
        $startLine = max(0, $totalLines - 100);
        $file->seek($startLine);
        
        $lines = [];
        while (!$file->eof()) {
            $line = trim($file->current());
            if (!empty($line)) {
                $lines[] = $line;
            }
            $file->next();
        }
        
        // Анализируем все записи для подсчета статистики
        foreach ($lines as $line) {
            // Ищем записи о запуске
            if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*Cron started/', $line, $matches)) {
                $status['last_run'] = $matches[1];
                $status['total_runs']++;
            }
            
            // Ищем успешные завершения
            if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*Cron finished successfully.*exit code: 0/', $line, $matches)) {
                $status['last_success'] = $matches[1];
                $status['success_count']++;
            }
            
            // Ищем ошибки завершения
            if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*Cron finished with errors.*exit code: 1/', $line, $matches)) {
                $status['last_error'] = $matches[1];
                $status['error_count']++;
            }
            
            // Также ищем ошибки в процессе выполнения
            if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*(?:Schedule:run failed|Queue:work failed)/', $line, $matches)) {
                if (!$status['last_error'] || $matches[1] > $status['last_error']) {
                    $status['last_error'] = $matches[1];
                }
                $status['error_count']++;
            }
        }
        
        // Проверяем, не запущен ли сейчас (есть "started" без "finished" в последних строках)
        $lastLines = array_slice($lines, -10); // Последние 10 строк
        $hasStarted = false;
        $hasFinished = false;
        foreach (array_reverse($lastLines) as $line) {
            if (stripos($line, 'Cron started') !== false) {
                $hasStarted = true;
            }
            if (stripos($line, 'Cron finished') !== false) {
                $hasFinished = true;
                break;
            }
        }
        
        if ($hasStarted && !$hasFinished) {
            $status['is_running'] = true;
        }
        
        return $status;
    }
}
