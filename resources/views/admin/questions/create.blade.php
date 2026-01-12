@extends('admin.layout')

@section('title', 'Добавить вопрос')
@section('page-title', 'Добавить вопрос')

@section('content')
<div class="card">
    <div class="card-header">
        <h2>Создать новый вопрос</h2>
    </div>

    <form method="POST" action="{{ route('admin.questions.store') }}">
        @csrf

        <div class="form-group">
            <label class="form-label" for="question">Вопрос *</label>
            <textarea id="question" name="question" class="form-control" required>{{ old('question') }}</textarea>
        </div>

        <div class="form-group">
            <label class="form-label" for="question_type">Тип вопроса *</label>
            <select id="question_type" name="question_type" class="form-control" required>
                <option value="multiple_choice" {{ old('question_type') == 'multiple_choice' ? 'selected' : '' }}>Выбор из вариантов (кнопки)</option>
                <option value="text" {{ old('question_type') == 'text' ? 'selected' : '' }}>Текстовый ответ</option>
                <option value="true_false" {{ old('question_type') == 'true_false' ? 'selected' : '' }}>Верно/Неверно</option>
                <option value="image" {{ old('question_type') == 'image' ? 'selected' : '' }}>С изображением</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" for="correct_answer">Правильный ответ *</label>
            <input type="text" id="correct_answer" name="correct_answer" class="form-control" value="{{ old('correct_answer') }}" required>
        </div>

        <div class="form-group" id="wrong-answers-group">
            <label class="form-label">Неправильные ответы (для типа "Выбор из вариантов")</label>
            <div id="wrong-answers-container">
                @if(old('wrong_answers'))
                    @foreach(old('wrong_answers') as $answer)
                        <input type="text" name="wrong_answers[]" class="form-control" value="{{ $answer }}" style="margin-bottom: 10px;">
                    @endforeach
                @else
                    <input type="text" name="wrong_answers[]" class="form-control" style="margin-bottom: 10px;" placeholder="Неправильный ответ 1">
                    <input type="text" name="wrong_answers[]" class="form-control" style="margin-bottom: 10px;" placeholder="Неправильный ответ 2">
                    <input type="text" name="wrong_answers[]" class="form-control" style="margin-bottom: 10px;" placeholder="Неправильный ответ 3">
                @endif
            </div>
            <button type="button" class="btn btn-secondary" onclick="addWrongAnswer()" style="margin-top: 10px;">+ Добавить вариант</button>
        </div>

        <div class="form-group">
            <label class="form-label" for="category">Категория *</label>
            <select id="category" name="category" class="form-control" required>
                <option value="heroes" {{ old('category') == 'heroes' ? 'selected' : '' }}>Герои</option>
                <option value="abilities" {{ old('category') == 'abilities' ? 'selected' : '' }}>Способности</option>
                <option value="items" {{ old('category') == 'items' ? 'selected' : '' }}>Предметы</option>
                <option value="lore" {{ old('category') == 'lore' ? 'selected' : '' }}>Лор</option>
                <option value="esports" {{ old('category') == 'esports' ? 'selected' : '' }}>Киберспорт</option>
                <option value="memes" {{ old('category') == 'memes' ? 'selected' : '' }}>Мемы</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" for="difficulty">Сложность *</label>
            <select id="difficulty" name="difficulty" class="form-control" required>
                <option value="easy" {{ old('difficulty') == 'easy' ? 'selected' : '' }}>Легкий (+1 очко)</option>
                <option value="medium" {{ old('difficulty') == 'medium' ? 'selected' : '' }}>Средний (+3 очка)</option>
                <option value="hard" {{ old('difficulty') == 'hard' ? 'selected' : '' }}>Сложный (+5 очков)</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" for="image_url">URL изображения (для типа "С изображением")</label>
            <input type="url" id="image_url" name="image_url" class="form-control" value="{{ old('image_url') }}">
        </div>

        <div class="form-group">
            <label class="form-label" for="image_file_id">File ID изображения (Telegram)</label>
            <input type="text" id="image_file_id" name="image_file_id" class="form-control" value="{{ old('image_file_id') }}">
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-success">Сохранить</button>
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
</script>
@endsection
