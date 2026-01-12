-- SQL команда для создания администратора
-- Использование: выполните этот SQL в вашей БД (phpMyAdmin, MySQL CLI и т.д.)

-- ВАРИАНТ 1: С паролем 'admin' (по умолчанию)
-- ВАЖНО: Если админ уже существует, используйте ВАРИАНТ 3 (UPDATE) или удалите существующего
INSERT INTO `admin_users` (`username`, `email`, `password`, `name`, `is_active`, `created_at`, `updated_at`)
VALUES (
    'admin',
    'admin@example.com',
    '$2y$10$SEPqfm2VxFoJdMBQ7ogaxOFHpRCsJ2gmMekf4NQRIWVhw2srYydzq', -- пароль: admin
    'Administrator',
    1,
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE 
    `password` = VALUES(`password`),
    `email` = VALUES(`email`),
    `name` = VALUES(`name`),
    `is_active` = VALUES(`is_active`),
    `updated_at` = NOW();

-- ВАРИАНТ 2: С вашим паролем (замените 'your_password' на нужный пароль)
-- Сначала сгенерируйте хеш пароля через PHP:
-- php -r "echo password_hash('your_password', PASSWORD_BCRYPT);"
-- Затем замените хеш ниже:

-- INSERT INTO `admin_users` (`username`, `email`, `password`, `name`, `is_active`, `created_at`, `updated_at`)
-- VALUES (
--     'admin',
--     'admin@example.com',
--     'ВАШ_ХЕШ_ПАРОЛЯ_ЗДЕСЬ', -- замените на хеш из команды выше
--     'Administrator',
--     1,
--     NOW(),
--     NOW()
-- );

-- ВАРИАНТ 3: Если админ уже существует, обновить пароль
-- UPDATE `admin_users` 
-- SET `password` = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' -- пароль: admin
-- WHERE `username` = 'admin';

-- ПРИМЕЧАНИЕ: 
-- - По умолчанию логин: admin, пароль: admin
-- - ОБЯЗАТЕЛЬНО измените пароль после первого входа!
-- - Для генерации нового хеша пароля используйте: php -r "echo password_hash('ваш_пароль', PASSWORD_BCRYPT);"
