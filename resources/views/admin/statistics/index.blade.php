@extends('admin.layout')

@section('title', 'Статистика')
@section('page-title', 'Статистика')

@section('content')
<div class="card">
    <div class="card-header">
        <h2>📊 Статистика за сегодня</h2>
    </div>
    @if($todayAnalytics)
        <table class="table">
            <tr>
                <td><strong>Активных чатов:</strong></td>
                <td>{{ number_format($todayAnalytics->active_chats) }}</td>
            </tr>
            <tr>
                <td><strong>Всего участников:</strong></td>
                <td>{{ number_format($todayAnalytics->total_participants) }}</td>
            </tr>
            <tr>
                <td><strong>Всего викторин:</strong></td>
                <td>{{ number_format($todayAnalytics->total_quizzes) }}</td>
            </tr>
            <tr>
                <td><strong>Всего ответов:</strong></td>
                <td>{{ number_format($todayAnalytics->total_answers) }}</td>
            </tr>
            <tr>
                <td><strong>Правильных ответов:</strong></td>
                <td>{{ number_format($todayAnalytics->correct_answers) }}</td>
            </tr>
            <tr>
                <td><strong>Процент правильных:</strong></td>
                <td>{{ $todayAnalytics->total_answers > 0 ? number_format(($todayAnalytics->correct_answers / $todayAnalytics->total_answers) * 100, 2) : 0 }}%</td>
            </tr>
            <tr>
                <td><strong>Ошибок:</strong></td>
                <td>{{ number_format($todayAnalytics->errors_count) }}</td>
            </tr>
            <tr>
                <td><strong>Среднее время ответа:</strong></td>
                <td>{{ number_format($todayAnalytics->avg_response_time_ms) }} мс</td>
            </tr>
        </table>
    @else
        <p>Статистика за сегодня отсутствует.</p>
    @endif
</div>

<div class="card">
    <div class="card-header">
        <h2>💬 Статистика по чатам</h2>
    </div>
    @if($chatStats->count() > 0)
        <table class="table">
            <thead>
                <tr>
                    <th>ID чата</th>
                    <th>Название</th>
                    <th>Всего викторин</th>
                    <th>Участников</th>
                    <th>Ответов</th>
                    <th>Правильных</th>
                    <th>Последняя викторина</th>
                    <th>Статус</th>
                </tr>
            </thead>
            <tbody>
                @foreach($chatStats as $chat)
                <tr>
                    <td>{{ $chat->chat_id }}</td>
                    <td>{{ $chat->chat_title ?? 'Без названия' }}</td>
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
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p>Нет активных чатов.</p>
    @endif
</div>

<div class="card">
    <div class="card-header">
        <h2>🏆 Топ пользователей (глобальный)</h2>
    </div>
    @if($topUsers->count() > 0)
        <table class="table">
            <thead>
                <tr>
                    <th>Место</th>
                    <th>Пользователь</th>
                    <th>Всего очков</th>
                    <th>Правильных ответов</th>
                </tr>
            </thead>
            <tbody>
                @foreach($topUsers as $index => $user)
                @php
                    $profile = \App\Models\UserProfile::where('user_id', $user->user_id)->first();
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        {{ $user->first_name ?? $user->username ?? "User {$user->user_id}" }}
                        @if($profile)
                            <br><small class="text-muted">{{ $profile->getFormattedRank() }}</small>
                        @endif
                    </td>
                    <td><strong>{{ number_format($user->total_points) }}</strong></td>
                    <td>{{ number_format($user->correct_answers) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p>Нет данных о пользователях.</p>
    @endif
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
    <div class="card">
        <div class="card-header">
            <h2>📊 По категориям</h2>
        </div>
        @if($categoryStats->count() > 0)
            <table class="table">
                @foreach($categoryStats as $stat)
                <tr>
                    <td>
                        @php
                            $categories = ['heroes' => 'Герои', 'abilities' => 'Способности', 'items' => 'Предметы', 'lore' => 'Лор', 'esports' => 'Киберспорт', 'memes' => 'Мемы'];
                            echo $categories[$stat->category] ?? $stat->category;
                        @endphp
                    </td>
                    <td><strong>{{ number_format($stat->count) }}</strong></td>
                </tr>
                @endforeach
            </table>
        @endif
    </div>

    <div class="card">
        <div class="card-header">
            <h2>📋 По типам</h2>
        </div>
        @if($typeStats->count() > 0)
            <table class="table">
                @foreach($typeStats as $stat)
                <tr>
                    <td>
                        @php
                            $types = ['multiple_choice' => 'Выбор', 'text' => 'Текст', 'true_false' => 'В/Н', 'image' => 'Картинка'];
                            echo $types[$stat->question_type] ?? $stat->question_type;
                        @endphp
                    </td>
                    <td><strong>{{ number_format($stat->count) }}</strong></td>
                </tr>
                @endforeach
            </table>
        @endif
    </div>

    <div class="card">
        <div class="card-header">
            <h2>⚡ По сложности</h2>
        </div>
        @if($difficultyStats->count() > 0)
            <table class="table">
                @foreach($difficultyStats as $stat)
                <tr>
                    <td>
                        @php
                            $difficulties = ['easy' => 'Легкий', 'medium' => 'Средний', 'hard' => 'Сложный'];
                            echo $difficulties[$stat->difficulty] ?? $stat->difficulty;
                        @endphp
                    </td>
                    <td><strong>{{ number_format($stat->count) }}</strong></td>
                </tr>
                @endforeach
            </table>
        @endif
    </div>
</div>
@endsection
