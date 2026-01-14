# Исправление ошибки загрузки изображений

## Проблема

Ошибка: `InvalidArgumentException: Disk [] does not have a configured driver.`

Происходит при попытке загрузить изображение для вопроса в админ-панели.

## Причина

1. Отсутствовал файл `config/filesystems.php` с конфигурацией дисков
2. В коде использовался `storeAs()` без явного указания диска

## Исправления

### 1. Создан `config/filesystems.php`

Файл содержит конфигурацию для дисков `local` и `public`.

### 2. Исправлен `QuestionController.php`

- Добавлен `use Illuminate\Support\Facades\Storage;`
- Изменен `$image->storeAs('public/questions', $filename)` на `$image->storeAs('questions', $filename, 'public')`
- Добавлено автоматическое создание директории: `Storage::disk('public')->makeDirectory('questions')`
- Исправлено удаление файлов: `Storage::disk('public')->delete('questions/' . $oldFilename)`

### 3. Создана символическая ссылка

Выполнена команда: `php artisan storage:link`

## Что нужно сделать на проде

1. Загрузить файл `config/filesystems.php` на сервер
2. Загрузить обновленный `app/Http/Controllers/Admin/QuestionController.php`
3. Выполнить на сервере:
   ```bash
   php artisan storage:link
   ```
4. Убедиться, что директория существует:
   ```bash
   mkdir -p storage/app/public/questions
   chmod -R 775 storage/app/public/questions
   ```

## Проверка

После исправлений загрузка изображений должна работать без ошибок.
