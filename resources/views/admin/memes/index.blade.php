@extends('admin.layout')

@section('title', 'Мемы')
@section('page-title', 'Управление мемами')

@section('content')
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2>Список мемов</h2>
        <a href="{{ route('admin.memes.create') }}" class="btn btn-primary">+ Добавить мем</a>
    </div>

    <form method="GET" action="{{ route('admin.memes.index') }}" style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
        <select name="type" class="form-control" style="width: 150px;">
            <option value="">Все типы</option>
            <option value="photo" {{ request('type') == 'photo' ? 'selected' : '' }}>Фото</option>
            <option value="video" {{ request('type') == 'video' ? 'selected' : '' }}>Видео</option>
        </select>
        <select name="active" class="form-control" style="width: 150px;">
            <option value="">Все</option>
            <option value="1" {{ request('active') == '1' ? 'selected' : '' }}>Активные</option>
            <option value="0" {{ request('active') == '0' ? 'selected' : '' }}>Неактивные</option>
        </select>
        <button type="submit" class="btn btn-primary">Фильтровать</button>
        <a href="{{ route('admin.memes.index') }}" class="btn btn-secondary">Сбросить</a>
    </form>

    @if($memes->count() > 0)
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Название</th>
                    <th>Тип</th>
                    <th>Статус</th>
                    <th>Превью</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                @foreach($memes as $meme)
                <tr>
                    <td>{{ $meme->id }}</td>
                    <td>{{ $meme->title ?? 'Без названия' }}</td>
                    <td>
                        @if($meme->media_type == 'video')
                            <span class="badge badge-info">🎥 Видео</span>
                        @else
                            <span class="badge badge-success">📷 Фото</span>
                        @endif
                    </td>
                    <td>
                        @if($meme->is_active)
                            <span class="badge badge-success">Активен</span>
                        @else
                            <span class="badge badge-secondary">Неактивен</span>
                        @endif
                    </td>
                    <td>
                        @if($meme->media_url)
                            @if($meme->media_type == 'video')
                                <span>🎥 Видео</span>
                            @else
                                <img src="{{ asset($meme->media_url) }}" alt="Preview" style="max-width: 100px; max-height: 100px; object-fit: cover;">
                            @endif
                        @endif
                    </td>
                    <td>
                        <div style="display: flex; gap: 5px;">
                            <a href="{{ route('admin.memes.edit', $meme) }}" class="btn btn-sm btn-primary">✏️</a>
                            <form action="{{ route('admin.memes.destroy', $meme) }}" method="POST" style="display: inline;" onsubmit="return confirm('Удалить мем?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top: 20px;">
            {{ $memes->links() }}
        </div>
    @else
        <div class="alert alert-info" style="margin: 20px;">
            Мемы не найдены. <a href="{{ route('admin.memes.create') }}">Добавить первый мем</a>
        </div>
    @endif
</div>
@endsection
