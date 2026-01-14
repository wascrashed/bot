<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Meme;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MemesController extends Controller
{
    /**
     * Display a listing of memes
     */
    public function index(Request $request)
    {
        $query = Meme::query();

        if ($request->filled('type')) {
            $query->where('media_type', $request->type);
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->active === '1');
        }

        $memes = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('admin.memes.index', compact('memes'));
    }

    /**
     * Show the form for creating a new meme
     */
    public function create()
    {
        return view('admin.memes.create');
    }

    /**
     * Store a newly created meme
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'media_type' => 'required|in:photo,video',
            'media_file' => 'required|file|mimes:jpeg,png,jpg,gif,webp,mp4,avi,mov|max:51200', // до 50MB
        ]);

        $file = $request->file('media_file');
        $extension = $file->getClientOriginalExtension();
        $filename = time() . '_' . uniqid() . '.' . $extension;
        
        // Определить тип медиа по расширению
        $isVideo = in_array(strtolower($extension), ['mp4', 'avi', 'mov', 'mkv', 'webm']);
        $validated['media_type'] = $isVideo ? Meme::TYPE_VIDEO : Meme::TYPE_PHOTO;
        
        // Убедиться, что директория существует
        Storage::disk('public')->makeDirectory('memes');
        
        // Сохранить файл
        $path = $file->storeAs('memes', $filename, 'public');
        $fullPath = Storage::disk('public')->path('memes/' . $filename);
        
        $validated['media_url'] = 'storage/memes/' . $filename;
        $validated['is_active'] = true;
        
        // Попытаться загрузить в Telegram и получить file_id (оптимизация)
        try {
            $telegramService = new TelegramService();
            $botInfo = $telegramService->getMe();
            
            if ($botInfo) {
                $testChatId = config('telegram.test_chat_id', null);
                
                if ($testChatId) {
                    $result = null;
                    if ($isVideo) {
                        $result = $telegramService->sendVideo($testChatId, $fullPath, 'Upload for file_id');
                    } else {
                        $result = $telegramService->sendPhoto($testChatId, $fullPath, 'Upload for file_id');
                    }
                    
                    if ($result) {
                        $fileId = null;
                        if (isset($result['photo'])) {
                            $photos = $result['photo'];
                            $largestPhoto = end($photos);
                            $fileId = $largestPhoto['file_id'] ?? null;
                        } elseif (isset($result['video'])) {
                            $fileId = $result['video']['file_id'] ?? null;
                        }
                        
                        if ($fileId) {
                            $validated['file_id'] = $fileId;
                            Log::info('Media uploaded to Telegram, file_id obtained', [
                                'file_id' => $fileId,
                                'meme_id' => 'new',
                                'type' => $validated['media_type']
                            ]);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to upload media to Telegram for file_id', [
                'error' => $e->getMessage()
            ]);
        }
        
        Meme::create($validated);

        return redirect()->route('admin.memes.index')
            ->with('success', 'Мем успешно добавлен.');
    }

    /**
     * Display the specified meme
     */
    public function show(Meme $meme)
    {
        return view('admin.memes.show', compact('meme'));
    }

    /**
     * Show the form for editing the specified meme
     */
    public function edit(Meme $meme)
    {
        return view('admin.memes.edit', compact('meme'));
    }

    /**
     * Update the specified meme
     */
    public function update(Request $request, Meme $meme)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'media_file' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,mp4,avi,mov|max:51200',
        ]);

        // Если загружен новый файл
        if ($request->hasFile('media_file')) {
            // Удалить старый файл
            if ($meme->media_url && strpos($meme->media_url, 'storage/memes/') !== false) {
                $oldFilename = basename($meme->media_url);
                Storage::disk('public')->delete('memes/' . $oldFilename);
            }
            
            $file = $request->file('media_file');
            $extension = $file->getClientOriginalExtension();
            $filename = time() . '_' . uniqid() . '.' . $extension;
            
            // Определить тип медиа
            $isVideo = in_array(strtolower($extension), ['mp4', 'avi', 'mov', 'mkv', 'webm']);
            $validated['media_type'] = $isVideo ? Meme::TYPE_VIDEO : Meme::TYPE_PHOTO;
            
            Storage::disk('public')->makeDirectory('memes');
            $path = $file->storeAs('memes', $filename, 'public');
            $fullPath = Storage::disk('public')->path('memes/' . $filename);
            
            $validated['media_url'] = 'storage/memes/' . $filename;
            $validated['file_id'] = null; // Сбросить file_id, будет обновлен
            
            // Загрузить в Telegram для получения file_id
            try {
                $telegramService = new TelegramService();
                $testChatId = config('telegram.test_chat_id', null);
                
                if ($testChatId) {
                    $result = null;
                    if ($isVideo) {
                        $result = $telegramService->sendVideo($testChatId, $fullPath, 'Upload for file_id');
                    } else {
                        $result = $telegramService->sendPhoto($testChatId, $fullPath, 'Upload for file_id');
                    }
                    
                    if ($result) {
                        $fileId = null;
                        if (isset($result['photo'])) {
                            $photos = $result['photo'];
                            $largestPhoto = end($photos);
                            $fileId = $largestPhoto['file_id'] ?? null;
                        } elseif (isset($result['video'])) {
                            $fileId = $result['video']['file_id'] ?? null;
                        }
                        
                        if ($fileId) {
                            $validated['file_id'] = $fileId;
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to upload media to Telegram for file_id', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        $meme->update($validated);

        return redirect()->route('admin.memes.index')
            ->with('success', 'Мем успешно обновлен.');
    }

    /**
     * Remove the specified meme
     */
    public function destroy(Meme $meme)
    {
        // Удалить файл
        if ($meme->media_url && strpos($meme->media_url, 'storage/memes/') !== false) {
            $filename = basename($meme->media_url);
            Storage::disk('public')->delete('memes/' . $filename);
        }
        
        $meme->delete();

        return redirect()->route('admin.memes.index')
            ->with('success', 'Мем успешно удален.');
    }
}
