@extends('admin.layout')

@section('title', 'Просмотр предложения')
@section('page-title', 'Предложение мема #{{ $memeSuggestion->id }}')

@section('content')
<div class="card">
    <div class="card-header">
        <h2>Предложение мема #{{ $memeSuggestion->id }}</h2>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
        <div>
            <h3>Информация о пользователе</h3>
            <table class="table" style="margin-top: 10px;">
                <tr>
                    <td><strong>Имя:</strong></td>
                    <td>{{ $memeSuggestion->first_name ?? 'Не указано' }}</td>
                </tr>
                <tr>
                    <td><strong>Username:</strong></td>
                    <td>@{{ $memeSuggestion->username ?? 'Не указано' }}</td>
                </tr>
                <tr>
                    <td><strong>User ID:</strong></td>
                    <td>{{ $memeSuggestion->user_id }}</td>
                </tr>
            </table>
        </div>

        <div>
            <h3>Информация о меме</h3>
            <table class="table" style="margin-top: 10px;">
                <tr>
                    <td><strong>Тип:</strong></td>
                    <td>
                        @if($memeSuggestion->media_type == 'video')
                            <span class="badge badge-info">🎥 Видео</span>
                        @else
                            <span class="badge badge-success">📷 Фото</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td><strong>Статус:</strong></td>
                    <td>
                        @if($memeSuggestion->status == 'pending')
                            <span class="badge badge-warning">Ожидает</span>
                        @elseif($memeSuggestion->status == 'approved')
                            <span class="badge badge-success">Одобрен</span>
                        @else
                            <span class="badge badge-danger">Отклонен</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td><strong>Дата предложения:</strong></td>
                    <td>{{ $memeSuggestion->created_at->format('d.m.Y H:i:s') }}</td>
                </tr>
                @if($memeSuggestion->reviewed_at)
                <tr>
                    <td><strong>Рассмотрено:</strong></td>
                    <td>{{ $memeSuggestion->reviewed_at->format('d.m.Y H:i:s') }}</td>
                </tr>
                @endif
                @if($memeSuggestion->reviewer)
                <tr>
                    <td><strong>Рассмотрел:</strong></td>
                    <td>{{ $memeSuggestion->reviewer->name ?? $memeSuggestion->reviewer->username }}</td>
                </tr>
                @endif
            </table>
        </div>
    </div>

    <div style="margin-bottom: 30px;">
        <h3>Превью мема</h3>
        <div style="margin-top: 15px;">
            @if($memeSuggestion->media_type == 'video')
                @if($filePath)
                    <div style="max-width: 800px;">
                        <video controls style="max-width: 100%; max-height: 600px; border: 1px solid #ddd; border-radius: 8px; background: #000;">
                            <source src="https://api.telegram.org/file/bot{{ config('telegram.bot_token') }}/{{ $filePath }}" type="video/mp4">
                            <source src="https://api.telegram.org/file/bot{{ config('telegram.bot_token') }}/{{ $filePath }}" type="video/webm">
                            Ваш браузер не поддерживает видео.
                        </video>
                        <p style="margin-top: 10px;">
                            <a href="https://api.telegram.org/file/bot{{ config('telegram.bot_token') }}/{{ $filePath }}" 
                               target="_blank" 
                               class="btn btn-primary btn-sm">
                                📥 Скачать видео
                            </a>
                        </p>
                    </div>
                @else
                    <div class="alert alert-warning">
                        <p>🎥 Видео (file_id: {{ $memeSuggestion->file_id }})</p>
                        <p><small>Не удалось получить путь к файлу для превью. Видео будет доступно после одобрения мема.</small></p>
                    </div>
                @endif
            @else
                @if($filePath)
                    <div style="max-width: 800px;">
                        <img src="https://api.telegram.org/file/bot{{ config('telegram.bot_token') }}/{{ $filePath }}" 
                             alt="Meme preview" 
                             style="max-width: 100%; max-height: 600px; border: 1px solid #ddd; border-radius: 8px; display: block;"
                             onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'500\' height=\'500\'%3E%3Crect width=\'500\' height=\'500\' fill=\'%23f0f0f0\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dominant-baseline=\'middle\' font-family=\'Arial\' font-size=\'18\' fill=\'%23999\'%3EНе удалось загрузить превью%3C/text%3E%3C/svg%3E';">
                        <p style="margin-top: 10px;">
                            <a href="https://api.telegram.org/file/bot{{ config('telegram.bot_token') }}/{{ $filePath }}" 
                               target="_blank" 
                               class="btn btn-primary btn-sm">
                                🔍 Открыть в полном размере
                            </a>
                        </p>
                    </div>
                @else
                    <div class="alert alert-warning">
                        <p class="text-muted">Не удалось загрузить превью. File ID: {{ $memeSuggestion->file_id }}</p>
                        <p><small>Попробуйте обновить страницу. Если проблема сохраняется, проверьте настройки бота.</small></p>
                    </div>
                @endif
            @endif
        </div>
    </div>

    @if($memeSuggestion->status == 'pending')
    <div class="card" style="background: #f8f9fa;">
        <div class="card-header">
            <h3>Действия</h3>
        </div>
        
        <form action="{{ route('admin.meme-suggestions.approve', $memeSuggestion) }}" method="POST" style="margin-bottom: 20px;">
            @csrf
            <div class="form-group">
                <label class="form-label" for="title">Название мема (опционально)</label>
                <input type="text" id="title" name="title" class="form-control" placeholder="Название мема">
            </div>
            <button type="submit" class="btn btn-success" onclick="return confirm('Одобрить и добавить мем в базу?');">
                ✅ Одобрить и добавить
            </button>
        </form>

        <form action="{{ route('admin.meme-suggestions.reject', $memeSuggestion) }}" method="POST">
            @csrf
            <div class="form-group">
                <label class="form-label" for="admin_comment">Комментарий при отклонении (опционально)</label>
                <textarea id="admin_comment" name="admin_comment" class="form-control" rows="3" placeholder="Причина отклонения..."></textarea>
            </div>
            <button type="submit" class="btn btn-danger" onclick="return confirm('Отклонить мем? Пользователь получит уведомление.');">
                ❌ Отклонить
            </button>
        </form>
    </div>
    @elseif($memeSuggestion->status == 'rejected' && $memeSuggestion->admin_comment)
    <div class="alert alert-info">
        <strong>Комментарий при отклонении:</strong><br>
        {{ $memeSuggestion->admin_comment }}
    </div>
    @endif

    <div style="margin-top: 20px;">
        <a href="{{ route('admin.meme-suggestions.index') }}" class="btn btn-secondary">← Назад к списку</a>
    </div>
</div>
@endsection
