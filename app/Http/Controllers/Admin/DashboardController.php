<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BotAnalytics;
use App\Models\ChatStatistics;
use App\Models\Question;
use App\Models\UserScore;
use App\Models\ActiveQuiz;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Show dashboard
     */
    public function index()
    {
        $stats = [
            'total_questions' => Question::count(),
            'active_chats' => ChatStatistics::where('is_active', true)->count(),
            'total_participants' => UserScore::distinct('user_id')->count('user_id'),
            'active_quizzes' => ActiveQuiz::where('is_active', true)->count(),
            'total_quizzes_today' => ActiveQuiz::whereDate('created_at', today())->count(),
        ];

        $todayAnalytics = BotAnalytics::getToday();
        
        $recentQuizzes = ActiveQuiz::with('question')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $topChats = ChatStatistics::where('is_active', true)
            ->orderBy('total_quizzes', 'desc')
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact('stats', 'todayAnalytics', 'recentQuizzes', 'topChats'));
    }
}
