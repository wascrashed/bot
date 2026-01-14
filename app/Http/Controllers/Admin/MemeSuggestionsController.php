<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Meme;
use App\Models\MemeSuggestion;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MemeSuggestionsController extends Controller
{
    /**
     * Display a listing of meme suggestions
     */
    public function index(Request $request)
    {
        $query = MemeSuggestion::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            // По умолчанию показываем только ожидающие
            $query->where('status', MemeSuggestion::STATUS_PENDING);
        }

        $suggestions = $query->orderBy('created_at', 'desc')->paginate(20);
        $pendingCount = MemeSuggestion::getPendingCount();

        return view('admin.meme-suggestions.index', compact('suggestions', 'pendingCount'));
    }

    /**
     * Show the specified suggestion
     */
    public function show(MemeSuggestion $memeSuggestion)
    {
        // Получить путь к файлу для превью
        $filePath = null;
        if ($memeSuggestion->file_id) {
            try {
                $telegramService = new TelegramService();
                $fileInfo = $telegramService->getFile($memeSuggestion->file_id);
                $filePath = $fileInfo['file_path'] ?? null;
            } catch (\Exception $e) {
                // Игнорируем ошибки
            }
        }
        
        return view('admin.meme-suggestions.show', compact('memeSuggestion', 'filePath'));
    }

    /**
     * Approve a meme suggestion
     */
    public function approve(Request $request, MemeSuggestion $memeSuggestion)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
        ]);

        try {
            $telegramService = new TelegramService();
            
            // Скачать файл из Telegram по file_id
            $fileInfo = $telegramService->getFile($memeSuggestion->file_id);
            
            if (!$fileInfo || !isset($fileInfo['file_path'])) {
                return redirect()->back()
                    ->with('error', 'Не удалось получить файл из Telegram.');
            }
            
            // Скачать файл
            $fileUrl = "https://api.telegram.org/file/bot" . config('telegram.bot_token') . "/" . $fileInfo['file_path'];
            $fileContent = file_get_contents($fileUrl);
            
            if (!$fileContent) {
                return redirect()->back()
                    ->with('error', 'Не удалось скачать файл.');
            }
            
            // Определить расширение
            $extension = pathinfo($fileInfo['file_path'], PATHINFO_EXTENSION);
            if (empty($extension)) {
                $extension = $memeSuggestion->media_type === MemeSuggestion::TYPE_VIDEO ? 'mp4' : 'jpg';
            }
            
            $filename = time() . '_' . uniqid() . '.' . $extension;
            
            // Сохранить файл
            Storage::disk('public')->makeDirectory('memes');
            Storage::disk('public')->put('memes/' . $filename, $fileContent);
            
            // Создать мем
            $meme = Meme::create([
                'title' => $request->input('title'),
                'media_type' => $memeSuggestion->media_type,
                'media_url' => 'storage/memes/' . $filename,
                'file_id' => $memeSuggestion->file_id,
                'is_active' => true,
            ]);
            
            // Обновить предложение
            $memeSuggestion->update([
                'status' => MemeSuggestion::STATUS_APPROVED,
                'reviewed_by' => auth('admin')->id(),
                'reviewed_at' => now(),
            ]);
            
            // Уведомить пользователя
            $this->notifyUserAboutApproval($memeSuggestion);
            
            return redirect()->route('admin.meme-suggestions.index')
                ->with('success', 'Мем одобрен и добавлен в базу.');
        } catch (\Exception $e) {
            Log::error('Failed to approve meme suggestion', [
                'suggestion_id' => $memeSuggestion->id,
                'error' => $e->getMessage(),
            ]);
            
            return redirect()->back()
                ->with('error', 'Ошибка при одобрении мема: ' . $e->getMessage());
        }
    }

    /**
     * Reject a meme suggestion
     */
    public function reject(Request $request, MemeSuggestion $memeSuggestion)
    {
        $request->validate([
            'admin_comment' => 'nullable|string|max:500',
        ]);

        $memeSuggestion->update([
            'status' => MemeSuggestion::STATUS_REJECTED,
            'admin_comment' => $request->input('admin_comment'),
            'reviewed_by' => auth('admin')->id(),
            'reviewed_at' => now(),
        ]);

        // Уведомить пользователя
        $this->notifyUserAboutRejection($memeSuggestion);

        return redirect()->route('admin.meme-suggestions.index')
            ->with('success', 'Мем отклонен.');
    }

    /**
     * Уведомить пользователя об одобрении
     */
    private function notifyUserAboutApproval(MemeSuggestion $suggestion): void
    {
        try {
            $telegramService = new TelegramService();
            $message = "✅ <b>Ваш мем одобрен!</b>\n\nМем добавлен в базу и теперь доступен через команду /mem.";
            
            $telegramService->sendMessage($suggestion->user_id, $message, ['parse_mode' => 'HTML']);
        } catch (\Exception $e) {
            Log::warning('Failed to notify user about approval', [
                'suggestion_id' => $suggestion->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Уведомить пользователя об отклонении
     */
    private function notifyUserAboutRejection(MemeSuggestion $suggestion): void
    {
        try {
            $telegramService = new TelegramService();
            $message = "❌ <b>Ваш мем отклонен</b>\n\n";
            
            if ($suggestion->admin_comment) {
                $message .= "Комментарий: {$suggestion->admin_comment}";
            } else {
                $message .= "К сожалению, ваш мем не подошел для добавления в базу.";
            }
            
            $telegramService->sendMessage($suggestion->user_id, $message, ['parse_mode' => 'HTML']);
        } catch (\Exception $e) {
            Log::warning('Failed to notify user about rejection', [
                'suggestion_id' => $suggestion->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
