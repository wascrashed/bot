@extends('admin.layout')

@section('title', 'Панель управления')
@section('page-title', 'Панель управления')

@section('content')
<div class="stats-grid">
    <div class="stat-card">
        <h3>Всего вопросов</h3>
        <div class="value">{{ number_format($stats['total_questions']) }}</div>
    </div>
    <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
        <h3>Активных чатов</h3>
        <div class="value">{{ number_format($stats['active_chats']) }}</div>
    </div>
    <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
        <h3>Участников</h3>
        <div class="value">{{ number_format($stats['total_participants']) }}</div>
    </div>
    <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
        <h3>Викторин сегодня</h3>
        <div class="value">{{ number_format($stats['total_quizzes_today']) }}</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>📊 Статистика за сегодня</h2>
    </div>
    @if($todayAnalytics)
        <table class="table">
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
                <td><strong>Ошибок:</strong></td>
                <td>{{ number_format($todayAnalytics->errors_count) }}</td>
            </tr>
            <tr>
                <td><strong>Среднее время ответа:</strong></td>
                <td>{{ number_format($todayAnalytics->avg_response_time_ms) }} мс</td>
            </tr>
        </table>
    @else
        <p>Статистика за сегодня пока отсутствует.</p>
    @endif
</div>

<div class="card">
    <div class="card-header">
        <h2>🏆 Топ чатов по активности</h2>
    </div>
    @if($topChats->count() > 0)
        <table class="table">
            <thead>
                <tr>
                    <th>ID чата</th>
                    <th>Название</th>
                    <th>Всего викторин</th>
                    <th>Участников</th>
                    <th>Ответов</th>
                </tr>
            </thead>
            <tbody>
                @foreach($topChats as $chat)
                <tr>
                    <td>{{ $chat->chat_id }}</td>
                    <td>{{ $chat->chat_title ?? 'Без названия' }}</td>
                    <td>{{ number_format($chat->total_quizzes) }}</td>
                    <td>{{ number_format($chat->total_participants) }}</td>
                    <td>{{ number_format($chat->total_answers) }}</td>
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
        <h2>🕐 Последние викторины</h2>
    </div>
    @if($recentQuizzes->count() > 0)
        <table class="table">
            <thead>
                <tr>
                    <th>ID чата</th>
                    <th>Вопрос</th>
                    <th>Время начала</th>
                    <th>Статус</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentQuizzes as $quiz)
                <tr>
                    <td>{{ $quiz->chat_id }}</td>
                    <td>{{ Str::limit($quiz->question->question ?? 'N/A', 50) }}</td>
                    <td>{{ $quiz->started_at->format('d.m.Y H:i:s') }}</td>
                    <td>
                        @if($quiz->is_active)
                            <span class="badge badge-success">Активна</span>
                        @else
                            <span class="badge badge-secondary">Завершена</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p>Нет викторин.</p>
    @endif
</div>
@endsection
