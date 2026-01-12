#!/bin/bash
# Скрипт для запуска Laravel scheduler и queue worker
# Используется в cron для автоматического выполнения задач

cd /home/ce528895/public_html || exit 1

# Запустить Laravel scheduler (проверит все запланированные команды)
/usr/bin/php artisan schedule:run >> /dev/null 2>&1

# Обработать одну задачу из очереди (если есть)
/usr/bin/php artisan queue:work --once --sleep=3 --tries=3 --timeout=120 >> /dev/null 2>&1
