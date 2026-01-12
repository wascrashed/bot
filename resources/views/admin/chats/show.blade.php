@extends('admin.layout')

@section('title', 'Детали чата')
@section('page-title', 'Детали чата #' . $chat->chat_id)

@section('content')
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2>Информация о чате</h2>
        <div style="display: flex; gap: 10px;">
            <form action="{{ route('admin.chats.toggle-active', $chat->chat_id) }}" method="POST" style="display: inline;">
                @csrf
                <button type="submit" class="btn btn-{{ $chat->is_active ? 'warning' : 'success' }}">
                    {{ $chat->is_active ? 'Деактивировать' : 'Активировать' }}
                </button>
            </form>
            <form action="{{ route('admin.chats.clear-all', $chat->chat_id) }}" method="POST" style="display: inline;" onsubmit="return confirm('⚠️ ВНИМАНИЕ: Это удалит ВСЕ данные чата (статистика, викторины, результаты, очки). Это действие необратимо!\n\nВы уверены?');">
                @csrf
                <button type="submit" class="btn btn-danger">🗑️ Удалить всё</button>
            </form>
            <form action="{{ route('admin.chats.destroy', $chat->chat_id) }}" method="POST" style="display: inline;" onsubmit="return confirm('Удалить только статистику чата? История викторин и очки сохранятся.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-warning">📊 Удалить статистику</button>
            </form>
        </div>
    </div>

    <table class="table">
        <tr>
            <td><strong>ID чата:</strong></td>
            <td>{{ $chat->chat_id }}</td>
        </tr>
        <tr>
            <td><strong>Название:</strong></td>
            <td>{{ $chat->chat_title ?? 'Без названия' }}</td>
        </tr>
        <tr>
            <td><strong>Тип:</strong></td>
            <td>{{ $chat->chat_type }}</td>
        </tr>
        <tr>
            <td><strong>Всего викторин:</strong></td>
            <td>{{ number_format($chat->total_quizzes) }}</td>
        </tr>
        <tr>
            <td><strong>Участников:</strong></td>
            <td>{{ number_format($chat->total_participants) }}</td>
        </tr>
        <tr>
            <td><strong>Всего ответов:</strong></td>
            <td>{{ number_format($chat->total_answers) }}</td>
        </tr>
        <tr>
            <td><strong>Правильных ответов:</strong></td>
            <td>{{ number_format($chat->correct_answers) }}</td>
        </tr>
        <tr>
            <td><strong>Процент правильных:</strong></td>
            <td>{{ $chat->total_answers > 0 ? number_format(($chat->correct_answers / $chat->total_answers) * 100, 2) : 0 }}%</td>
        </tr>
        <tr>
            <td><strong>Первая викторина:</strong></td>
            <td>{{ $chat->first_quiz_at ? $chat->first_quiz_at->format('d.m.Y H:i:s') : 'Никогда' }}</td>
        </tr>
        <tr>
            <td><strong>Последняя викторина:</strong></td>
            <td>{{ $chat->last_quiz_at ? $chat->last_quiz_at->format('d.m.Y H:i:s') : 'Никогда' }}</td>
        </tr>
        <tr>
            <td><strong>Статус:</strong></td>
            <td>
                @if($chat->is_active)
                    <span class="badge badge-success">Активен</span>
                @else
                    <span class="badge badge-secondary">Неактивен</span>
                @endif
            </td>
        </tr>
    </table>
</div>

<div class="card">
    <div class="card-header">
        <h2>🏆 Таблица лидеров чата</h2>
    </div>

    @if($leaderboard->count() > 0)
        <table class="table">
            <thead>
                <tr>
                    <th>Место</th>
                    <th>Пользователь</th>
                    <th>Очки</th>
                    <th>Правильных</th>
                    <th>Всего ответов</th>
                    <th>Первых мест</th>
                </tr>
            </thead>
            <tbody>
                @foreach($leaderboard as $index => $user)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $user->first_name ?? $user->username ?? "User {$user->user_id}" }}</td>
                    <td><strong>{{ number_format($user->total_points) }}</strong></td>
                    <td>{{ number_format($user->correct_answers) }}</td>
                    <td>{{ number_format($user->total_answers) }}</td>
                    <td>{{ number_format($user->first_place_count) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p>Нет участников в этом чате.</p>
    @endif
</div>

<div style="margin-top: 20px;">
    <a href="{{ route('admin.chats.index') }}" class="btn btn-secondary">← Назад к списку чатов</a>
</div>
@endsection
