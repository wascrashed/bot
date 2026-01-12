@extends('admin.layout')

@section('title', 'Чаты')
@section('page-title', 'Управление чатами')

@section('content')
<div class="card">
    <div class="card-header">
        <h2>Список чатов</h2>
        <div class="mt-2">
            <a href="{{ route('admin.chats.index', ['show_all' => !$showAll]) }}" class="btn btn-{{ $showAll ? 'primary' : 'secondary' }}">
                {{ $showAll ? 'Показать только активные чаты' : 'Показать все чаты' }}
            </a>
        </div>
    </div>

    @if($chats->count() > 0)
        <table class="table">
            <thead>
                <tr>
                    <th>ID чата</th>
                    <th>Название</th>
                    <th>Тип</th>
                    <th>Всего викторин</th>
                    <th>Участников</th>
                    <th>Ответов</th>
                    <th>Правильных</th>
                    <th>Последняя викторина</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                @foreach($chats as $chat)
                <tr>
                    <td>{{ $chat->chat_id }}</td>
                    <td>{{ $chat->chat_title ?? 'Без названия' }}</td>
                    <td>{{ $chat->chat_type }}</td>
                    <td>{{ number_format($chat->total_quizzes) }}</td>
                    <td>{{ number_format($chat->total_participants) }}</td>
                    <td>{{ number_format($chat->total_answers) }}</td>
                    <td>{{ number_format($chat->correct_answers) }}</td>
                    <td>{{ $chat->last_quiz_at ? $chat->last_quiz_at->format('d.m.Y H:i') : 'Никогда' }}</td>
                    <td>
                        @if($chat->is_active)
                            <span class="badge badge-success">Активен</span>
                        @else
                            <span class="badge badge-secondary">Неактивен</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('admin.chats.show', $chat->chat_id) }}" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">Просмотр</a>
                        <form action="{{ route('admin.chats.check-status', $chat->chat_id) }}" method="POST" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn-info" style="padding: 5px 10px; font-size: 12px;" title="Проверить через Telegram API">🔍 Проверить</button>
                        </form>
                        <form action="{{ route('admin.chats.toggle-active', $chat->chat_id) }}" method="POST" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn-{{ $chat->is_active ? 'warning' : 'success' }}" style="padding: 5px 10px; font-size: 12px;">
                                {{ $chat->is_active ? 'Деактивировать' : 'Активировать' }}
                            </button>
                        </form>
                        <form action="{{ route('admin.chats.clear-all', $chat->chat_id) }}" method="POST" style="display: inline;" onsubmit="return confirm('⚠️ ВНИМАНИЕ: Это удалит ВСЕ данные чата (статистика, викторины, результаты, очки). Это действие необратимо!\n\nВы уверены?');">
                            @csrf
                            <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;" title="Полностью удалить все данные чата">🗑️ Удалить всё</button>
                        </form>
                        <form action="{{ route('admin.chats.destroy', $chat->chat_id) }}" method="POST" style="display: inline;" onsubmit="return confirm('Удалить только статистику чата? История викторин и очки сохранятся.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-warning" style="padding: 5px 10px; font-size: 12px;" title="Удалить только статистику">📊 Удалить статистику</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="pagination">
            {{ $chats->links() }}
        </div>
    @else
        <div class="card-body">
            <p>Чаты не найдены.</p>
            <p class="text-muted">Если вы удалили чат, он автоматически восстановится при следующем сообщении в группе.</p>
            <p class="text-muted">Или вы можете восстановить чат вручную, указав его ID:</p>
            <form action="{{ route('admin.chats.restore', 0) }}" method="POST" class="mt-3" onsubmit="const chatId = prompt('Введите ID чата (число):'); if (!chatId) return false; this.action = this.action.replace('/0/', '/' + chatId + '/'); return true;">
                @csrf
                <div class="form-group">
                    <label for="chat_type">Тип чата:</label>
                    <select name="chat_type" id="chat_type" class="form-control" style="max-width: 200px; display: inline-block;">
                        <option value="group">Группа</option>
                        <option value="supergroup">Супергруппа</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="chat_title">Название чата (опционально):</label>
                    <input type="text" name="chat_title" id="chat_title" class="form-control" style="max-width: 300px; display: inline-block;" placeholder="Название группы">
                </div>
                <button type="submit" class="btn btn-success">Восстановить чат</button>
            </form>
        </div>
    @endif
</div>
@endsection
