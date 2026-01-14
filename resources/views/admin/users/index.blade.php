@extends('admin.layout')

@section('title', 'Пользователи')
@section('page-title', 'Управление пользователями')

@section('content')
@if(isset($topTitans) && $topTitans->count() > 0)
<div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; margin-bottom: 20px;">
    <div class="card-header" style="border-bottom: 1px solid rgba(255,255,255,0.3);">
        <h2 style="color: white; margin: 0;">🏆 Топ Титанов (Лидерборд)</h2>
    </div>
    <div style="padding: 15px;">
        <table class="table" style="color: white; margin: 0;">
            <thead>
                <tr style="border-bottom: 1px solid rgba(255,255,255,0.3);">
                    <th style="color: white; border: none;">Место</th>
                    <th style="color: white; border: none;">Ник</th>
                    <th style="color: white; border: none;">Рейтинг</th>
                    <th style="color: white; border: none;">Очки</th>
                </tr>
            </thead>
            <tbody>
                @foreach($topTitans as $index => $titan)
                <tr style="border-bottom: 1px solid rgba(255,255,255,0.2);">
                    <td style="border: none;">
                        @if($index + 1 == 1) 🥇
                        @elseif($index + 1 == 2) 🥈
                        @elseif($index + 1 == 3) 🥉
                        @else 🏅
                        @endif
                        <strong>#{{ $index + 1 }}</strong>
                    </td>
                    <td style="border: none;">{{ $titan->game_nickname ?? "User {$titan->user_id}" }}</td>
                    <td style="border: none;">
                        <span style="background: rgba(255,255,255,0.2); padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                            {{ $titan->getFormattedRank() }}
                        </span>
                    </td>
                    <td style="border: none;"><strong>{{ number_format($titan->rank_points) }}</strong></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="card">
    <div class="card-header">
        <h2>Список пользователей</h2>
        
        <form method="GET" action="{{ route('admin.users.index') }}" style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
            <input type="text" name="search" placeholder="Поиск по нику или ID..." value="{{ request('search') }}" class="form-control" style="width: 250px;">
            
            <select name="rank_tier" class="form-control" style="width: 200px;">
                <option value="">Все ранги</option>
                <option value="recruit" {{ request('rank_tier') == 'recruit' ? 'selected' : '' }}>🟤 Рекрут</option>
                <option value="guardian" {{ request('rank_tier') == 'guardian' ? 'selected' : '' }}>🟢 Страж</option>
                <option value="knight" {{ request('rank_tier') == 'knight' ? 'selected' : '' }}>🟡 Рыцарь</option>
                <option value="hero" {{ request('rank_tier') == 'hero' ? 'selected' : '' }}>🔵 Герой</option>
                <option value="legend" {{ request('rank_tier') == 'legend' ? 'selected' : '' }}>🟣 Легенда</option>
                <option value="overlord" {{ request('rank_tier') == 'overlord' ? 'selected' : '' }}>🟠 Властилин</option>
                <option value="deity" {{ request('rank_tier') == 'deity' ? 'selected' : '' }}>🔴 Божество</option>
                <option value="titan" {{ request('rank_tier') == 'titan' ? 'selected' : '' }}>⚪ Титан</option>
            </select>
            
            <select name="sort_by" class="form-control" style="width: 150px;">
                <option value="total_points" {{ request('sort_by') == 'total_points' ? 'selected' : '' }}>По очкам</option>
                <option value="rank_points" {{ request('sort_by') == 'rank_points' ? 'selected' : '' }}>По рангу</option>
                <option value="created_at" {{ request('sort_by') == 'created_at' ? 'selected' : '' }}>По дате</option>
            </select>
            
            <button type="submit" class="btn btn-primary">🔍 Поиск</button>
            <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">🔄 Сбросить</a>
        </form>
    </div>

    @if($users->count() > 0)
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Ник в игре</th>
                    <th>Рейтинг</th>
                    <th>Очки</th>
                    <th>Чатов</th>
                    <th>Правильных</th>
                    <th>Dotabuff</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                <tr>
                    <td><code>{{ $user->user_id }}</code></td>
                    <td>{{ $user->game_nickname ?? '—' }}</td>
                    <td>
                        <span class="badge badge-info" style="font-size: 13px;">
                            {{ $user->getFormattedRank() }}
                        </span>
                        @if($user->rank_tier == 'titan' && $user->rank_points >= 7000)
                            @php
                                $position = array_search($user->user_id, $titanLeaderboard) + 1;
                            @endphp
                            @if($position <= 10)
                                <span class="badge badge-warning" style="margin-left: 5px; font-size: 11px;">🏆 Топ {{ $position }}</span>
                            @endif
                        @endif
                    </td>
                    <td><strong>{{ number_format($user->total_points) }}</strong></td>
                    <td>{{ $user->chats_count ?? 0 }}</td>
                    <td>{{ number_format($user->total_correct ?? 0) }}</td>
                    <td>
                        @if($user->dotabuff_url)
                            <a href="{{ $user->dotabuff_url }}" target="_blank" class="btn btn-sm btn-info" style="padding: 2px 8px; font-size: 11px;">🔗 Открыть</a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('admin.users.show', $user) }}" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">Просмотр</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="pagination">
            {{ $users->appends(request()->query())->links() }}
        </div>
    @else
        <p>Пользователи не найдены.</p>
    @endif
</div>
@endsection
