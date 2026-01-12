# Быстрый старт

## Шаг 1: Установка зависимостей

```bash
composer install
```

## Шаг 2: Настройка окружения

```bash
cp .env.example .env
php artisan key:generate
```

Отредактируйте `.env`:
- Укажите настройки базы данных
- Укажите `TELEGRAM_BOT_TOKEN` (получить у @BotFather)
- Укажите `TELEGRAM_WEBHOOK_URL` (публичный URL вашего сервера)

## Шаг 3: Создание базы данных

```bash
# Создайте базу данных в MySQL/PostgreSQL
# Затем выполните миграции:
php artisan migrate
```

## Шаг 4: Заполнение вопросами

```bash
php artisan db:seed --class=Dota2QuestionsSeeder
```

## Шаг 5: Проверка бота

```bash
php artisan telegram:bot-info
```

## Шаг 6: Настройка вебхука

```bash
php artisan telegram:set-webhook
```

## Шаг 7: Настройка планировщика

Добавьте в crontab (Linux/Mac):
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

Или для Windows используйте Task Scheduler.

## Шаг 8: Добавление бота в группу

1. Добавьте бота в Telegram группу/супергруппу
2. Отправьте любое сообщение в группу
3. Бот автоматически начнет проводить викторины каждые 6 минут

## Ручной запуск викторины

```bash
php artisan quiz:start-random
```

## Полезные команды

- `php artisan telegram:bot-info` - информация о боте
- `php artisan telegram:set-webhook` - установить вебхук
- `php artisan quiz:start-random` - запустить викторину вручную
- `php artisan schedule:list` - список запланированных задач
- `php artisan migrate:status` - статус миграций

## Логи

Логи находятся в `storage/logs/laravel.log`

## Поддержка

При возникновении проблем:
1. Проверьте логи: `storage/logs/laravel.log`
2. Проверьте настройки в `.env`
3. Убедитесь, что планировщик работает
4. Проверьте, что вебхук настроен правильно
