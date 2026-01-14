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
        Schema::create('memes', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable(); // Опциональное название мема
            $table->string('media_type')->default('photo'); // 'photo' или 'video'
            $table->string('media_url')->nullable(); // URL или путь к файлу
            $table->string('file_id')->nullable(); // Telegram file_id для оптимизации
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('is_active');
            $table->index('media_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memes');
    }
};
