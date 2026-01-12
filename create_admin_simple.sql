-- Простая SQL команда для создания/обновления администратора
-- Пароль по умолчанию: admin

INSERT INTO `admin_users` (`username`, `email`, `password`, `name`, `is_active`, `created_at`, `updated_at`)
VALUES (
    'admin',
    'admin@example.com',
    '$2y$10$SEPqfm2VxFoJdMBQ7ogaxOFHpRCsJ2gmMekf4NQRIWVhw2srYydzq',
    'Administrator',
    1,
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE 
    `password` = VALUES(`password`),
    `updated_at` = NOW();

-- Логин: admin
-- Пароль: admin
-- ⚠️ ОБЯЗАТЕЛЬНО измените пароль после первого входа!
