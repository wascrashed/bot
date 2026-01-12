<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

try {
    // Проверяем, существует ли таблица
    $tableExists = DB::select("SHOW TABLES LIKE 'admin_users'");
    
    if (empty($tableExists)) {
        echo "❌ Table 'admin_users' does not exist!\n";
        echo "Please run migrations first:\n";
        echo "1. Create migrations table (see create_migrations_table.sql)\n";
        echo "2. Run: php artisan migrate\n";
        exit(1);
    }
    
    // Проверяем, существует ли администратор
    $admin = DB::table('admin_users')->where('username', 'admin')->first();
    
    if ($admin) {
        echo "✅ Administrator already exists!\n";
        echo "Username: admin\n";
        echo "Email: {$admin->email}\n";
    } else {
        // Создаем администратора напрямую через DB
        DB::table('admin_users')->insert([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('admin'),
            'name' => 'Administrator',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        echo "✅ Administrator created successfully!\n\n";
        echo "Login credentials:\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "Username: admin\n";
        echo "Password: admin\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        echo "⚠️  Please change the password after first login!\n";
        echo "\n";
        echo "Admin panel: http://localhost:8000/admin\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
