<?php
/**
 * Временный скрипт для настройки через панель управления
 * Загрузите этот файл на сервер и откройте через браузер
 * ИЛИ выполните через CLI: php setup_via_panel.php
 */

// Безопасность: удалите этот файл после использования!

echo "<h1>🚀 Настройка бота</h1>";
echo "<pre>";

$basePath = __DIR__;

// 1. Проверка структуры
echo "1. Проверка структуры...\n";
if (!file_exists($basePath . '/artisan')) {
    die("❌ Файл artisan не найден! Убедитесь, что вы в правильной папке.\n");
}
echo "✅ Структура проекта найдена\n\n";

// 2. Создание .env
echo "2. Создание .env...\n";
if (!file_exists($basePath . '/.env')) {
    if (file_exists($basePath . '/.env.example')) {
        copy($basePath . '/.env.example', $basePath . '/.env');
        echo "✅ .env создан из .env.example\n";
        echo "⚠️  ВАЖНО: Отредактируйте .env файл вручную!\n";
    } else {
        echo "⚠️  .env.example не найден. Создайте .env вручную.\n";
    }
} else {
    echo "✅ .env уже существует\n";
}
echo "\n";

// 3. Права доступа
echo "3. Настройка прав доступа...\n";
$dirs = ['storage', 'bootstrap/cache'];
foreach ($dirs as $dir) {
    $path = $basePath . '/' . $dir;
    if (is_dir($path)) {
        chmod($path, 0775);
        echo "✅ Права установлены для: $dir\n";
    }
}
echo "\n";

// 4. Проверка composer
echo "4. Проверка composer...\n";
if (file_exists($basePath . '/composer.json')) {
    echo "✅ composer.json найден\n";
    echo "⚠️  Выполните вручную: composer install --no-dev --optimize-autoloader\n";
} else {
    echo "❌ composer.json не найден!\n";
}
echo "\n";

// 5. Проверка PHP
echo "5. Информация о PHP:\n";
echo "   Версия: " . PHP_VERSION . "\n";
echo "   Путь к PHP: " . PHP_BINARY . "\n";
echo "\n";

// 6. Команды для выполнения
echo "═══════════════════════════════════════════════════════════\n";
echo "📋 СЛЕДУЮЩИЕ КОМАНДЫ ДЛЯ ВЫПОЛНЕНИЯ:\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "\n";
echo "cd $basePath\n";
echo "composer install --no-dev --optimize-autoloader\n";
echo "php artisan key:generate\n";
echo "php artisan migrate --force\n";
echo "php artisan db:seed --class=Dota2QuestionsSeeder\n";
echo "php artisan db:seed --class=AdminUserSeeder\n";
echo "php artisan config:cache\n";
echo "php artisan route:cache\n";
echo "php artisan telegram:set-webhook https://ваш-домен.ru/webhook/telegram\n";
echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "⚠️  ВАЖНО: Удалите этот файл после использования!\n";
echo "═══════════════════════════════════════════════════════════\n";

echo "</pre>";
?>
