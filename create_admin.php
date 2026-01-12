<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AdminUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

try {
    // Проверяем, существует ли таблица
    DB::select('SELECT 1 FROM admin_users LIMIT 1');
    
    // Проверяем, существует ли администратор
    $admin = AdminUser::where('username', 'admin')->first();
    
    if ($admin) {
        echo "Administrator already exists!\n";
        echo "Username: admin\n";
        echo "Email: {$admin->email}\n";
    } else {
        // Создаем администратора
        $admin = AdminUser::create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('admin'),
            'name' => 'Administrator',
            'is_active' => true,
        ]);
        
        echo "✅ Administrator created successfully!\n\n";
        echo "Login credentials:\n";
        echo "Username: admin\n";
        echo "Password: admin\n";
        echo "Email: {$admin->email}\n\n";
        echo "⚠️  Please change the password after first login!\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\n";
    echo "The admin_users table does not exist. Please run migrations first:\n";
    echo "1. Create migrations table manually (see create_migrations_table.sql)\n";
    echo "2. Run: php artisan migrate\n";
    echo "3. Then run this script again\n";
}
