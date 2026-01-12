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
        $logFile = storage_path('logs/laravel.log');
        $lines = $request->input('lines', 200); // По умолчанию последние 200 строк
        $level = $request->input('level', 'all'); // Фильтр по уровню: all, error, warning, info
        
        $logs = [];
        
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
        
        $logSize = File::exists($logFile) ? File::size($logFile) : 0;
        $logSizeFormatted = $this->formatBytes($logSize);
        
        return view('admin.logs.index', compact('logs', 'lines', 'level', 'logSizeFormatted'));
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
    public function clear()
    {
        $logFile = storage_path('logs/laravel.log');
        
        if (File::exists($logFile)) {
            File::put($logFile, '');
        }
        
        return redirect()->route('admin.logs.index')
            ->with('success', 'Логи успешно очищены.');
    }
}
