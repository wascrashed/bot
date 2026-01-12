#!/bin/bash
# Скрипт для запуска Laravel scheduler и queue worker
# Используется в cron для автоматического выполнения задач

SCRIPT_DIR="/home/ce528895/public_html"
LOG_FILE="$SCRIPT_DIR/storage/logs/cron.log"

cd "$SCRIPT_DIR" || exit 1

# Создать лог-файл, если его нет
touch "$LOG_FILE"

# Логировать время запуска
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Cron started" >> "$LOG_FILE"

# Запустить Laravel scheduler (проверит все запланированные команды)
/usr/bin/php artisan schedule:run >> "$LOG_FILE" 2>&1
SCHEDULE_EXIT=$?

if [ $SCHEDULE_EXIT -eq 0 ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Schedule:run completed" >> "$LOG_FILE"
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Schedule:run failed with exit code $SCHEDULE_EXIT" >> "$LOG_FILE"
fi

# Обработать одну задачу из очереди (если есть)
/usr/bin/php artisan queue:work --once --sleep=3 --tries=3 --timeout=120 >> "$LOG_FILE" 2>&1
QUEUE_EXIT=$?

if [ $QUEUE_EXIT -eq 0 ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Queue:work completed" >> "$LOG_FILE"
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Queue:work failed with exit code $QUEUE_EXIT" >> "$LOG_FILE"
fi

# Определить общий статус выполнения
if [ $SCHEDULE_EXIT -eq 0 ] && [ $QUEUE_EXIT -eq 0 ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Cron finished successfully (exit code: 0)" >> "$LOG_FILE"
    EXIT_CODE=0
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Cron finished with errors (exit code: 1)" >> "$LOG_FILE"
    EXIT_CODE=1
fi
echo "---" >> "$LOG_FILE"

# Ограничить размер лог-файла (оставить последние 1000 строк)
tail -n 1000 "$LOG_FILE" > "$LOG_FILE.tmp" && mv "$LOG_FILE.tmp" "$LOG_FILE"

exit $EXIT_CODE
