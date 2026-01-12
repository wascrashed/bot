<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatStatistics;
use App\Models\UserScore;
use App\Services\TelegramService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    /**
     * Display a listing of chats
     */
    public function index(Request $request)
    {
        // По умолчанию показываем только чаты, где бот присутствует (is_active = true)
        $showAll = $request->get('show_all', false);
        
        $query = ChatStatistics::query();
        
        if (!$showAll) {
            // Показывать только чаты, где бот активен
            $query->where('is_active', true);
        }
        
        $chats = $query->orderBy('last_quiz_at', 'desc')->paginate(20);
        
        return view('admin.chats.index', compact('chats', 'showAll'));
    }

    /**
     * Show chat details
     */
    public function show($chatId)
    {
        $chat = ChatStatistics::where('chat_id', $chatId)->firstOrFail();
        
        $leaderboard = UserScore::where('chat_id', $chatId)
            ->orderBy('total_points', 'desc')
            ->limit(20)
            ->get();

        return view('admin.chats.show', compact('chat', 'leaderboard'));
    }

    /**
     * Toggle chat active status
     */
    public function toggleActive($chatId)
    {
        $chat = ChatStatistics::where('chat_id', $chatId)->firstOrFail();
        
        // Если активируем чат, проверить через API, действительно ли бот в чате
        if (!$chat->is_active) {
            $telegramService = new \App\Services\TelegramService();
            $isMember = $telegramService->isBotMember($chatId);
            
            if (!$isMember) {
                return back()->with('error', 'Бот не является членом этого чата. Добавьте бота в группу, чтобы активировать чат.');
            }
        }
        
        $chat->is_active = !$chat->is_active;
        $chat->save();

        return back()->with('success', 'Статус чата обновлен.');
    }
    
    /**
     * Проверить статус бота в чате через Telegram API
     */
    public function checkBotStatus($chatId)
    {
        try {
            $chat = ChatStatistics::where('chat_id', $chatId)->firstOrFail();
            $telegramService = new \App\Services\TelegramService();
            
            $isMember = $telegramService->isBotMember($chatId);
            
            // Обновить статус в базе данных
            $chat->is_active = $isMember;
            $chat->save();
            
            if ($isMember) {
                return back()->with('success', 'Бот присутствует в чате. Статус обновлен.');
            } else {
                return back()->with('warning', 'Бот не найден в чате. Чат деактивирован.');
            }
        } catch (\Exception $e) {
            return back()->with('error', 'Ошибка при проверке статуса: ' . $e->getMessage());
        }
    }

    /**
     * Restore chat (create if deleted)
     */
    public function restore(Request $request, $chatId)
    {
        try {
            // Попробовать найти существующий чат
            $chat = ChatStatistics::where('chat_id', $chatId)->first();
            
            if ($chat) {
                // Чат уже существует - просто активировать
                $chat->is_active = true;
                $chat->save();
                return back()->with('success', "Чат уже существует и был активирован.");
            }
            
            // Чат не существует - создать новый
            $chatType = $request->input('chat_type', 'group');
            $chatTitle = $request->input('chat_title', null);
            
            $chat = ChatStatistics::create([
                'chat_id' => $chatId,
                'chat_type' => $chatType,
                'chat_title' => $chatTitle,
                'is_active' => true,
            ]);
            
            return back()->with('success', "Чат успешно восстановлен. Отправьте сообщение в группу, чтобы обновить название чата.");
        } catch (\Exception $e) {
            return back()->with('error', 'Ошибка при восстановлении чата: ' . $e->getMessage());
        }
    }

    /**
     * Delete chat from database (only statistics, keeps history)
     */
    public function destroy($chatId)
    {
        try {
            $chat = ChatStatistics::where('chat_id', $chatId)->firstOrFail();
            $chatTitle = $chat->chat_title ?? "Chat {$chatId}";
            
            // Деактивировать все активные викторины в этом чате
            \App\Models\ActiveQuiz::where('chat_id', $chatId)
                ->where('is_active', true)
                ->update(['is_active' => false]);
            
            // Удалить статистику чата
            $chat->delete();
            
            return redirect()->route('admin.chats.index')
                ->with('success', "Чат \"{$chatTitle}\" успешно удален из базы данных.");
        } catch (\Exception $e) {
            return back()->with('error', 'Ошибка при удалении чата: ' . $e->getMessage());
        }
    }

    /**
     * Полностью очистить все данные чата (статистика, викторины, результаты, очки)
     */
    public function clearAll($chatId)
    {
        try {
            $chat = ChatStatistics::where('chat_id', $chatId)->firstOrFail();
            $chatTitle = $chat->chat_title ?? "Chat {$chatId}";
            
            \Illuminate\Support\Facades\DB::beginTransaction();

            // 1. Получить ID всех викторин этого чата ПЕРЕД удалением
            $quizIds = \App\Models\ActiveQuiz::where('chat_id', $chatId)->pluck('id');
            
            // 2. Удалить результаты викторин (связаны через active_quiz_id)
            if ($quizIds->isNotEmpty()) {
                \App\Models\QuizResult::whereIn('active_quiz_id', $quizIds)->delete();
            }
            
            // 3. Удалить активные викторины
            \App\Models\ActiveQuiz::where('chat_id', $chatId)->delete();
            
            // 4. Удалить очки пользователей
            \App\Models\UserScore::where('chat_id', $chatId)->delete();
            
            // 5. Удалить историю вопросов
            \App\Models\QuestionHistory::where('chat_id', $chatId)->delete();
            
            // 6. Удалить статистику чата
            $chat->delete();

            \Illuminate\Support\Facades\DB::commit();
            
            return redirect()->route('admin.chats.index')
                ->with('success', "Все данные чата \"{$chatTitle}\" полностью удалены. Чат можно зарегистрировать заново.");
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return back()->with('error', 'Ошибка при удалении данных: ' . $e->getMessage());
        }
    }
}
