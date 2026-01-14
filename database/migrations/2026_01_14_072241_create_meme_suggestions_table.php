<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('meme_suggestions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id'); // ID пользователя Telegram
            $table->string('username')->nullable(); // Username пользователя
            $table->string('first_name')->nullable(); // Имя пользователя
            $table->string('media_type')->default('photo'); // 'photo' или 'video'
            $table->string('media_url')->nullable(); // URL или путь к файлу
            $table->string('file_id')->nullable(); // Telegram file_id
            $table->string('status')->default('pending'); // 'pending', 'approved', 'rejected'
            $table->text('admin_comment')->nullable(); // Комментарий админа при отклонении
            $table->foreignId('reviewed_by')->nullable()->constrained('admin_users')->onDelete('set null'); // Кто рассмотрел
            $table->timestamp('reviewed_at')->nullable(); // Когда рассмотрено
            $table->timestamps();
            
            $table->index('status');
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meme_suggestions');
    }
};
