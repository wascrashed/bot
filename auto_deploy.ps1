# Автоматический деплой бота на сервер
# PowerShell скрипт для Windows

$server = "77.222.40.251"
$user = "iwascrash2"
$password = "!X4x2Bik7B2epz1a"
$remotePath = "/home/iwascrash2/public_html/bot"
$localPath = "C:\Users\Administrator\Documents\bot"

Write-Host "🚀 Начало автоматического деплоя..." -ForegroundColor Green
Write-Host ""

# Проверка доступности сервера
Write-Host "📡 Проверка доступности сервера..." -ForegroundColor Yellow
$ping = Test-Connection -ComputerName $server -Count 1 -Quiet
if (-not $ping) {
    Write-Host "❌ Сервер недоступен!" -ForegroundColor Red
    exit 1
}
Write-Host "✅ Сервер доступен" -ForegroundColor Green
Write-Host ""

# Создание архива для загрузки
Write-Host "📦 Создание архива проекта..." -ForegroundColor Yellow
$excludeItems = @("vendor", ".env", "node_modules", ".git", "storage\logs\*", "bootstrap\cache\*")
$archiveName = "bot_deploy_$(Get-Date -Format 'yyyyMMdd_HHmmss').zip"

# Используем 7-Zip или встроенный Compress-Archive
if (Get-Command Compress-Archive -ErrorAction SilentlyContinue) {
    # Создаем временную папку без исключенных файлов
    $tempPath = "$env:TEMP\bot_deploy_temp"
    if (Test-Path $tempPath) {
        Remove-Item $tempPath -Recurse -Force
    }
    New-Item -ItemType Directory -Path $tempPath | Out-Null
    
    # Копируем файлы, исключая ненужные
    Get-ChildItem -Path $localPath -Recurse | Where-Object {
        $exclude = $false
        foreach ($item in $excludeItems) {
            if ($_.FullName -like "*\$item*") {
                $exclude = $true
                break
            }
        }
        return -not $exclude
    } | Copy-Item -Destination {
        $_.FullName.Replace($localPath, $tempPath)
    } -Force
    
    Compress-Archive -Path "$tempPath\*" -DestinationPath $archiveName -Force
    Remove-Item $tempPath -Recurse -Force
    Write-Host "✅ Архив создан: $archiveName" -ForegroundColor Green
} else {
    Write-Host "⚠️  Compress-Archive недоступен. Используйте WinSCP или FileZilla для загрузки файлов." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "📤 Следующие шаги:" -ForegroundColor Cyan
Write-Host "1. Загрузите файлы на сервер через FTP/SFTP:" -ForegroundColor White
Write-Host "   Хост: $server" -ForegroundColor Gray
Write-Host "   Логин: $user" -ForegroundColor Gray
Write-Host "   Пароль: $password" -ForegroundColor Gray
Write-Host "   Папка: public_html/bot" -ForegroundColor Gray
Write-Host ""
Write-Host "2. Подключитесь по SSH и выполните команды из deploy_commands.sh" -ForegroundColor White
Write-Host ""
Write-Host "Или используйте WinSCP для автоматической загрузки!" -ForegroundColor Yellow
