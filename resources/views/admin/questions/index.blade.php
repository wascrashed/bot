@extends('admin.layout')

@section('title', 'Вопросы')
@section('page-title', 'Управление вопросами')

@section('content')
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2>Список вопросов</h2>
        <a href="{{ route('admin.questions.create') }}" class="btn btn-primary">+ Добавить вопрос</a>
    </div>

    <form method="GET" action="{{ route('admin.questions.index') }}" style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
        <input type="text" name="search" class="form-control" placeholder="Поиск..." value="{{ request('search') }}" style="flex: 1; min-width: 200px;">
        <select name="category" class="form-control" style="width: 150px;">
            <option value="">Все категории</option>
            <option value="heroes" {{ request('category') == 'heroes' ? 'selected' : '' }}>Герои</option>
            <option value="abilities" {{ request('category') == 'abilities' ? 'selected' : '' }}>Способности</option>
            <option value="items" {{ request('category') == 'items' ? 'selected' : '' }}>Предметы</option>
            <option value="lore" {{ request('category') == 'lore' ? 'selected' : '' }}>Лор</option>
            <option value="esports" {{ request('category') == 'esports' ? 'selected' : '' }}>Киберспорт</option>
            <option value="memes" {{ request('category') == 'memes' ? 'selected' : '' }}>Мемы</option>
        </select>
        <select name="type" class="form-control" style="width: 150px;">
            <option value="">Все типы</option>
            <option value="multiple_choice" {{ request('type') == 'multiple_choice' ? 'selected' : '' }}>Выбор</option>
            <option value="text" {{ request('type') == 'text' ? 'selected' : '' }}>Текст</option>
            <option value="true_false" {{ request('type') == 'true_false' ? 'selected' : '' }}>Верно/Неверно</option>
            <option value="image" {{ request('type') == 'image' ? 'selected' : '' }}>Картинка</option>
        </select>
        <select name="difficulty" class="form-control" style="width: 120px;">
            <option value="">Все уровни</option>
            <option value="easy" {{ request('difficulty') == 'easy' ? 'selected' : '' }}>Легкий</option>
            <option value="medium" {{ request('difficulty') == 'medium' ? 'selected' : '' }}>Средний</option>
            <option value="hard" {{ request('difficulty') == 'hard' ? 'selected' : '' }}>Сложный</option>
        </select>
        <button type="submit" class="btn btn-primary">Фильтровать</button>
        <a href="{{ route('admin.questions.index') }}" class="btn btn-secondary">Сбросить</a>
    </form>

    @if($questions->count() > 0)
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Вопрос</th>
                    <th>Категория</th>
                    <th>Тип</th>
                    <th>Сложность</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                @foreach($questions as $question)
                <tr>
                    <td>{{ $question->id }}</td>
                    <td>
                        {{ Str::limit($question->question, 60) }}
                        @if($question->image_url || $question->image_file_id)
                            <span class="badge badge-info" title="Есть изображение">🖼️</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $categories = ['heroes' => 'Герои', 'abilities' => 'Способности', 'items' => 'Предметы', 'lore' => 'Лор', 'esports' => 'Киберспорт', 'memes' => 'Мемы'];
                            echo $categories[$question->category] ?? $question->category;
                        @endphp
                    </td>
                    <td>
                        @php
                            $types = ['multiple_choice' => 'Выбор', 'text' => 'Текст', 'true_false' => 'В/Н', 'image' => 'Картинка'];
                            echo $types[$question->question_type] ?? $question->question_type;
                        @endphp
                    </td>
                    <td>
                        @if($question->difficulty == 'easy')
                            <span class="badge badge-success">Легкий</span>
                        @elseif($question->difficulty == 'medium')
                            <span class="badge badge-warning">Средний</span>
                        @else
                            <span class="badge badge-danger">Сложный</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('admin.questions.edit', $question) }}" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">Редактировать</a>
                        <form action="{{ route('admin.questions.destroy', $question) }}" method="POST" style="display: inline;" onsubmit="return confirm('Удалить этот вопрос?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;">Удалить</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="pagination">
            {{ $questions->links() }}
        </div>
    @else
        <p>Вопросы не найдены.</p>
    @endif
</div>
@endsection
