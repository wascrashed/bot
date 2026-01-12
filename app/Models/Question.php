<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'question',
        'question_type',
        'correct_answer', // Индекс правильного ответа (0, 1, 2...)
        'correct_answer_text', // Текст правильного ответа
        'wrong_answers',
        'category',
        'difficulty',
        'image_url',
        'image_file_id',
    ];

    protected $casts = [
        'wrong_answers' => 'array',
    ];

    // Типы вопросов
    const TYPE_MULTIPLE_CHOICE = 'multiple_choice'; // Выбор из вариантов (кнопки)
    const TYPE_TEXT = 'text'; // Текстовый ответ
    const TYPE_TRUE_FALSE = 'true_false'; // Верно/Неверно
    const TYPE_IMAGE = 'image'; // С изображением

    // Категории
    const CATEGORY_HEROES = 'heroes';
    const CATEGORY_ABILITIES = 'abilities';
    const CATEGORY_ITEMS = 'items';
    const CATEGORY_LORE = 'lore';
    const CATEGORY_ESPORTS = 'esports';
    const CATEGORY_MEMES = 'memes';

    // Сложность и очки
    const DIFFICULTY_EASY = 'easy'; // +1 очко
    const DIFFICULTY_MEDIUM = 'medium'; // +3 очка
    const DIFFICULTY_HARD = 'hard'; // +5 очков

    /**
     * Получить количество очков за правильный ответ в зависимости от сложности
     */
    public function getPointsForAnswer(): int
    {
        return match($this->difficulty) {
            self::DIFFICULTY_EASY => 1,
            self::DIFFICULTY_MEDIUM => 3,
            self::DIFFICULTY_HARD => 5,
            default => 3,
        };
    }

    /**
     * Получить текст правильного ответа
     */
    public function getCorrectAnswerText(): string
    {
        // Если есть correct_answer_text - используем его
        if (!empty($this->correct_answer_text)) {
            return $this->correct_answer_text;
        }
        
        // Fallback для старых данных - correct_answer может быть текстом
        if (!is_numeric($this->correct_answer)) {
            return $this->correct_answer;
        }
        
        // Если correct_answer - это индекс, получаем из массива ответов
        $allAnswers = $this->getAllAnswers();
        $index = (int)$this->correct_answer;
        return $allAnswers[$index] ?? '';
    }
    
    /**
     * Получить все варианты ответов (правильный + неправильные) БЕЗ перемешивания
     */
    public function getAllAnswers(): array
    {
        if ($this->question_type === self::TYPE_TRUE_FALSE) {
            return ['Верно', 'Неверно'];
        }
        
        $correctText = $this->getCorrectAnswerText();
        
        if (empty($this->wrong_answers)) {
            return [$correctText];
        }
        
        return array_merge([$correctText], $this->wrong_answers);
    }

    /**
     * Получить все варианты ответов (правильный + неправильные) в перемешанном порядке
     */
    public function getShuffledAnswers(): array
    {
        $answers = $this->getAllAnswers();
        shuffle($answers);
        return $answers;
    }

    /**
     * Проверить правильность ответа
     */
    public function checkAnswer(string $answer): bool
    {
        $answer = mb_strtolower(trim($answer));
        $correctText = $this->getCorrectAnswerText();
        $correct = mb_strtolower(trim($correctText));
        
        // Для вопросов типа Верно/Неверно
        if ($this->question_type === self::TYPE_TRUE_FALSE) {
            $answerNormalized = in_array($answer, ['верно', 'да', 'true', '1', '✓', '✅']) ? 'верно' : 'неверно';
            $correctNormalized = in_array($correct, ['верно', 'да', 'true', '1', '✓', '✅']) ? 'верно' : 'неверно';
            return $answerNormalized === $correctNormalized;
        }
        
        // Для текстовых вопросов - частичное совпадение (без учета регистра)
        if ($this->question_type === self::TYPE_TEXT || $this->question_type === self::TYPE_IMAGE) {
            // Проверяем оба направления поиска подстроки
            $correctLower = mb_strtolower($correct);
            $answerLower = mb_strtolower($answer);
            
            // Точное совпадение (после нормализации)
            if ($answerLower === $correctLower) {
                return true;
            }
            
            // Частичное совпадение (одна строка содержит другую)
            return mb_strpos($correctLower, $answerLower) !== false || mb_strpos($answerLower, $correctLower) !== false;
        }
        
        // Точное совпадение для остальных типов
        return $answer === $correct;
    }
}
