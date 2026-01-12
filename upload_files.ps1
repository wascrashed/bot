# PowerShell скрипт для загрузки файлов через SCP
# Требует OpenSSH (встроен в Windows 10/11)

$server = "77.222.40.251"
$user = "iwascrash2"
$remotePath = "/home/iwascrash2/public_html/bot"
$localPath = "C:\Users\Administrator\Documents\bot"

Write-Host "📤 Загрузка файлов на сервер..." -ForegroundColor Green
Write-Host ""

# Список папок и файлов для загрузки (исключая ненужные)
$itemsToUpload = @(
    "app",
    "bootstrap",
    "config",
    "database",
    "public",
    "resources",
    "routes",
    "storage",
    "artisan",
    "composer.json",
    "composer.lock"
)

Write-Host "Загружаемые элементы:" -ForegroundColor Yellow
foreach ($item in $itemsToUpload) {
    Write-Host "  - $item" -ForegroundColor Gray
}

Write-Host ""
Write-Host "⚠️  Исключены: vendor, .env, node_modules, .git" -ForegroundColor Yellow
Write-Host ""

# Загрузка через SCP
foreach ($item in $itemsToUpload) {
    $localItem = Join-Path $localPath $item
    if (Test-Path $localItem) {
        Write-Host "Загрузка: $item..." -ForegroundColor Cyan
        scp -r -o StrictHostKeyChecking=no "$localItem" "${user}@${server}:${remotePath}/"
        if ($LASTEXITCODE -eq 0) {
            Write-Host "✅ $item загружен" -ForegroundColor Green
        } else {
            Write-Host "❌ Ошибка загрузки $item" -ForegroundColor Red
        }
    }
}

Write-Host ""
Write-Host "✅ Загрузка завершена!" -ForegroundColor Green
Write-Host ""
Write-Host "Следующий шаг: подключитесь через Termius и выполните команды из TERMIUS_COMMANDS.sh" -ForegroundColor Yellow
