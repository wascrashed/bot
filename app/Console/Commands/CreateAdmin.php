<?php

namespace App\Console\Commands;

use App\Models\AdminUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create {username=admin} {password=admin}';
    protected $description = 'Создать администратора (по умолчанию: admin/admin)';

    public function handle(): int
    {
        $username = $this->argument('username');
        $password = $this->argument('password');
        
        try {
            // Проверяем, существует ли таблица
            if (!DB::getSchemaBuilder()->hasTable('admin_users')) {
                $this->error('Таблица admin_users не существует. Выполните миграции:');
                $this->line('php artisan migrate');
                return Command::FAILURE;
            }
            
            // Проверяем, существует ли администратор
            $admin = AdminUser::where('username', $username)->first();
            
            if ($admin) {
                // Обновляем пароль
                $admin->password = Hash::make($password);
                $admin->is_active = true;
                $admin->save();
                
                $this->info("✅ Администратор '{$username}' обновлен!");
                $this->line("Логин: {$username}");
                $this->line("Пароль: {$password}");
                $this->warn('⚠️  Обязательно измените пароль после первого входа!');
            } else {
                // Создаем администратора
                $admin = AdminUser::create([
                    'username' => $username,
                    'email' => $username . '@example.com',
                    'password' => Hash::make($password),
                    'name' => 'Administrator',
                    'is_active' => true,
                ]);
                
                $this->info("✅ Администратор '{$username}' создан!");
                $this->line("Логин: {$username}");
                $this->line("Пароль: {$password}");
                $this->warn('⚠️  Обязательно измените пароль после первого входа!');
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Ошибка: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
