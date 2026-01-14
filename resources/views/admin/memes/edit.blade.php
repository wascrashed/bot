@extends('admin.layout')

@section('title', 'Редактировать мем')
@section('page-title', 'Редактировать мем')

@section('content')
<div class="card">
    <div class="card-header">
        <h2>Редактировать мем #{{ $meme->id }}</h2>
    </div>

    <form method="POST" action="{{ route('admin.memes.update', $meme) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label class="form-label" for="title">Название (опционально)</label>
            <input type="text" id="title" name="title" class="form-control" value="{{ old('title', $meme->title) }}" placeholder="Название мема">
        </div>

        <div class="form-group">
            <label class="form-label">Текущий файл</label>
            <div>
                @if($meme->media_url)
                    @if($meme->media_type == 'video')
                        <p>🎥 Видео: <a href="{{ asset($meme->media_url) }}" target="_blank">{{ basename($meme->media_url) }}</a></p>
                    @else
                        <img src="{{ asset($meme->media_url) }}" alt="Current media" style="max-width: 300px; max-height: 300px; object-fit: cover; display: block; margin-bottom: 10px;">
                    @endif
                @else
                    <p class="text-muted">Файл не загружен</p>
                @endif
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="media_file">Новый файл (оставьте пустым, чтобы не менять)</label>
            <input type="file" id="media_file" name="media_file" class="form-control" accept="image/*,video/*">
            <small class="form-text text-muted">Поддерживаются: JPEG, PNG, GIF, WebP (до 10MB) или MP4, AVI, MOV (до 50MB)</small>
        </div>

        <div class="form-group">
            <label class="form-label">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $meme->is_active) ? 'checked' : '' }}>
                Активен (будет показываться при команде /mem)
            </label>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul style="margin: 0;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div style="margin-top: 20px;">
            <button type="submit" class="btn btn-primary">Сохранить</button>
            <a href="{{ route('admin.memes.index') }}" class="btn btn-secondary">Отмена</a>
        </div>
    </form>
</div>
@endsection
