@extends('admin.layout')

@section('title', 'Предложения мемов')
@section('page-title', 'Модерация предложений мемов')

@section('content')
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2>Предложения мемов</h2>
        @if($pendingCount > 0)
            <span class="badge badge-warning" style="font-size: 14px; padding: 8px 12px;">
                Ожидают рассмотрения: {{ $pendingCount }}
            </span>
        @endif
    </div>

    <form method="GET" action="{{ route('admin.meme-suggestions.index') }}" style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
        <select name="status" class="form-control" style="width: 200px;">
            <option value="">Все статусы</option>
            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Ожидают ({{ \App\Models\MemeSuggestion::where('status', 'pending')->count() }})</option>
            <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Одобрены ({{ \App\Models\MemeSuggestion::where('status', 'approved')->count() }})</option>
            <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Отклонены ({{ \App\Models\MemeSuggestion::where('status', 'rejected')->count() }})</option>
        </select>
        <button type="submit" class="btn btn-primary">Фильтровать</button>
        <a href="{{ route('admin.meme-suggestions.index') }}" class="btn btn-secondary">Сбросить</a>
    </form>

    @if($suggestions->count() > 0)
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>От пользователя</th>
                    <th>Тип</th>
                    <th>Статус</th>
                    <th>Дата</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                @foreach($suggestions as $suggestion)
                <tr>
                    <td>{{ $suggestion->id }}</td>
                    <td>
                        {{ $suggestion->first_name ?? $suggestion->username ?? "ID: {$suggestion->user_id}" }}
                        @if($suggestion->username)
                            <br><small class="text-muted">@{{ $suggestion->username }}</small>
                        @endif
                    </td>
                    <td>
                        @if($suggestion->media_type == 'video')
                            <span class="badge badge-info">🎥 Видео</span>
                        @else
                            <span class="badge badge-success">📷 Фото</span>
                        @endif
                    </td>
                    <td>
                        @if($suggestion->status == 'pending')
                            <span class="badge badge-warning">Ожидает</span>
                        @elseif($suggestion->status == 'approved')
                            <span class="badge badge-success">Одобрен</span>
                        @else
                            <span class="badge badge-danger">Отклонен</span>
                        @endif
                    </td>
                    <td>
                        {{ $suggestion->created_at->format('d.m.Y H:i') }}
                        @if($suggestion->reviewed_at)
                            <br><small class="text-muted">Рассмотрено: {{ $suggestion->reviewed_at->format('d.m.Y H:i') }}</small>
                        @endif
                    </td>
                    <td>
                        <div style="display: flex; gap: 5px;">
                            <a href="{{ route('admin.meme-suggestions.show', $suggestion) }}" class="btn btn-sm btn-primary">👁️ Просмотр</a>
                            @if($suggestion->status == 'pending')
                                <form action="{{ route('admin.meme-suggestions.approve', $suggestion) }}" method="POST" style="display: inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Одобрить мем?');">✅ Одобрить</button>
                                </form>
                                <form action="{{ route('admin.meme-suggestions.reject', $suggestion) }}" method="POST" style="display: inline;" id="reject-form-{{ $suggestion->id }}">
                                    @csrf
                                    <button type="button" class="btn btn-sm btn-danger" onclick="showRejectModal({{ $suggestion->id }})">❌ Отклонить</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top: 20px;">
            {{ $suggestions->links() }}
        </div>
    @else
        <div class="alert alert-info" style="margin: 20px;">
            Предложения не найдены.
        </div>
    @endif
</div>

<!-- Модальное окно для отклонения -->
<div id="rejectModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%;">
        <h3>Отклонить мем</h3>
        <form id="rejectModalForm" method="POST">
            @csrf
            <div class="form-group">
                <label class="form-label">Комментарий (опционально)</label>
                <textarea name="admin_comment" class="form-control" rows="3" placeholder="Причина отклонения..."></textarea>
            </div>
            <div style="margin-top: 20px; display: flex; gap: 10px;">
                <button type="submit" class="btn btn-danger">Отклонить</button>
                <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">Отмена</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentSuggestionId = null;

function showRejectModal(suggestionId) {
    currentSuggestionId = suggestionId;
    const form = document.getElementById('rejectModalForm');
    form.action = '/admin/meme-suggestions/' + suggestionId + '/reject';
    document.getElementById('rejectModal').style.display = 'block';
}

function closeRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
    currentSuggestionId = null;
}

// Закрыть при клике вне модального окна
document.getElementById('rejectModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeRejectModal();
    }
});
</script>
@endsection
