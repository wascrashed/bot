#!/bin/bash
# Скрипт для запуска Laravel scheduler и queue worker
# Используется в cron для автоматического выполнения задач

# Определить директорию скрипта (где находится run_cron.sh)
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
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

# Обработать все готовые задачи из очереди (до 10 задач за раз)
# Обрабатываем несколько задач, чтобы не пропустить отложенные задачи
QUEUE_PROCESSED=0
MAX_QUEUE_ATTEMPTS=10

for i in $(seq 1 $MAX_QUEUE_ATTEMPTS); do
    /usr/bin/php artisan queue:work --once --sleep=0 --tries=3 --timeout=120 >> "$LOG_FILE" 2>&1
    QUEUE_EXIT=$?
    
    # Если нет задач для обработки (exit code 1 обычно означает "no jobs"), выходим
    if [ $QUEUE_EXIT -ne 0 ]; then
        break
    fi
    
    QUEUE_PROCESSED=$((QUEUE_PROCESSED + 1))
done

if [ $QUEUE_PROCESSED -gt 0 ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Queue:work completed ($QUEUE_PROCESSED tasks processed)" >> "$LOG_FILE"
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Queue:work - no tasks to process" >> "$LOG_FILE"
fi
QUEUE_EXIT=0  # Считаем успешным, даже если задач не было

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
