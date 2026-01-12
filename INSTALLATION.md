# Инструкция по установке

## Быстрая установка

1. **Клонируйте или распакуйте проект**

2. **Установите зависимости:**
```bash
composer install
```

3. **Настройте переменные окружения:**
```bash
cp .env.example .env
php artisan key:generate
```

Отредактируйте `.env` файл:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dota2_quiz_bot
DB_USERNAME=root
DB_PASSWORD=your_password

TELEGRAM_BOT_TOKEN=your_bot_token_here
TELEGRAM_WEBHOOK_URL=https://yourdomain.com/webhook/telegram
```

4. **Создайте базу данных:**
```sql
CREATE DATABASE dota2_quiz_bot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

5. **Запустите миграции:**
```bash
php artisan migrate
```

6. **Заполните базу данных вопросами:**
```bash
php artisan db:seed --class=Dota2QuestionsSeeder
```

7. **Проверьте, что бот работает:**
```bash
php artisan telegram:bot-info
```

8. **Настройте вебхук (требуется публичный URL):**
```bash
php artisan telegram:set-webhook
```

Или используйте ngrok для локальной разработки:
```bash
ngrok http 8000
# Затем используйте полученный URL:
php artisan telegram:set-webhook https://your-ngrok-url.ngrok.io/webhook/telegram
```

9. **Настройте планировщик:**

Для Linux/Mac добавьте в crontab:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

Для Windows используйте Task Scheduler для выполнения команды каждую минуту:
```cmd
php artisan schedule:run
```

## Локальная разработка

Для локальной разработки используйте встроенный сервер Laravel:

```bash
php artisan serve
```

Затем используйте ngrok для создания публичного URL:
```bash
ngrok http 8000
```

И установите вебхук:
```bash
php artisan telegram:set-webhook https://your-ngrok-url.ngrok.io/webhook/telegram
```

## Структура директорий storage

Создайте следующие директории, если их нет:
- `storage/app/public`
- `storage/framework/cache/data`
- `storage/framework/sessions`
- `storage/framework/testing`
- `storage/framework/views`
- `storage/logs`

Права доступа (Linux):
```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

## Проверка установки

После установки проверьте:

1. **База данных:**
```bash
php artisan migrate:status
```

2. **Бот:**
```bash
php artisan telegram:bot-info
```

3. **Вебхук:**
```bash
curl https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getWebhookInfo
```

4. **Планировщик:**
```bash
php artisan schedule:list
```

## Устранение проблем

### Ошибка "No application encryption key"
```bash
php artisan key:generate
```

### Ошибка подключения к базе данных
Проверьте настройки в `.env` и убедитесь, что база данных создана.

### Вебхук не работает
- Проверьте, что URL доступен публично
- Проверьте, что маршрут `/webhook/telegram` доступен
- Проверьте логи: `storage/logs/laravel.log`

### Викторины не запускаются
- Убедитесь, что планировщик работает
- Проверьте, что бот добавлен в группу
- Отправьте сообщение в группу, чтобы бот узнал о чате
- Проверьте логи: `storage/logs/laravel.log`
