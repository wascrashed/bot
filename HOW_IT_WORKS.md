# Как работает новая структура ответов

## Структура в таблице `questions`:

### Поля:
- **`correct_answer`** = индекс (0, 1, 2...) - указывает на позицию правильного ответа в массиве
- **`correct_answer_text`** = текст правильного ответа (например, "Techies")

### Пример из вашей БД:
```
id: 85
correct_answer: 0          ← это индекс (0 = первый в массиве)
correct_answer_text: Techies  ← это текст правильного ответа
wrong_answers: ["Pudge", "Abaddon", "Undying"]
```

## Как это работает:

### 1. При создании вопроса:
- Пользователь вводит текст правильного ответа: "Techies"
- Система сохраняет:
  - `correct_answer` = `0` (индекс, так как правильный ответ всегда первый)
  - `correct_answer_text` = `"Techies"` (текст)

### 2. При создании викторины:
- Берем все ответы: `["Techies", "Pudge", "Abaddon", "Undying"]`
- Перемешиваем: например, `["Pudge", "Techies", "Abaddon", "Undying"]`
- Находим индекс "Techies" в перемешанном массиве: `1`
- Сохраняем в `active_quizzes.correct_answer_index` = `1`

### 3. При проверке ответа:
- Пользователь нажимает кнопку с индексом `1`
- Сравниваем: `1` (выбранный) === `1` (правильный из active_quizzes)
- ✅ Правильно!

## Преимущества:

1. **Точность** - сравнение по индексу, а не по тексту
2. **Скорость** - сравнение чисел быстрее строк
3. **Надежность** - не зависит от регистра, пробелов и т.д.

## Как проверить, что всё правильно:

### 1. Проверить структуру вопросов:
```bash
php artisan questions:check
```

### 2. Проверить конкретный вопрос:
```sql
SELECT 
    id,
    question,
    correct_answer AS 'Индекс',
    correct_answer_text AS 'Текст ответа',
    wrong_answers AS 'Неправильные'
FROM questions 
WHERE id = 85;
```

### 3. Проверить активную викторину:
```sql
SELECT 
    aq.id,
    aq.correct_answer_index AS 'Правильный индекс',
    q.correct_answer_text AS 'Правильный текст',
    aq.answers_order AS 'Порядок ответов'
FROM active_quizzes aq
JOIN questions q ON aq.question_id = q.id
WHERE aq.is_active = 1;
```

### 4. Проверить результаты:
```sql
SELECT 
    qr.id,
    qr.answer AS 'Сохраненный индекс',
    qr.is_correct AS 'Правильно',
    aq.answers_order AS 'Порядок ответов'
FROM quiz_results qr
JOIN active_quizzes aq ON qr.active_quiz_id = aq.id
ORDER BY qr.id DESC
LIMIT 10;
```

## Важно:

- **`correct_answer`** = индекс в исходном массиве (до перемешивания) = всегда `0` для multiple_choice
- **`correct_answer_text`** = текст правильного ответа
- **`active_quizzes.correct_answer_index`** = индекс в перемешанном массиве (может быть 0, 1, 2, 3...)
- **`quiz_results.answer`** = индекс выбранного ответа (0, 1, 2, 3...)

## Если видите проблему:

1. Проверьте миграции: `php artisan migrate:status`
2. Проверьте структуру: `php artisan webhook:diagnose-500`
3. Конвертируйте вопросы: `php artisan questions:convert-to-index`
