@extends('admin.layout')

@section('title', 'Добавить мем')
@section('page-title', 'Добавить мем')

@section('content')
<div class="card">
    <div class="card-header">
        <h2>Создать новый мем</h2>
    </div>

    <form method="POST" action="{{ route('admin.memes.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="form-group">
            <label class="form-label" for="title">Название (опционально)</label>
            <input type="text" id="title" name="title" class="form-control" value="{{ old('title') }}" placeholder="Название мема">
        </div>

        <div class="form-group">
            <label class="form-label" for="media_file">Файл (фото или видео) *</label>
            <input type="file" id="media_file" name="media_file" class="form-control" accept="image/*,video/*" required>
            <small class="form-text text-muted">Поддерживаются: JPEG, PNG, GIF, WebP (до 10MB) или MP4, AVI, MOV (до 50MB)</small>
        </div>

        <div class="form-group">
            <label class="form-label" for="media_type">Тип медиа *</label>
            <select id="media_type" name="media_type" class="form-control" required>
                <option value="photo" {{ old('media_type') == 'photo' ? 'selected' : '' }}>Фото</option>
                <option value="video" {{ old('media_type') == 'video' ? 'selected' : '' }}>Видео</option>
            </select>
            <small class="form-text text-muted">Тип будет автоматически определен по расширению файла</small>
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

<script>
document.getElementById('media_file').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const extension = file.name.split('.').pop().toLowerCase();
        const isVideo = ['mp4', 'avi', 'mov', 'mkv', 'webm'].includes(extension);
        const mediaTypeSelect = document.getElementById('media_type');
        if (isVideo) {
            mediaTypeSelect.value = 'video';
        } else {
            mediaTypeSelect.value = 'photo';
        }
    }
});
</script>
@endsection
