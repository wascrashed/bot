# Исправление ошибки 500 в webhook

## Возможные причины ошибки 500:

1. ❌ **Не выполнена миграция** - поле `correct_answer_text` отсутствует в таблице `questions`
2. ❌ **Не выполнена миграция** - поле `correct_answer_index` отсутствует в таблице `active_quizzes`
3. ❌ **Ошибка в методе `getCorrectAnswerText()`** - метод пытается обратиться к несуществующему полю
4. ❌ **Ошибка при обработке команды `/status`** - проблема с отправкой сообщения

## Что исправлено:

1. ✅ Добавлена защита в `getCorrectAnswerText()` - метод теперь безопасно обрабатывает отсутствие поля
2. ✅ Улучшена обработка ошибок в `TelegramWebhookController` - детальное логирование
3. ✅ Исправлена незавершенная строка в `QuizService` (строка 933)
4. ✅ Добавлена защита в `finishQuiz` при получении правильного ответа

## Команды для диагностики на проде:

### 1. Проверить структуру БД:
```bash
php artisan webhook:diagnose-500
```

### 2. Проверить ошибки в логах:
```bash
php artisan webhook:check-errors
```

### 3. Проверить статус миграций:
```bash
php artisan migrate:status
```

### 4. Выполнить миграции (если нужно):
```bash
php artisan migrate
```

### 5. Конвертировать вопросы (если нужно):
```bash
php artisan questions:convert-to-index
```

## Что нужно сделать на проде:

1. **Загрузить изменения:**
   ```bash
   git pull
   ```

2. **Выполнить миграции:**
   ```bash
   php artisan migrate
   ```

3. **Проверить структуру БД:**
   ```bash
   php artisan webhook:diagnose-500
   ```

4. **Проверить ошибки:**
   ```bash
   php artisan webhook:check-errors
   ```

5. **Проверить webhook:**
   ```bash
   php artisan telegram:check-webhook
   ```

## Если ошибка 500 продолжается:

1. Проверьте логи: `storage/logs/laravel.log` и `storage/logs/webhook_errors.log`
2. Убедитесь, что все миграции выполнены
3. Проверьте, что метод `getCorrectAnswerText()` работает: `php artisan questions:check`
4. Проверьте права на запись в `storage/logs/`
