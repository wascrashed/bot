<?php

return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),
    // Включить/выключить автоматические викторины (по умолчанию из .env или true)
    'auto_quiz_enabled' => env('TELEGRAM_AUTO_QUIZ_ENABLED', true),
    // ID тестового чата для загрузки изображений и получения file_id (опционально)
    // Можно использовать ваш личный чат с ботом
    'test_chat_id' => env('TELEGRAM_TEST_CHAT_ID', null),
];
