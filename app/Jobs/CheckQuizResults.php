<?php

namespace App\Jobs;

use App\Models\ActiveQuiz;
use App\Services\QuizService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckQuizResults implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $activeQuizId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $activeQuizId)
    {
        $this->activeQuizId = $activeQuizId;
    }

    /**
     * Execute the job.
     */
    public function handle(QuizService $quizService): void
    {
        $activeQuiz = ActiveQuiz::find($this->activeQuizId);

        if (!$activeQuiz || !$activeQuiz->is_active) {
            return;
        }

        // Завершить викторину и показать результаты
        $quizService->finishQuiz($this->activeQuizId);
    }
}
