<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BotAnalytics;
use App\Models\ChatStatistics;
use App\Models\Question;
use App\Models\UserScore;
use App\Models\ActiveQuiz;
use App\Services\QuizService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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

        // Получить статус автоматических викторин
        $autoQuizEnabled = Cache::get('auto_quiz_enabled', config('telegram.auto_quiz_enabled', true));
        
        // Получить список всех чатов для выбора
        $allChats = ChatStatistics::orderBy('chat_title')->get();
        
        return view('admin.dashboard', compact('stats', 'todayAnalytics', 'recentQuizzes', 'topChats', 'autoQuizEnabled', 'allChats'));
    }

    /**
     * Переключить автоматические викторины
     */
    public function toggleAutoQuiz(Request $request)
    {
        $enabled = $request->input('enabled', false);
        Cache::forever('auto_quiz_enabled', (bool) $enabled);
        
        return response()->json([
            'success' => true,
            'enabled' => (bool) $enabled,
            'message' => $enabled ? 'Автоматические викторины включены' : 'Автоматические викторины выключены'
        ]);
    }

    /**
     * Запустить викторину в выбранных чатах
     */
    public function startQuiz(Request $request, QuizService $quizService)
    {
        $chatIds = $request->input('chat_ids', []);
        $startEverywhere = $request->input('everywhere', false);
        
        $successCount = 0;
        $failedCount = 0;
        $errors = [];
        
        try {
            if ($startEverywhere) {
                // Запустить во всех чатах
                $chats = ChatStatistics::select('chat_id', 'chat_type')->get();
            } else {
                // Запустить только в выбранных чатах
                if (empty($chatIds)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Выберите хотя бы один чат'
                    ], 400);
                }
                
                $chats = ChatStatistics::whereIn('chat_id', $chatIds)
                    ->select('chat_id', 'chat_type')
                    ->get();
            }
            
            foreach ($chats as $chat) {
                try {
                    if ($quizService->startQuiz($chat->chat_id, $chat->chat_type)) {
                        $successCount++;
                    } else {
                        $failedCount++;
                        $errors[] = "Не удалось запустить в чате {$chat->chat_id}";
                    }
                } catch (\Exception $e) {
                    $failedCount++;
                    $errors[] = "Ошибка в чате {$chat->chat_id}: " . $e->getMessage();
                    Log::error('Start quiz error from admin', [
                        'chat_id' => $chat->chat_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => "Викторина запущена в {$successCount} чат(ах)" . ($failedCount > 0 ? ", ошибок: {$failedCount}" : ''),
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'errors' => $errors
            ]);
            
        } catch (\Exception $e) {
            Log::error('Start quiz from admin error', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при запуске викторины: ' . $e->getMessage()
            ], 500);
        }
    }
}
