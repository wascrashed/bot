@extends('admin.layout')

@section('title', 'Чаты')
@section('page-title', 'Управление чатами')

@section('content')
<div class="card">
    <div class="card-header">
        <h2>Список чатов</h2>
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
                        <form action="{{ route('admin.chats.toggle-active', $chat->chat_id) }}" method="POST" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn-{{ $chat->is_active ? 'warning' : 'success' }}" style="padding: 5px 10px; font-size: 12px;">
                                {{ $chat->is_active ? 'Деактивировать' : 'Активировать' }}
                            </button>
                        </form>
                        <form action="{{ route('admin.chats.destroy', $chat->chat_id) }}" method="POST" style="display: inline;" onsubmit="return confirm('Вы уверены, что хотите удалить этот чат из базы данных? Это действие нельзя отменить.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;">🗑️ Удалить</button>
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
        <p>Чаты не найдены.</p>
    @endif
</div>
@endsection
