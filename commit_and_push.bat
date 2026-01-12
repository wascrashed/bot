@echo off
cd /d "c:\Users\Administrator\Documents\GitHub\bot"

echo Adding files...
git add app/Services/TelegramService.php app/Console/Kernel.php

echo Committing changes...
git commit -m "Fix TelegramService makeRequest return type and scheduler cron expressions

- Added explicit type checking in makeRequest to ensure ?array return type
- Fixed scheduler: replaced everySixMinutes() and everyFiveMinutes() with cron() expressions
- Added validation to prevent bool return values"

echo Pushing to origin main...
git push origin main

echo Done!
