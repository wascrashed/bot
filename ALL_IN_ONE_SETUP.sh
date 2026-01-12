#!/bin/bash
# Полная настройка бота одной командой
# Выполните в любом доступном терминале (SSH, панель управления, и т.д.)

echo "🚀 Начало полной настройки бота..."
echo ""

# Определение пути к проекту
PROJECT_PATH=""
if [ -d ~/public_html/bot ]; then
    PROJECT_PATH=~/public_html/bot
elif [ -d ~/www/bot ]; then
    PROJECT_PATH=~/www/bot
elif [ -d /home/iwascrash2/public_html/bot ]; then
    PROJECT_PATH=/home/iwascrash2/public_html/bot
elif [ -d /home/iwascrash2/www/bot ]; then
    PROJECT_PATH=/home/iwascrash2/www/bot
else
    echo "❌ Папка проекта не найдена!"
    echo "Укажите путь вручную или создайте папку bot в public_html"
    exit 1
fi

echo "📁 Путь к проекту: $PROJECT_PATH"
cd $PROJECT_PATH || exit 1

echo ""
echo "📦 Шаг 1: Установка зависимостей..."
if command -v composer &> /dev/null; then
    composer install --no-dev --optimize-autoloader
else
    echo "⚠️  Composer не найден. Установите composer или выполните команды вручную."
fi

echo ""
echo "📝 Шаг 2: Создание .env..."
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
        echo "✅ .env создан"
        echo "⚠️  ВАЖНО: Отредактируйте .env файл!"
        echo "   Выполните: nano .env"
    else
        echo "⚠️  .env.example не найден. Создайте .env вручную."
    fi
else
    echo "✅ .env уже существует"
fi

echo ""
echo "🔑 Шаг 3: Генерация ключа..."
php artisan key:generate

echo ""
echo "📁 Шаг 4: Права доступа..."
chmod -R 775 storage bootstrap/cache 2>/dev/null
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || chown -R apache:apache storage bootstrap/cache 2>/dev/null || echo "⚠️  Не удалось изменить владельца (может потребоваться sudo)"

echo ""
echo "🗄️  Шаг 5: Миграции..."
php artisan migrate --force

echo ""
echo "📊 Шаг 6: Добавление вопросов..."
php artisan db:seed --class=Dota2QuestionsSeeder

echo ""
echo "👤 Шаг 7: Создание администратора..."
php artisan db:seed --class=AdminUserSeeder

echo ""
echo "💾 Шаг 8: Кеширование..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo ""
echo "═══════════════════════════════════════════════════════════"
echo "✅ Настройка завершена!"
echo "═══════════════════════════════════════════════════════════"
echo ""
echo "📋 Следующие шаги:"
echo "1. Отредактируйте .env: nano .env"
echo "2. Установите webhook:"
echo "   php artisan telegram:set-webhook https://ваш-домен.ru/webhook/telegram"
echo "3. Настройте Cron в панели cp.sweb.ru"
echo "4. Проверьте админ-панель: https://ваш-домен.ru/admin"
echo ""
echo "🔍 Проверка:"
echo "   php artisan telegram:bot-info"
echo "   php artisan quiz:start-random"
echo ""
