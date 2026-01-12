#!/bin/bash
# Команды для выполнения в Termius после загрузки файлов

echo "🚀 Начало настройки бота на сервере..."

# Переход в папку проекта
cd ~/public_html/bot || cd ~/www/bot || {
    echo "❌ Папка проекта не найдена!"
    echo "Проверьте путь к проекту"
    exit 1
}

echo "📦 Установка зависимостей..."
composer install --no-dev --optimize-autoloader

echo "📝 Создание .env файла..."
if [ ! -f .env ]; then
    cp .env.example .env
    echo "✅ .env создан. Отредактируйте его: nano .env"
else
    echo "⚠️  .env уже существует"
fi

echo "🔑 Генерация ключа приложения..."
php artisan key:generate

echo "📁 Настройка прав доступа..."
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || chown -R apache:apache storage bootstrap/cache 2>/dev/null

echo "🗄️  Выполнение миграций..."
php artisan migrate --force

echo "📊 Добавление вопросов..."
php artisan db:seed --class=Dota2QuestionsSeeder

echo "👤 Создание администратора..."
php artisan db:seed --class=AdminUserSeeder

echo "💾 Кеширование..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo ""
echo "✅ Настройка завершена!"
echo ""
echo "📋 Следующие шаги:"
echo "1. Отредактируйте .env: nano .env"
echo "2. Установите webhook: php artisan telegram:set-webhook https://ваш-домен.ru/webhook/telegram"
echo "3. Настройте Cron в панели cp.sweb.ru"
echo "4. Проверьте админ-панель: https://ваш-домен.ru/admin"
echo ""
