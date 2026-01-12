# Список измененных файлов для деплоя

## Новые файлы:
1. `database/migrations/2026_01_12_125241_add_correct_answer_index_to_active_quizzes_table.php`
2. `app/Console/Commands/FixQuizAnswers.php`
3. `DEPLOYMENT_CHECKLIST.md` (этот файл можно удалить после деплоя)

## Измененные файлы:

### Модели:
- `app/Models/ActiveQuiz.php` - добавлено `correct_answer_index` в fillable
- `app/Models/QuizResult.php` - добавлен метод `getAnswerText()`

### Сервисы:
- `app/Services/QuizService.php` - изменена логика проверки и сохранения ответов

### Команды:
- `app/Console/Commands/TestWebhook.php` - обновлено отображение ответов
- `app/Console/Commands/DiagnoseQuiz.php` - обновлено отображение ответов
- `app/Console/Commands/CheckQuizStatus.php` - обновлено отображение ответов

### Контроллеры:
- `app/Http/Controllers/TelegramWebhookController.php` - улучшено логирование для /status

## Проверка перед коммитом:

✅ Миграция создана и корректна
✅ Модель ActiveQuiz обновлена
✅ Модель QuizResult обновлена
✅ QuizService обновлен с логикой сравнения по индексу
✅ Команды обновлены для отображения ответов
✅ Создана команда FixQuizAnswers для исправления данных

## Команды для проверки на проде:

```bash
# 1. Проверить миграцию
php artisan migrate:status

# 2. Выполнить миграцию
php artisan migrate

# 3. Проверить что будет исправлено
php artisan quiz:fix-answers --dry-run

# 4. Исправить данные
php artisan quiz:fix-answers

# 5. Проверить работу
php artisan quiz:diagnose
```
