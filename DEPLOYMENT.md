# 🚀 Инструкция по развертыванию

## Системные требования

- PHP >= 8.1
- Composer
- MySQL/PostgreSQL/SQLite
- Telegram Bot Token (получить у @BotFather)
- Публичный URL для webhook (или ngrok для разработки)

## Пошаговая установка

### Шаг 1: Установка зависимостей

```bash
composer install
```

### Шаг 2: Настройка окружения

```bash
cp .env.example .env
php artisan key:generate
```

Отредактируйте `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dota2_quiz_bot
DB_USERNAME=root
DB_PASSWORD=your_password

TELEGRAM_BOT_TOKEN=your_bot_token_here
TELEGRAM_WEBHOOK_URL=https://yourdomain.com/webhook/telegram

QUEUE_CONNECTION=database
CACHE_STORE=database
```

### Шаг 3: Создание базы данных

```sql
CREATE DATABASE dota2_quiz_bot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Шаг 4: Миграции и сиды

```bash
php artisan migrate
php artisan db:seed --class=Dota2QuestionsSeeder
```

Для расширенной базы (1000+ вопросов):
```bash
php artisan db:seed --class=ExtendedDota2QuestionsSeeder
```

### Шаг 5: Проверка бота

```bash
php artisan telegram:bot-info
```

### Шаг 6: Настройка webhook

```bash
php artisan telegram:set-webhook
```

Или вручную указать URL:
```bash
php artisan telegram:set-webhook https://yourdomain.com/webhook/telegram
```

### Шаг 7: Настройка планировщика

#### Linux/Mac (Cron)

```bash
crontab -e
```

Добавьте:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

#### Windows (Task Scheduler)

1. Откройте Task Scheduler
2. Создайте задачу
3. Триггер: каждую минуту
4. Действие: запуск `php artisan schedule:run`
5. Рабочая директория: путь к проекту

### Шаг 8: Запуск очередей (для асинхронной обработки)

```bash
php artisan queue:work
```

Или с supervisor для продакшена.

## Настройка бота в Telegram

1. Создайте бота через @BotFather
2. Получите токен
3. Добавьте бота в группу/супергруппу
4. **ВАЖНО:** Дайте боту права администратора!
5. Отправьте любое сообщение в группу, чтобы бот узнал о чате
6. Бот автоматически начнет проводить викторины каждые 6 минут

## Проверка работы

### Проверить статус планировщика

```bash
php artisan schedule:list
```

### Проверить аналитику

```bash
php artisan analytics:update
```

### Проверить таблицу лидеров

```bash
php artisan quiz:leaderboard
```

### Проверить логи

```bash
tail -f storage/logs/laravel.log
```

## Оптимизация для продакшена

### 1. Настройка очередей (Supervisor)

Создайте файл `/etc/supervisor/conf.d/dota2-quiz-bot.conf`:

```ini
[program:dota2-quiz-bot-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path-to-project/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/path-to-project/storage/logs/worker.log
stopwaitsecs=3600
```

### 2. Оптимизация PHP

В `php.ini`:
```ini
memory_limit=256M
max_execution_time=300
opcache.enable=1
opcache.memory_consumption=128
```

### 3. Оптимизация базы данных

Добавьте индексы:
```sql
ALTER TABLE active_quizzes ADD INDEX idx_chat_active (chat_id, is_active);
ALTER TABLE quiz_results ADD INDEX idx_user_quiz (user_id, active_quiz_id);
ALTER TABLE question_history ADD INDEX idx_chat_date (chat_id, asked_at);
```

### 4. Кэширование конфигурации

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Мониторинг

### Команды для проверки

```bash
# Статистика очередей
php artisan queue:stats

# Список активных викторин
php artisan tinker
>>> App\Models\ActiveQuiz::where('is_active', true)->count();

# Количество активных чатов
>>> App\Models\ChatStatistics::where('is_active', true)->count();

# Статистика за сегодня
>>> App\Models\BotAnalytics::getToday();
```

## Устранение неполадок

### Бот не отвечает

1. Проверьте webhook:
```bash
curl https://api.telegram.org/bot<YOUR_TOKEN>/getWebhookInfo
```

2. Проверьте логи:
```bash
tail -f storage/logs/laravel.log
```

3. Проверьте права администратора:
```bash
php artisan tinker
>>> $telegram = new \App\Services\TelegramService();
>>> $telegram->isBotAdmin($chatId);
```

### Викторины не запускаются

1. Проверьте планировщик:
```bash
php artisan schedule:run -v
```

2. Проверьте, что бот добавлен в группы
3. Проверьте, что есть вопросы в базе:
```bash
php artisan tinker
>>> App\Models\Question::count();
```

### Ошибки Rate Limiting

Если видите ошибки 429:
- Увеличьте задержки между запросами
- Уменьшите размер батчей
- Проверьте настройки rate limiting в `TelegramService`

### Ошибки базы данных

1. Проверьте подключение:
```bash
php artisan tinker
>>> DB::connection()->getPdo();
```

2. Проверьте миграции:
```bash
php artisan migrate:status
```

## Производительность

Для работы в 50+ чатах рекомендуется:

1. **Настроить очереди** (обязательно)
2. **Увеличить количество воркеров** (4-8 процессов)
3. **Настроить кэширование** (Redis рекомендуется)
4. **Оптимизировать базу данных** (индексы)
5. **Мониторить производительность** (логи, аналитика)

## Резервное копирование

Рекомендуется регулярное резервное копирование:

```bash
# База данных
mysqldump -u root -p dota2_quiz_bot > backup_$(date +%Y%m%d).sql

# Файлы проекта
tar -czf backup_$(date +%Y%m%d).tar.gz /path-to-project --exclude=vendor --exclude=node_modules
```

## Обновление

```bash
git pull
composer install --no-dev
php artisan migrate
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

## Поддержка

При возникновении проблем:
1. Проверьте логи: `storage/logs/laravel.log`
2. Проверьте статус сервисов (queues, scheduler)
3. Проверьте настройки в `.env`
4. Проверьте права доступа к файлам и директориям
