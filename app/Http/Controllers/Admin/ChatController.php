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
}
