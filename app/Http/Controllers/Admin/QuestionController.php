<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\Request;

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
            'correct_answer' => 'required|string',
            'wrong_answers' => 'nullable|array',
            'wrong_answers.*' => 'string',
            'category' => 'required|in:heroes,abilities,items,lore,esports,memes',
            'difficulty' => 'required|in:easy,medium,hard',
            'image_url' => 'nullable|url',
            'image_file_id' => 'nullable|string',
        ]);

        $validated['wrong_answers'] = $validated['wrong_answers'] ?? [];

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
            'correct_answer' => 'required|string',
            'wrong_answers' => 'nullable|array',
            'wrong_answers.*' => 'string',
            'category' => 'required|in:heroes,abilities,items,lore,esports,memes',
            'difficulty' => 'required|in:easy,medium,hard',
            'image_url' => 'nullable|url',
            'image_file_id' => 'nullable|string',
        ]);

        $validated['wrong_answers'] = $validated['wrong_answers'] ?? [];

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
