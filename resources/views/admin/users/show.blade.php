@extends('admin.layout')

@section('title', 'Профиль пользователя')
@section('page-title', 'Профиль пользователя #{{ $userProfile->user_id }}')

@section('content')
<div class="card">
    <div class="card-header">
        <h2>Профиль пользователя #{{ $userProfile->user_id }}</h2>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
        <div>
            <h3>Основная информация</h3>
            <table class="table" style="margin-top: 10px;">
                <tr>
                    <td><strong>User ID:</strong></td>
                    <td><code>{{ $userProfile->user_id }}</code></td>
                </tr>
                <tr>
                    <td><strong>Ник в игре:</strong></td>
                    <td>{{ $userProfile->game_nickname ?? 'Не указан' }}</td>
                </tr>
                <tr>
                    <td><strong>Рейтинг:</strong></td>
                    <td>
                        <span class="badge badge-info" style="font-size: 14px;">
                            {{ $userProfile->getFormattedRank() }}
                        </span>
                        @if($titanPosition && $titanPosition <= 10)
                            <span class="badge badge-warning" style="margin-left: 5px; font-size: 12px;">🏆 Топ {{ $titanPosition }}</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td><strong>Очки для ранга:</strong></td>
                    <td><strong>{{ number_format($userProfile->rank_points) }}</strong></td>
                </tr>
                <tr>
                    <td><strong>Всего очков:</strong></td>
                    <td><strong>{{ number_format($userProfile->total_points) }}</strong></td>
                </tr>
                <tr>
                    <td><strong>Показывать рейтинг:</strong></td>
                    <td>
                        @if($userProfile->show_rank_in_name)
                            <span class="badge badge-success">✅ Включено</span>
                        @else
                            <span class="badge badge-secondary">❌ Выключено</span>
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        <div>
            <h3>Dotabuff</h3>
            <table class="table" style="margin-top: 10px;">
                @if($userProfile->dotabuff_url)
                <tr>
                    <td><strong>Ссылка:</strong></td>
                    <td><a href="{{ $userProfile->dotabuff_url }}" target="_blank">{{ $userProfile->dotabuff_url }}</a></td>
                </tr>
                @if($userProfile->dotabuff_data)
                    @if(isset($userProfile->dotabuff_data['mmr']))
                    <tr>
                        <td><strong>MMR:</strong></td>
                        <td>{{ number_format($userProfile->dotabuff_data['mmr']) }}</td>
                    </tr>
                    @endif
                    @if(isset($userProfile->dotabuff_data['rank']))
                    <tr>
                        <td><strong>Ранг:</strong></td>
                        <td>{{ $userProfile->dotabuff_data['rank'] }}</td>
                    </tr>
                    @endif
                @endif
                <tr>
                    <td><strong>Последняя синхронизация:</strong></td>
                    <td>{{ $userProfile->dotabuff_last_sync ? $userProfile->dotabuff_last_sync->format('d.m.Y H:i:s') : 'Никогда' }}</td>
                </tr>
                @else
                <tr>
                    <td colspan="2" class="text-muted">Не указан</td>
                </tr>
                @endif
            </table>
        </div>
    </div>

    @if($totalStats)
    <div class="card" style="background: #f8f9fa; margin-bottom: 30px;">
        <div class="card-header">
            <h3>Общая статистика</h3>
        </div>
        <table class="table">
            <tr>
                <td><strong>Всего очков:</strong></td>
                <td><strong>{{ number_format($totalStats->total_points) }}</strong></td>
            </tr>
            <tr>
                <td><strong>Правильных ответов:</strong></td>
                <td>{{ number_format($totalStats->correct_answers) }}</td>
            </tr>
            <tr>
                <td><strong>Всего ответов:</strong></td>
                <td>{{ number_format($totalStats->total_answers) }}</td>
            </tr>
            <tr>
                <td><strong>Точность:</strong></td>
                <td>
                    @if($totalStats->total_answers > 0)
                        {{ number_format(($totalStats->correct_answers / $totalStats->total_answers) * 100, 1) }}%
                    @else
                        0%
                    @endif
                </td>
            </tr>
            <tr>
                <td><strong>Первых мест:</strong></td>
                <td>{{ number_format($totalStats->first_place_count) }}</td>
            </tr>
            <tr>
                <td><strong>Активных чатов:</strong></td>
                <td>{{ number_format($totalStats->chats_count) }}</td>
            </tr>
        </table>
    </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3>Статистика по чатам</h3>
        </div>
        
        @if($scores->count() > 0)
            <table class="table">
                <thead>
                    <tr>
                        <th>Chat ID</th>
                        <th>Очки</th>
                        <th>Правильных</th>
                        <th>Всего ответов</th>
                        <th>Первых мест</th>
                        <th>Последняя активность</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($scores as $score)
                    <tr>
                        <td><code>{{ $score->chat_id }}</code></td>
                        <td><strong>{{ number_format($score->total_points) }}</strong></td>
                        <td>{{ number_format($score->correct_answers) }}</td>
                        <td>{{ number_format($score->total_answers) }}</td>
                        <td>{{ number_format($score->first_place_count) }}</td>
                        <td>{{ $score->last_activity_at ? $score->last_activity_at->format('d.m.Y H:i') : '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p>Нет статистики по чатам.</p>
        @endif
    </div>

    <div style="margin-top: 20px;">
        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">← Назад к списку</a>
    </div>
</div>
@endsection
