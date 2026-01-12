@extends('admin.layout')

@section('title', 'Редактировать вопрос')
@section('page-title', 'Редактировать вопрос')

@section('content')
<div class="card">
    <div class="card-header">
        <h2>Редактировать вопрос #{{ $question->id }}</h2>
    </div>

    <form method="POST" action="{{ route('admin.questions.update', $question) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label class="form-label" for="question">Вопрос *</label>
            <textarea id="question" name="question" class="form-control" required>{{ old('question', $question->question) }}</textarea>
        </div>

        <div class="form-group">
            <label class="form-label" for="question_type">Тип вопроса *</label>
            <select id="question_type" name="question_type" class="form-control" required>
                <option value="multiple_choice" {{ old('question_type', $question->question_type) == 'multiple_choice' ? 'selected' : '' }}>Выбор из вариантов (кнопки)</option>
                <option value="text" {{ old('question_type', $question->question_type) == 'text' ? 'selected' : '' }}>Текстовый ответ</option>
                <option value="true_false" {{ old('question_type', $question->question_type) == 'true_false' ? 'selected' : '' }}>Верно/Неверно</option>
                <option value="image" {{ old('question_type', $question->question_type) == 'image' ? 'selected' : '' }}>С изображением</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" for="correct_answer">Правильный ответ *</label>
            <input type="text" id="correct_answer" name="correct_answer" class="form-control" value="{{ old('correct_answer', $question->correct_answer_text ?? $question->correct_answer) }}" required>
        </div>

        <div class="form-group" id="wrong-answers-group">
            <label class="form-label">Неправильные ответы (для типа "Выбор из вариантов")</label>
            <div id="wrong-answers-container">
                @php
                    $wrongAnswers = old('wrong_answers', $question->wrong_answers ?? []);
                    if (empty($wrongAnswers)) {
                        $wrongAnswers = ['', '', ''];
                    }
                @endphp
                @foreach($wrongAnswers as $answer)
                    <input type="text" name="wrong_answers[]" class="form-control" value="{{ $answer }}" style="margin-bottom: 10px;" placeholder="Неправильный ответ">
                @endforeach
            </div>
            <button type="button" class="btn btn-secondary" onclick="addWrongAnswer()" style="margin-top: 10px;">+ Добавить вариант</button>
        </div>

        <div class="form-group">
            <label class="form-label" for="category">Категория *</label>
            <select id="category" name="category" class="form-control" required>
                <option value="heroes" {{ old('category', $question->category) == 'heroes' ? 'selected' : '' }}>Герои</option>
                <option value="abilities" {{ old('category', $question->category) == 'abilities' ? 'selected' : '' }}>Способности</option>
                <option value="items" {{ old('category', $question->category) == 'items' ? 'selected' : '' }}>Предметы</option>
                <option value="lore" {{ old('category', $question->category) == 'lore' ? 'selected' : '' }}>Лор</option>
                <option value="esports" {{ old('category', $question->category) == 'esports' ? 'selected' : '' }}>Киберспорт</option>
                <option value="memes" {{ old('category', $question->category) == 'memes' ? 'selected' : '' }}>Мемы</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" for="difficulty">Сложность *</label>
            <select id="difficulty" name="difficulty" class="form-control" required>
                <option value="easy" {{ old('difficulty', $question->difficulty) == 'easy' ? 'selected' : '' }}>Легкий (+1 очко)</option>
                <option value="medium" {{ old('difficulty', $question->difficulty) == 'medium' ? 'selected' : '' }}>Средний (+3 очка)</option>
                <option value="hard" {{ old('difficulty', $question->difficulty) == 'hard' ? 'selected' : '' }}>Сложный (+5 очков)</option>
            </select>
        </div>

        <div class="form-group" id="image-upload-group" style="display: none;">
            <label class="form-label">Загрузить новое изображение</label>
            @if($question->image_url || $question->image_file_id)
                <div style="margin-bottom: 10px;">
                    <strong>Текущее изображение:</strong><br>
                    @if($question->image_url)
                        <img src="{{ $question->image_url }}" alt="Current image" style="max-width: 300px; max-height: 300px; border: 1px solid #ddd; border-radius: 4px; margin-top: 5px;">
                    @else
                        <span class="badge badge-info">Используется File ID из Telegram</span>
                    @endif
                    <br>
                    <label style="margin-top: 10px; display: inline-block;">
                        <input type="checkbox" name="remove_image" value="1"> Удалить изображение
                    </label>
                </div>
            @endif
            <input type="file" id="image_file" name="image_file" class="form-control" accept="image/*" onchange="previewImage(this)">
            <small class="form-text text-muted">Поддерживаемые форматы: JPEG, PNG, JPG, GIF, WEBP (до 10MB)</small>
            <div id="image-preview" style="margin-top: 10px; display: none;">
                <img id="preview-img" src="" alt="Preview" style="max-width: 300px; max-height: 300px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
        </div>

        <div class="form-group" id="image-url-group" style="display: none;">
            <label class="form-label" for="image_url">Или указать URL изображения</label>
            <input type="url" id="image_url" name="image_url" class="form-control" value="{{ old('image_url', $question->image_url) }}" placeholder="https://example.com/image.jpg">
        </div>

        <div class="form-group" id="image-file-id-group" style="display: none;">
            <label class="form-label" for="image_file_id">Или File ID изображения (Telegram)</label>
            <input type="text" id="image_file_id" name="image_file_id" class="form-control" value="{{ old('image_file_id', $question->image_file_id) }}" placeholder="AgACAgIAAxkBAAIB...">
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-success">Сохранить изменения</button>
            <a href="{{ route('admin.questions.index') }}" class="btn btn-secondary">Отмена</a>
        </div>
    </form>
</div>

<script>
function addWrongAnswer() {
    const container = document.getElementById('wrong-answers-container');
    const input = document.createElement('input');
    input.type = 'text';
    input.name = 'wrong_answers[]';
    input.className = 'form-control';
    input.style.marginBottom = '10px';
    input.placeholder = 'Неправильный ответ';
    container.appendChild(input);
}

function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('preview-img').src = e.target.result;
            document.getElementById('image-preview').style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Показать/скрыть поля для изображения в зависимости от типа вопроса
document.getElementById('question_type').addEventListener('change', function() {
    const questionType = this.value;
    const imageUploadGroup = document.getElementById('image-upload-group');
    const imageUrlGroup = document.getElementById('image-url-group');
    const imageFileIdGroup = document.getElementById('image-file-id-group');
    
    if (questionType === 'image') {
        imageUploadGroup.style.display = 'block';
        imageUrlGroup.style.display = 'block';
        imageFileIdGroup.style.display = 'block';
    } else {
        imageUploadGroup.style.display = 'none';
        imageUrlGroup.style.display = 'none';
        imageFileIdGroup.style.display = 'none';
    }
});

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('question_type').dispatchEvent(new Event('change'));
});
</script>
@endsection
