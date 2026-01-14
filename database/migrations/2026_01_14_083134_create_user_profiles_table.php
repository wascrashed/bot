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
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->unique(); // Telegram user ID
            $table->string('game_nickname')->nullable(); // Ник в игре
            $table->string('dotabuff_url')->nullable(); // Ссылка на Dotabuff
            $table->string('faceit_username')->nullable(); // Faceit username для CS2
            $table->integer('total_points')->default(0); // Общие очки по всем чатам
            $table->integer('rank_points')->default(0); // Очки для ранга (может отличаться от total_points)
            $table->string('rank_tier')->default('herald'); // Ранг (herald, guardian, crusader, archon, legend, ancient, divine, immortal)
            $table->integer('rank_stars')->default(0); // Звезды ранга (0-5)
            $table->boolean('show_rank_in_name')->default(false); // Показывать рейтинг рядом с именем
            $table->json('dotabuff_data')->nullable(); // Кэш данных с Dotabuff (рейтинг, значок)
            $table->json('faceit_data')->nullable(); // Кэш данных с Faceit (уровень)
            $table->timestamp('dotabuff_last_sync')->nullable(); // Последняя синхронизация с Dotabuff
            $table->timestamp('faceit_last_sync')->nullable(); // Последняя синхронизация с Faceit
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('rank_tier');
            $table->index('total_points');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
