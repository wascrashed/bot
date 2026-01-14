# Настройка Cron для завершения викторин каждые 10 секунд

## Проблема

Викторина истекает через 20 секунд, но команда `quiz:finish-stuck` запускается только каждую минуту через Laravel scheduler. Это означает, что результаты показываются с задержкой до 40 секунд.

## Решение

Запускать команду `quiz:finish-stuck` каждые 10 секунд через cron напрямую.

## Вариант 1: Cron с секундами (если поддерживается)

Добавьте в crontab:

```bash
*/10 * * * * * cd /path-to-your-project && php artisan quiz:finish-stuck >> /dev/null 2>&1
```

**Примечание:** Не все системы поддерживают секунды в cron. Если не работает, используйте Вариант 2.

## Вариант 2: Несколько записей в cron (рекомендуется)

Добавьте в crontab 6 записей с разными секундами:

```bash
# Запускать каждые 10 секунд (0, 10, 20, 30, 40, 50 секунд каждой минуты)
* * * * * cd /path-to-your-project && sleep 0 && php artisan quiz:finish-stuck >> /dev/null 2>&1
* * * * * cd /path-to-your-project && sleep 10 && php artisan quiz:finish-stuck >> /dev/null 2>&1
* * * * * cd /path-to-your-project && sleep 20 && php artisan quiz:finish-stuck >> /dev/null 2>&1
* * * * * cd /path-to-your-project && sleep 30 && php artisan quiz:finish-stuck >> /dev/null 2>&1
* * * * * cd /path-to-your-project && sleep 40 && php artisan quiz:finish-stuck >> /dev/null 2>&1
* * * * * cd /path-to-your-project && sleep 50 && php artisan quiz:finish-stuck >> /dev/null 2>&1
```

## Вариант 3: Использовать скрипт (самый простой)

Создайте файл `finish_quizzes_loop.sh`:

```bash
#!/bin/bash
cd /path-to-your-project
while true; do
    php artisan quiz:finish-stuck >> /dev/null 2>&1
    sleep 10
done
```

Запустите его в фоне:

```bash
nohup ./finish_quizzes_loop.sh > /dev/null 2>&1 &
```

Или через systemd/supervisor для автоматического запуска при перезагрузке.

## Проверка

После настройки проверьте:

```bash
# Проверить, что команда запускается
php artisan quiz:finish-stuck

# Проверить логи
tail -f storage/logs/laravel.log | grep "finish-stuck"
```

## Для прода (ce528895.tw1.ru)

Если вы используете хостинг с ограниченным доступом к cron, используйте Вариант 3 (скрипт в фоне) или добавьте несколько записей в cron через панель управления хостингом.
