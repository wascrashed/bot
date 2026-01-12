<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatStatistics;
use App\Models\UserScore;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    /**
     * Display a listing of chats
     */
    public function index()
    {
        $chats = ChatStatistics::orderBy('last_quiz_at', 'desc')->paginate(20);
        return view('admin.chats.index', compact('chats'));
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
        $chat->is_active = !$chat->is_active;
        $chat->save();

        return back()->with('success', 'Статус чата обновлен.');
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
     * Delete chat from database
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
}
