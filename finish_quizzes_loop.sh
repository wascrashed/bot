#!/bin/bash
# Скрипт для запуска команды завершения викторин каждые 10 секунд
# Используется для немедленного завершения викторин после истечения времени

# Определить директорию скрипта
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
LOG_FILE="$SCRIPT_DIR/storage/logs/finish_quizzes.log"

cd "$SCRIPT_DIR" || exit 1

# Создать лог-файл, если его нет
touch "$LOG_FILE"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting finish quizzes loop (every 10 seconds)" >> "$LOG_FILE"

# Бесконечный цикл
while true; do
    /usr/bin/php artisan quiz:finish-stuck >> "$LOG_FILE" 2>&1
    sleep 10
done
