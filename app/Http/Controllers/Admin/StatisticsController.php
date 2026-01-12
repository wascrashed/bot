<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BotAnalytics;
use App\Models\ChatStatistics;
use App\Models\UserScore;
use App\Models\QuizResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    /**
     * Show statistics
     */
    public function index()
    {
        $todayAnalytics = BotAnalytics::getToday();
        
        $chatStats = ChatStatistics::where('is_active', true)
            ->orderBy('total_quizzes', 'desc')
            ->get();

        $topUsers = UserScore::select('user_id', 'username', 'first_name', DB::raw('SUM(total_points) as total_points'), DB::raw('SUM(correct_answers) as correct_answers'))
            ->groupBy('user_id', 'username', 'first_name')
            ->orderByDesc('total_points')
            ->limit(20)
            ->get();

        $categoryStats = DB::table('questions')
            ->select('category', DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->get();

        $typeStats = DB::table('questions')
            ->select('question_type', DB::raw('COUNT(*) as count'))
            ->groupBy('question_type')
            ->get();

        $difficultyStats = DB::table('questions')
            ->select('difficulty', DB::raw('COUNT(*) as count'))
            ->groupBy('difficulty')
            ->get();

        return view('admin.statistics.index', compact(
            'todayAnalytics',
            'chatStats',
            'topUsers',
            'categoryStats',
            'typeStats',
            'difficultyStats'
        ));
    }
}
