#!/bin/bash
# Скрипт для автоматического деплоя (выполнять на сервере через SSH)

echo "🚀 Начало деплоя..."

# Переход в папку проекта
cd ~/public_html/bot

echo "📦 Установка зависимостей..."
composer install --no-dev --optimize-autoloader

echo "🔑 Генерация ключа приложения..."
php artisan key:generate

echo "📝 Настройка прав доступа..."
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

echo "🗄️  Выполнение миграций..."
php artisan migrate --force

echo "📊 Добавление вопросов..."
php artisan db:seed --class=Dota2QuestionsSeeder

echo "👤 Создание администратора..."
php artisan db:seed --class=AdminUserSeeder

echo "💾 Кеширование конфигурации..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "✅ Деплой завершен!"
echo ""
echo "📋 Следующие шаги:"
echo "1. Настройте webhook: php artisan telegram:set-webhook https://ваш-домен.ru/webhook/telegram"
echo "2. Настройте Cron в панели управления"
echo "3. Проверьте админ-панель: https://ваш-домен.ru/admin"
