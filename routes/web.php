<?php

use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\Admin\{
    AuthController,
    DashboardController,
    QuestionController,
    StatisticsController,
    ChatController,
    LogController
};
use Illuminate\Support\Facades\Route;

// Отключить CSRF для вебхука Telegram
Route::post('/webhook/telegram', [TelegramWebhookController::class, 'handle'])
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

// Админ-панель
Route::prefix('admin')->name('admin.')->group(function () {
    // Авторизация
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Защищенные маршруты
    Route::middleware(['admin.auth'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('/dashboard/toggle-auto-quiz', [DashboardController::class, 'toggleAutoQuiz'])->name('dashboard.toggle-auto-quiz');
        Route::post('/dashboard/start-quiz', [DashboardController::class, 'startQuiz'])->name('dashboard.start-quiz');

        // Вопросы
        Route::resource('questions', QuestionController::class);

        // Статистика
        Route::get('/statistics', [StatisticsController::class, 'index'])->name('statistics.index');

        // Чаты
        Route::get('/chats', [ChatController::class, 'index'])->name('chats.index');
        Route::get('/chats/{chatId}', [ChatController::class, 'show'])->name('chats.show');
        Route::post('/chats/{chatId}/toggle-active', [ChatController::class, 'toggleActive'])->name('chats.toggle-active');
        Route::delete('/chats/{chatId}', [ChatController::class, 'destroy'])->name('chats.destroy');

        // Логи
        Route::get('/logs', [LogController::class, 'index'])->name('logs.index');
        Route::post('/logs/clear', [LogController::class, 'clear'])->name('logs.clear');
    });
});
