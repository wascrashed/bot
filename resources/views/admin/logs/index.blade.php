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
                <button type="submit" class="btn btn-danger" style="padding: 8px 16px;">🗑️ Очистить логи</button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('admin.logs.index') }}" style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
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
