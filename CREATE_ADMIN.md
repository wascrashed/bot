# Создание администратора через SQL

## Быстрый способ:

Выполните SQL команду из файла `create_admin_simple.sql` в вашей БД.

## Детальная инструкция:

### 1. Через phpMyAdmin:
1. Откройте phpMyAdmin
2. Выберите вашу базу данных
3. Перейдите на вкладку "SQL"
4. Скопируйте и выполните команду из `create_admin_simple.sql`

### 2. Через MySQL CLI:
```bash
mysql -u ваш_пользователь -p ваша_база < create_admin_simple.sql
```

### 3. Через Laravel Tinker:
```bash
php artisan tinker
```
Затем выполните:
```php
\App\Models\AdminUser::create([
    'username' => 'admin',
    'email' => 'admin@example.com',
    'password' => \Illuminate\Support\Facades\Hash::make('admin'),
    'name' => 'Administrator',
    'is_active' => true,
]);
```

### 4. Через Artisan команду:
```bash
php artisan db:seed --class=AdminUserSeeder
```

## Данные для входа (по умолчанию):

- **Username:** `admin`
- **Password:** `admin`
- **Email:** `admin@example.com`

⚠️ **ВАЖНО:** Обязательно измените пароль после первого входа!

## Создание админа с другим паролем:

### 1. Сгенерируйте хеш пароля:
```bash
php -r "echo password_hash('ваш_пароль', PASSWORD_BCRYPT);"
```

### 2. Используйте полученный хеш в SQL:
```sql
INSERT INTO `admin_users` (`username`, `email`, `password`, `name`, `is_active`, `created_at`, `updated_at`)
VALUES (
    'admin',
    'admin@example.com',
    'ВАШ_ХЕШ_ПАРОЛЯ',
    'Administrator',
    1,
    NOW(),
    NOW()
);
```

## Обновление пароля существующего админа:

```sql
UPDATE `admin_users` 
SET `password` = '$2y$10$ВАШ_НОВЫЙ_ХЕШ_ПАРОЛЯ',
    `updated_at` = NOW()
WHERE `username` = 'admin';
```

## Проверка существования админа:

```sql
SELECT id, username, email, name, is_active, created_at 
FROM `admin_users` 
WHERE `username` = 'admin';
```
