@extends('admin.layout')

@section('title', 'Логи')
@section('page-title', '📋 Логи системы')

@section('content')
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2>📋 Логи системы</h2>
        <div style="display: flex; gap: 10px; align-items: center;">
            <span style="color: #666; font-size: 14px;">Размер файла: <strong>{{ $logSizeFormatted }}</strong></span>
            <form action="{{ route('admin.logs.clear') }}" method="POST" style="display: inline;" onsubmit="return confirm('Вы уверены, что хотите очистить все логи?');">
                @csrf
                <input type="hidden" name="type" value="{{ $logType }}">
                <button type="submit" class="btn btn-danger" style="padding: 8px 16px;">🗑️ Очистить логи</button>
            </form>
        </div>
    </div>
    
    @if($logType === 'cron' && $cronStatus)
    <div class="card-body" style="background: #f8f9fa; border-bottom: 1px solid #dee2e6;">
        <h3 style="margin-bottom: 15px; font-size: 18px;">⏰ Статус Cron</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
            <div style="background: white; padding: 15px; border-radius: 4px; border-left: 4px solid #17a2b8;">
                <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Последний запуск</div>
                <div style="font-size: 16px; font-weight: bold; color: #333;">
                    {{ $cronStatus['last_run'] ?? 'Никогда' }}
                </div>
            </div>
            <div style="background: white; padding: 15px; border-radius: 4px; border-left: 4px solid #28a745;">
                <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Последний успех</div>
                <div style="font-size: 16px; font-weight: bold; color: #333;">
                    {{ $cronStatus['last_success'] ?? 'Нет данных' }}
                </div>
            </div>
            <div style="background: white; padding: 15px; border-radius: 4px; border-left: 4px solid {{ $cronStatus['last_error'] ? '#dc3545' : '#6c757d' }};">
                <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Последняя ошибка</div>
                <div style="font-size: 16px; font-weight: bold; color: #333;">
                    {{ $cronStatus['last_error'] ?? 'Нет ошибок' }}
                </div>
            </div>
            <div style="background: white; padding: 15px; border-radius: 4px; border-left: 4px solid {{ $cronStatus['is_running'] ? '#ffc107' : '#6c757d' }};">
                <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Статус</div>
                <div style="font-size: 16px; font-weight: bold; color: #333;">
                    @if($cronStatus['is_running'])
                        <span style="color: #ffc107;">🟡 Выполняется</span>
                    @else
                        <span style="color: #6c757d;">⚪ Ожидание</span>
                    @endif
                </div>
            </div>
        </div>
        <div style="margin-top: 15px; padding: 10px; background: white; border-radius: 4px;">
            <div style="display: flex; gap: 20px; font-size: 14px;">
                <span>Всего запусков: <strong>{{ $cronStatus['total_runs'] }}</strong></span>
                <span style="color: #28a745;">Успешных: <strong>{{ $cronStatus['success_count'] }}</strong></span>
                <span style="color: #dc3545;">Ошибок: <strong>{{ $cronStatus['error_count'] }}</strong></span>
            </div>
        </div>
    </div>
    @endif
    <div class="card-body">
        <div style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <div style="display: flex; gap: 5px; border: 1px solid #ddd; border-radius: 4px; overflow: hidden;">
                <a href="{{ route('admin.logs.index', ['type' => 'laravel', 'lines' => $lines, 'level' => $level]) }}" 
                   style="padding: 8px 16px; text-decoration: none; background: {{ $logType === 'laravel' ? '#007bff' : '#f8f9fa' }}; color: {{ $logType === 'laravel' ? 'white' : '#333' }}; border-right: 1px solid #ddd;">
                    📝 Laravel Logs
                </a>
                <a href="{{ route('admin.logs.index', ['type' => 'cron', 'lines' => $lines, 'level' => $level]) }}" 
                   style="padding: 8px 16px; text-decoration: none; background: {{ $logType === 'cron' ? '#007bff' : '#f8f9fa' }}; color: {{ $logType === 'cron' ? 'white' : '#333' }};">
                    ⏰ Cron Logs
                </a>
            </div>
        </div>
        
        <form method="GET" action="{{ route('admin.logs.index') }}" style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <input type="hidden" name="type" value="{{ $logType }}">
            <div>
                <label for="lines" style="display: block; margin-bottom: 5px; font-weight: bold;">Строк:</label>
                <select name="lines" id="lines" class="form-control" style="width: 120px;">
                    <option value="50" {{ $lines == 50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ $lines == 100 ? 'selected' : '' }}>100</option>
                    <option value="200" {{ $lines == 200 ? 'selected' : '' }}>200</option>
                    <option value="500" {{ $lines == 500 ? 'selected' : '' }}>500</option>
                    <option value="1000" {{ $lines == 1000 ? 'selected' : '' }}>1000</option>
                </select>
            </div>
            @if($logType === 'laravel')
            <div>
                <label for="level" style="display: block; margin-bottom: 5px; font-weight: bold;">Уровень:</label>
                <select name="level" id="level" class="form-control" style="width: 150px;">
                    <option value="all" {{ $level == 'all' ? 'selected' : '' }}>Все</option>
                    <option value="error" {{ $level == 'error' ? 'selected' : '' }}>Ошибки</option>
                    <option value="warning" {{ $level == 'warning' ? 'selected' : '' }}>Предупреждения</option>
                    <option value="info" {{ $level == 'info' ? 'selected' : '' }}>Информация</option>
                    <option value="debug" {{ $level == 'debug' ? 'selected' : '' }}>Отладка</option>
                </select>
            </div>
            @endif
            <div style="align-self: flex-end;">
                <button type="submit" class="btn btn-primary" style="padding: 8px 20px;">🔍 Применить фильтры</button>
            </div>
        </form>
        
        @if(count($logs) > 0)
            <div style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 12px; max-height: 600px; overflow-y: auto;">
                @foreach($logs as $log)
                    <div style="margin-bottom: 15px; padding: 10px; border-left: 3px solid 
                        @if($log['level'] == 'error') #dc3545
                        @elseif($log['level'] == 'warning') #ffc107
                        @elseif($log['level'] == 'info') #17a2b8
                        @else #6c757d
                        @endif; 
                        background: rgba(255,255,255,0.02); border-radius: 2px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span style="color: #569cd6; font-weight: bold;">
                                [{{ $log['timestamp'] }}]
                            </span>
                            <span class="badge 
                                @if($log['level'] == 'error') badge-danger
                                @elseif($log['level'] == 'warning') badge-warning
                                @elseif($log['level'] == 'info') badge-info
                                @else badge-secondary
                                @endif" 
                                style="text-transform: uppercase; font-size: 10px;">
                                {{ $log['level'] }}
                            </span>
                        </div>
                        <pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word; color: #d4d4d4; font-size: 11px; line-height: 1.4;">{{ htmlspecialchars($log['full']) }}</pre>
                    </div>
                @endforeach
            </div>
        @else
            <div style="text-align: center; padding: 40px; color: #666;">
                <p style="font-size: 18px;">📭 Логи не найдены</p>
                <p>Попробуйте изменить фильтры или увеличить количество строк.</p>
            </div>
        @endif
    </div>
</div>

<style>
.form-control {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.badge-danger { background: #dc3545; color: white; padding: 4px 8px; border-radius: 3px; }
.badge-warning { background: #ffc107; color: #000; padding: 4px 8px; border-radius: 3px; }
.badge-info { background: #17a2b8; color: white; padding: 4px 8px; border-radius: 3px; }
.badge-secondary { background: #6c757d; color: white; padding: 4px 8px; border-radius: 3px; }
</style>
@endsection
