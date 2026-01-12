<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QuestionController extends Controller
{
    /**
     * Display a listing of questions
     */
    public function index(Request $request)
    {
        $query = Question::query();

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('type')) {
            $query->where('question_type', $request->type);
        }

        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }

        if ($request->filled('search')) {
            $query->where('question', 'like', '%' . $request->search . '%');
        }

        $questions = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('admin.questions.index', compact('questions'));
    }

    /**
     * Show the form for creating a new question
     */
    public function create()
    {
        return view('admin.questions.create');
    }

    /**
     * Store a newly created question
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'question' => 'required|string',
            'question_type' => 'required|in:multiple_choice,text,true_false,image',
            'correct_answer' => 'required|string', // Это будет текст, сохраним в correct_answer_text
            'wrong_answers' => 'nullable|array',
            'wrong_answers.*' => 'string',
            'category' => 'required|in:heroes,abilities,items,lore,esports,memes',
            'difficulty' => 'required|in:easy,medium,hard',
            'image_url' => 'nullable|string', // Может быть URL или относительный путь
            'image_file_id' => 'nullable|string',
            'image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240', // до 10MB
        ]);

        $validated['wrong_answers'] = $validated['wrong_answers'] ?? [];
        
        // Сохранить текст правильного ответа в correct_answer_text
        // correct_answer будет индексом (0 для multiple_choice, 0 или 1 для true_false)
        $correctAnswerText = $validated['correct_answer'];
        unset($validated['correct_answer']);
        
        // Определить индекс правильного ответа
        if ($validated['question_type'] === 'true_false') {
            // Для Верно/Неверно: Верно = 0, Неверно = 1
            $correctAnswerIndex = (in_array(mb_strtolower(trim($correctAnswerText)), ['верно', 'да', 'true', '1', '✓', '✅'])) ? 0 : 1;
        } else {
            // Для остальных типов правильный ответ всегда первый в массиве [correct, wrong1, wrong2, ...]
            $correctAnswerIndex = 0;
        }
        
        $validated['correct_answer'] = (string)$correctAnswerIndex;
        $validated['correct_answer_text'] = $correctAnswerText;

        // Обработка загрузки файла изображения
        if ($request->hasFile('image_file')) {
            $image = $request->file('image_file');
            $filename = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $path = $image->storeAs('public/questions', $filename);
            $fullPath = storage_path('app/' . $path);
            
            // Попытаться загрузить в Telegram и получить file_id (оптимизация)
            // Используем тестовый чат или первый доступный чат для загрузки
            try {
                $telegramService = new TelegramService();
                $botInfo = $telegramService->getMe();
                
                if ($botInfo) {
                    // Загрузить изображение в Telegram через sendPhoto в тестовый чат
                    // Telegram вернет file_id, который можно использовать для всех чатов
                    $testChatId = config('telegram.test_chat_id', null);
                    
                    if ($testChatId) {
                        $result = $telegramService->sendPhoto($testChatId, $fullPath, 'Upload for file_id');
                        // sendPhoto возвращает массив message, в котором есть photo
                        if ($result && isset($result['photo']) && is_array($result['photo'])) {
                            // Получить самый большой размер фото (обычно последний в массиве)
                            $photos = $result['photo'];
                            $largestPhoto = end($photos);
                            $fileId = $largestPhoto['file_id'] ?? null;
                            
                            if ($fileId) {
                                $validated['image_file_id'] = $fileId;
                                Log::info('Image uploaded to Telegram, file_id obtained', [
                                    'file_id' => $fileId,
                                    'question_id' => 'new'
                                ]);
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to upload image to Telegram for file_id', [
                    'error' => $e->getMessage()
                ]);
                // Продолжаем работу, используя локальный файл
            }
            
            // Сохранить URL для доступа к файлу (относительный путь для storage)
            $validated['image_url'] = 'storage/questions/' . $filename;
        }

        Question::create($validated);

        return redirect()->route('admin.questions.index')
            ->with('success', 'Вопрос успешно создан.');
    }

    /**
     * Show the form for editing a question
     */
    public function edit(Question $question)
    {
        return view('admin.questions.edit', compact('question'));
    }

    /**
     * Update the specified question
     */
    public function update(Request $request, Question $question)
    {
        $validated = $request->validate([
            'question' => 'required|string',
            'question_type' => 'required|in:multiple_choice,text,true_false,image',
            'correct_answer' => 'required|string', // Это будет текст, сохраним в correct_answer_text
            'wrong_answers' => 'nullable|array',
            'wrong_answers.*' => 'string',
            'category' => 'required|in:heroes,abilities,items,lore,esports,memes',
            'difficulty' => 'required|in:easy,medium,hard',
            'image_url' => 'nullable|string', // Может быть URL или относительный путь
            'image_file_id' => 'nullable|string',
            'image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240', // до 10MB
            'remove_image' => 'nullable|boolean',
        ]);

        $validated['wrong_answers'] = $validated['wrong_answers'] ?? [];
        
        // Сохранить текст правильного ответа в correct_answer_text
        // correct_answer будет индексом (0 для multiple_choice, 0 или 1 для true_false)
        $correctAnswerText = $validated['correct_answer'];
        unset($validated['correct_answer']);
        
        // Определить индекс правильного ответа
        if ($validated['question_type'] === 'true_false') {
            // Для Верно/Неверно: Верно = 0, Неверно = 1
            $correctAnswerIndex = (in_array(mb_strtolower(trim($correctAnswerText)), ['верно', 'да', 'true', '1', '✓', '✅'])) ? 0 : 1;
        } else {
            // Для остальных типов правильный ответ всегда первый в массиве [correct, wrong1, wrong2, ...]
            $correctAnswerIndex = 0;
        }
        
        $validated['correct_answer'] = (string)$correctAnswerIndex;
        $validated['correct_answer_text'] = $correctAnswerText;

        // Удаление изображения
        if ($request->has('remove_image') && $request->remove_image) {
            $validated['image_url'] = null;
            $validated['image_file_id'] = null;
        }

        // Обработка загрузки нового файла изображения
        if ($request->hasFile('image_file')) {
            // Удалить старое изображение, если есть
            if ($question->image_url) {
                // Проверить, локальный ли это файл
                if (strpos($question->image_url, 'storage/questions/') !== false) {
                    $oldFilename = basename($question->image_url);
                    \Storage::delete('public/questions/' . $oldFilename);
                }
            }
            
            $image = $request->file('image_file');
            $filename = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $path = $image->storeAs('public/questions', $filename);
            $fullPath = storage_path('app/' . $path);
            
            // Попытаться загрузить в Telegram и получить file_id (оптимизация)
            try {
                $telegramService = new TelegramService();
                $botInfo = $telegramService->getMe();
                
                if ($botInfo) {
                    $testChatId = config('telegram.test_chat_id', null);
                    
                    if ($testChatId) {
                        $result = $telegramService->sendPhoto($testChatId, $fullPath, 'Upload for file_id');
                        // sendPhoto возвращает массив message, в котором есть photo
                        if ($result && isset($result['photo']) && is_array($result['photo'])) {
                            $photos = $result['photo'];
                            $largestPhoto = end($photos);
                            $fileId = $largestPhoto['file_id'] ?? null;
                            
                            if ($fileId) {
                                $validated['image_file_id'] = $fileId;
                                Log::info('Image uploaded to Telegram, file_id obtained', [
                                    'file_id' => $fileId,
                                    'question_id' => $question->id
                                ]);
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to upload image to Telegram for file_id', [
                    'error' => $e->getMessage()
                ]);
            }
            
            // Сохранить путь к файлу (относительный путь для storage)
            $validated['image_url'] = 'storage/questions/' . $filename;
        }

        $question->update($validated);

        return redirect()->route('admin.questions.index')
            ->with('success', 'Вопрос успешно обновлен.');
    }

    /**
     * Remove the specified question
     */
    public function destroy(Question $question)
    {
        $question->delete();

        return redirect()->route('admin.questions.index')
            ->with('success', 'Вопрос успешно удален.');
    }
}
