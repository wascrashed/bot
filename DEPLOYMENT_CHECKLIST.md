# Чеклист для деплоя исправлений викторины

## Изменения в этом обновлении:

1. ✅ Добавлено поле `correct_answer_index` в таблицу `active_quizzes`
2. ✅ Добавлено поле `correct_answer_text` в таблицу `questions`
3. ✅ Изменена логика: `correct_answer` теперь хранит индекс (0, 1, 2...) вместо текста
4. ✅ Изменена логика проверки ответов - сравнение по индексу вместо текста
5. ✅ Сохранение индекса ответа в БД вместо текста для вопросов с выбором
6. ✅ Ускорена обработка ответов - уведомление отправляется сразу
7. ✅ Добавлен метод `getCorrectAnswerText()` в модель Question
8. ✅ Добавлен метод `getAnswerText()` в модель QuizResult для корректного отображения ответов

## Файлы, которые были изменены:

### Миграции:
- `database/migrations/2026_01_12_125241_add_correct_answer_index_to_active_quizzes_table.php` (НОВЫЙ)
- `database/migrations/2026_01_12_130620_add_correct_answer_text_to_questions_table.php` (НОВЫЙ)

### Модели:
- `app/Models/ActiveQuiz.php` - добавлено поле `correct_answer_index` в fillable
- `app/Models/Question.php` - добавлено поле `correct_answer_text`, изменена логика работы с `correct_answer` (теперь индекс), добавлен метод `getCorrectAnswerText()`
- `app/Models/QuizResult.php` - добавлен метод `getAnswerText()`

### Сервисы:
- `app/Services/QuizService.php` - изменена логика проверки и сохранения ответов

### Команды:
- `app/Console/Commands/FixQuizAnswers.php` (НОВЫЙ) - команда для исправления данных
- `app/Console/Commands/ConvertQuestionsToIndex.php` (НОВЫЙ) - команда для конвертации correct_answer в индекс
- `app/Console/Commands/TestWebhook.php` - обновлено отображение ответов
- `app/Console/Commands/DiagnoseQuiz.php` - обновлено отображение ответов
- `app/Console/Commands/CheckQuizStatus.php` - обновлено отображение ответов

### Контроллеры:
- `app/Http/Controllers/TelegramWebhookController.php` - улучшено логирование для /status
- `app/Http/Controllers/Admin/QuestionController.php` - обновлена логика сохранения вопросов (correct_answer -> индекс, correct_answer_text -> текст)

### Представления:
- `resources/views/admin/questions/edit.blade.php` - обновлено для отображения correct_answer_text

## Инструкция по деплою:

### 1. Загрузить изменения на сервер:
```bash
git pull
```

### 2. Выполнить миграцию:
```bash
php artisan migrate
```

### 3. Конвертировать существующие вопросы (correct_answer -> индекс):
```bash
# Проверить что будет изменено
php artisan questions:convert-to-index --dry-run

# Применить изменения
php artisan questions:convert-to-index
```

### 4. (Опционально) Проверить что будет исправлено:
```bash
php artisan quiz:fix-answers --dry-run
```

### 5. Исправить существующие активные викторины:
```bash
php artisan quiz:fix-answers
```

### 6. Проверить работу:
- Создайте новую викторину
- Выберите правильный ответ
- Проверьте в БД:
- В `questions.correct_answer` должен быть индекс (0, 1, 2...)
- В `questions.correct_answer_text` должен быть текст правильного ответа
- В `active_quizzes.correct_answer_index` должен быть индекс (0, 1, 2...)
- В `quiz_results.answer` должен быть индекс для вопросов с выбором
- В `quiz_results.is_correct` должен быть `1` для правильных ответов

## Проверка после деплоя:

1. Проверить структуру БД:
```sql
SHOW COLUMNS FROM active_quizzes LIKE 'correct_answer_index';
```

2. Проверить логи:
```bash
tail -50 storage/logs/laravel.log | grep -i "answer check by index"
```

3. Проверить работу викторины:
- Отправить викторину в группе
- Выбрать правильный ответ
- Убедиться, что уведомление приходит быстро
- Убедиться, что правильный ответ определяется корректно

## Откат (если что-то пошло не так):

```bash
php artisan migrate:rollback --step=1
```

Это удалит колонку `correct_answer_index`, но старые данные останутся.
