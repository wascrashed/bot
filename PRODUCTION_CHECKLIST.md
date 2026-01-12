# Чеклист для прода: Нужно ли менять БД?

## Текущая логика (после изменений):

✅ **Сравнение ответов идет по ТЕКСТУ** (не по индексу)
✅ В `quiz_results.answer` сохраняется **ТЕКСТ** ответа
✅ Используется метод `getCorrectAnswerText()` для получения правильного ответа

## Что нужно проверить на проде:

### 1. Проверить наличие колонки `correct_answer_text`:

```sql
SHOW COLUMNS FROM questions LIKE 'correct_answer_text';
```

**Если колонки НЕТ:**
- ❌ Метод `getCorrectAnswerText()` будет работать через fallback, но это ненадежно
- ❌ Рекомендуется применить миграцию

**Если колонка ЕСТЬ:**
- ✅ Всё хорошо, можно не менять

### 2. Проверить наличие колонки `correct_answer_index`:

```sql
SHOW COLUMNS FROM active_quizzes LIKE 'correct_answer_index';
```

**Если колонки НЕТ:**
- ⚠️ Не критично, но полезно для отладки
- ⚠️ Можно оставить как есть или добавить позже

**Если колонка ЕСТЬ:**
- ✅ Всё хорошо

## Рекомендация:

### Вариант 1: МИНИМАЛЬНЫЕ ИЗМЕНЕНИЯ (если `correct_answer_text` уже есть)

```bash
# Только проверить, что всё работает
php artisan db:check-structure
php artisan quiz:test-answer-logic
```

**Можно ничего не менять**, если:
- ✅ Колонка `correct_answer_text` существует
- ✅ В ней есть данные для всех вопросов

### Вариант 2: ПРИМЕНИТЬ МИГРАЦИИ (рекомендуется)

```bash
# 1. Применить миграции (безопасно, только добавляет колонки)
php artisan migrate

# 2. Проверить структуру
php artisan db:check-structure

# 3. Конвертировать существующие вопросы (если нужно)
php artisan questions:convert-to-index

# 4. Проверить работу
php artisan quiz:test-answer-logic
```

**Применить миграции**, если:
- ❌ Колонка `correct_answer_text` отсутствует
- ⚠️ Хотите иметь полную совместимость с локальной версией
- ⚠️ Хотите иметь `correct_answer_index` для отладки

## Безопасность миграций:

✅ **Безопасно применять:**
- Не удаляют данные
- Не изменяют существующие колонки (кроме `correct_answer` в `questions`)
- Можно откатить: `php artisan migrate:rollback --step=2`

## Что изменится:

### Таблица `questions`:
- Добавится `correct_answer_text` (string, nullable)
- Если `correct_answer` был текстом → переносится в `correct_answer_text`, `correct_answer` → '0'

### Таблица `active_quizzes`:
- Добавится `correct_answer_index` (integer, nullable)
- Существующие данные не изменятся

### Таблица `quiz_results`:
- Ничего не изменится

## Вывод:

**Если на проде уже есть `correct_answer_text`** → можно ничего не менять
**Если на проде НЕТ `correct_answer_text`** → рекомендуется применить миграции
