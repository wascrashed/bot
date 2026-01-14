<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserProfile;
use App\Models\UserScore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UsersController extends Controller
{
    /**
     * Показать список пользователей
     */
    public function index(Request $request)
    {
        $query = UserProfile::query();

        // Фильтр по рангу
        if ($request->filled('rank_tier')) {
            $query->where('rank_tier', $request->rank_tier);
        }

        // Поиск по нику или user_id
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('game_nickname', 'like', "%{$search}%")
                  ->orWhere('user_id', 'like', "%{$search}%");
            });
        }

        // Сортировка
        $sortBy = $request->get('sort_by', 'total_points');
        $sortOrder = $request->get('sort_order', 'desc');
        
        $query->orderBy($sortBy, $sortOrder);

        $users = $query->paginate(50);

        // Получаем статистику по чатам для каждого пользователя
        foreach ($users as $user) {
            $user->chats_count = UserScore::where('user_id', $user->user_id)->count();
            $user->total_correct = UserScore::where('user_id', $user->user_id)->sum('correct_answers');
            $user->total_answers = UserScore::where('user_id', $user->user_id)->sum('total_answers');
        }

        // Получаем топ Титанов для отображения позиций
        $titanLeaderboard = UserProfile::where('rank_tier', UserProfile::RANK_TITAN)
            ->where('rank_points', '>=', UserProfile::TITAN_MIN_FOR_NUMBERS)
            ->orderBy('rank_points', 'desc')
            ->pluck('user_id')
            ->toArray();

        // Получаем топ 10 Титанов для отображения в отдельном блоке
        $topTitans = UserProfile::where('rank_tier', UserProfile::RANK_TITAN)
            ->where('rank_points', '>=', UserProfile::TITAN_MIN_FOR_NUMBERS)
            ->orderBy('rank_points', 'desc')
            ->take(10)
            ->get();

        return view('admin.users.index', compact('users', 'titanLeaderboard', 'topTitans'));
    }

    /**
     * Показать детальную информацию о пользователе
     */
    public function show(UserProfile $userProfile)
    {
        // Получить статистику по всем чатам
        $scores = UserScore::where('user_id', $userProfile->user_id)
            ->orderBy('total_points', 'desc')
            ->get();

        // Общая статистика
        $totalStats = UserScore::where('user_id', $userProfile->user_id)
            ->selectRaw('
                SUM(total_points) as total_points,
                SUM(correct_answers) as correct_answers,
                SUM(total_answers) as total_answers,
                SUM(first_place_count) as first_place_count,
                COUNT(*) as chats_count
            ')
            ->first();

        // Позиция в топе Титанов (если применимо)
        $titanPosition = null;
        if ($userProfile->rank_tier === UserProfile::RANK_TITAN && $userProfile->rank_points >= UserProfile::TITAN_MIN_FOR_NUMBERS) {
            $titanPosition = $userProfile->getTitanLeaderboardPosition();
        }

        return view('admin.users.show', compact('userProfile', 'scores', 'totalStats', 'titanPosition'));
    }
}
