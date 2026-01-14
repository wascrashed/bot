# Исправление: Викторина длится 20 сек, а результаты через минуту

## Проблема

Викторина истекает через 20 секунд, но команда `quiz:finish-stuck` запускается только каждую минуту через Laravel scheduler. Это означает задержку до 40 секунд перед показом результатов.

## Решение

Запускать команду `quiz:finish-stuck` каждые 10 секунд.

## Быстрое решение (для прода)

### Вариант 1: Добавить в crontab несколько записей

Откройте crontab:
```bash
crontab -e
```

Добавьте 6 записей (запуск каждые 10 секунд):
```bash
* * * * * cd /home/ce528895/public_html && sleep 0 && php artisan quiz:finish-stuck >> /dev/null 2>&1
* * * * * cd /home/ce528895/public_html && sleep 10 && php artisan quiz:finish-stuck >> /dev/null 2>&1
* * * * * cd /home/ce528895/public_html && sleep 20 && php artisan quiz:finish-stuck >> /dev/null 2>&1
* * * * * cd /home/ce528895/public_html && sleep 30 && php artisan quiz:finish-stuck >> /dev/null 2>&1
* * * * * cd /home/ce528895/public_html && sleep 40 && php artisan quiz:finish-stuck >> /dev/null 2>&1
* * * * * cd /home/ce528895/public_html && sleep 50 && php artisan quiz:finish-stuck >> /dev/null 2>&1
```

### Вариант 2: Использовать скрипт в фоне

```bash
cd /home/ce528895/public_html
nohup bash finish_quizzes_loop.sh > /dev/null 2>&1 &
```

Проверить, что работает:
```bash
ps aux | grep finish_quizzes_loop
```

## Результат

После настройки результаты викторины будут показываться **сразу после истечения 20 секунд** (максимальная задержка 10 секунд вместо 40+).

## Проверка

```bash
# Проверить работу команды
php artisan quiz:finish-stuck

# Проверить логи
tail -f storage/logs/laravel.log | grep "finish-stuck"
```
