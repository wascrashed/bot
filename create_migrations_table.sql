-- Создание таблицы migrations вручную
-- Выполните эту команду в MySQL перед запуском миграций

USE dota2_quiz_bot;

CREATE TABLE IF NOT EXISTS migrations (
    id int unsigned auto_increment primary key,
    migration varchar(255) not null,
    batch int not null
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
