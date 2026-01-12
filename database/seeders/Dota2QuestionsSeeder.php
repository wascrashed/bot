<?php

namespace Database\Seeders;

use App\Models\Question;
use Illuminate\Database\Seeder;

class Dota2QuestionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $questions = [
            // Multiple Choice Questions (Heroes)
            [
                'question' => 'Какой герой имеет способность "Black Hole"?',
                'question_type' => Question::TYPE_MULTIPLE_CHOICE,
                'correct_answer' => 'Enigma',
                'wrong_answers' => ['Pudge', 'Invoker', 'Rubick'],
                'category' => Question::CATEGORY_HEROES,
                'difficulty' => Question::DIFFICULTY_EASY,
            ],
            [
                'question' => 'Сколько максимум героев может быть в одной команде в стандартном матче?',
                'correct_answer' => '5',
                'wrong_answers' => ['6', '4', '7'],
                'difficulty' => 'easy',
            ],
            [
                'question' => 'Какой предмет дает возможность телепортироваться к союзным юнитам?',
                'correct_answer' => 'Boots of Travel',
                'wrong_answers' => ['Blink Dagger', 'Shadow Blade', 'Force Staff'],
                'difficulty' => 'medium',
            ],
            [
                'question' => 'Какой герой может создавать иллюзии себя с помощью ультимативной способности?',
                'correct_answer' => 'Phantom Lancer',
                'wrong_answers' => ['Terrorblade', 'Chaos Knight', 'Spectre'],
                'difficulty' => 'medium',
            ],
            [
                'question' => 'Какое максимальное количество слотов для предметов у героя?',
                'correct_answer' => '6',
                'wrong_answers' => ['5', '7', '8'],
                'difficulty' => 'easy',
            ],
            [
                'question' => 'Какой герой использует способность "Global Silence"?',
                'correct_answer' => 'Silencer',
                'wrong_answers' => ['Death Prophet', 'Drow Ranger', 'Crystal Maiden'],
                'difficulty' => 'easy',
            ],
            [
                'question' => 'Сколько стоит полное восстановление в фонтане?',
                'correct_answer' => 'Бесплатно',
                'wrong_answers' => ['100 золота', '200 золота', '500 золота'],
                'difficulty' => 'easy',
            ],
            [
                'question' => 'Какой герой может перевоплощаться в любого вражеского героя?',
                'correct_answer' => 'Rubick',
                'wrong_answers' => ['Morphling', 'Shadow Demon', 'Invoker'],
                'difficulty' => 'hard',
            ],
            [
                'question' => 'Какое время респавна Рошана после его убийства?',
                'correct_answer' => '8-11 минут',
                'wrong_answers' => ['5-7 минут', '12-15 минут', '3-5 минут'],
                'difficulty' => 'medium',
            ],
            [
                'question' => 'Какой герой может убить себя с помощью ультимативной способности?',
                'correct_answer' => 'Techies',
                'wrong_answers' => ['Pudge', 'Abaddon', 'Undying'],
                'difficulty' => 'medium',
            ],
            [
                'question' => 'Сколько уровней нужно достичь герою, чтобы выучить все способности?',
                'correct_answer' => '18',
                'wrong_answers' => ['16', '20', '25'],
                'difficulty' => 'medium',
            ],
            [
                'question' => 'Какой предмет позволяет использовать способность даже при недостатке маны?',
                'correct_answer' => 'Aghanim\'s Scepter',
                'wrong_answers' => ['Arcane Boots', 'Magic Wand', 'None'],
                'difficulty' => 'hard',
            ],
            [
                'question' => 'Какой герой имеет пассивную способность "Bash of the Deep"?',
                'correct_answer' => 'Slardar',
                'wrong_answers' => ['Tidehunter', 'Kunkka', 'Naga Siren'],
                'difficulty' => 'medium',
            ],
            [
                'question' => 'Сколько золота получает команда за убийство Рошана?',
                'correct_answer' => '200-400 на героя',
                'wrong_answers' => ['100-200 на героя', '500-1000 на героя', 'Нет золота'],
                'difficulty' => 'hard',
            ],
            [
                'question' => 'Какой герой может красть интеллект у врагов?',
                'correct_answer' => 'Silencer',
                'wrong_answers' => ['Pudge', 'Necrophos', 'Undying'],
                'difficulty' => 'medium',
            ],
            [
                'question' => 'Сколько времени длится стандартная игра Dota 2 до автоматической сдачи?',
                'correct_answer' => 'Нет автоматической сдачи',
                'wrong_answers' => ['30 минут', '45 минут', '60 минут'],
                'difficulty' => 'easy',
            ],
            [
                'question' => 'Какой герой может призывать огненную стражу?',
                'correct_answer' => 'Warlock',
                'wrong_answers' => ['Invoker', 'Enigma', 'Chen'],
                'difficulty' => 'medium',
            ],
            [
                'question' => 'Какое максимальное количество зарядов у Magic Wand?',
                'correct_answer' => '20',
                'wrong_answers' => ['15', '17', '25'],
                'difficulty' => 'easy',
            ],
            [
                'question' => 'Какой герой имеет способность "Chronosphere"?',
                'correct_answer' => 'Faceless Void',
                'wrong_answers' => ['Enigma', 'Dark Seer', 'Puck'],
                'difficulty' => 'easy',
            ],
            [
                'question' => 'Сколько стоит купить Backpack (дополнительные слоты)?',
                'correct_answer' => 'Бесплатно',
                'wrong_answers' => ['500 золота', '1000 золота', '2000 золота'],
                'difficulty' => 'easy',
            ],
            [
                'question' => 'Какой герой может превращать врагов в лягушек?',
                'correct_answer' => 'Shadow Shaman',
                'wrong_answers' => ['Lion', 'Witch Doctor', 'Dazzle'],
                'difficulty' => 'medium',
            ],
            [
                'question' => 'Сколько времени длится эффект Aegis of the Immortal?',
                'correct_answer' => '5 минут',
                'wrong_answers' => ['3 минуты', '7 минут', '10 минут'],
                'difficulty' => 'medium',
            ],
            [
                'question' => 'Какой герой может контролировать до 5 юнитов одновременно?',
                'correct_answer' => 'Chen',
                'wrong_answers' => ['Enchantress', 'Beastmaster', 'Lycan'],
                'difficulty' => 'hard',
            ],
            [
                'question' => 'Сколько раз можно использовать Aegis of the Immortal?',
                'correct_answer' => 'Один раз',
                'wrong_answers' => ['Два раза', 'Три раза', 'Неограниченно'],
                'difficulty' => 'easy',
            ],
            [
                'question' => 'Какой герой имеет способность "Requiem of Souls"?',
                'correct_answer' => 'Shadow Fiend',
                'wrong_answers' => ['Death Prophet', 'Pugna', 'Necrophos'],
                'difficulty' => 'medium',
            ],
        ];

        foreach ($questions as $questionData) {
            // Установить значения по умолчанию
            $questionData['question_type'] = $questionData['question_type'] ?? Question::TYPE_MULTIPLE_CHOICE;
            $questionData['category'] = $questionData['category'] ?? Question::CATEGORY_HEROES;
            $questionData['difficulty'] = $questionData['difficulty'] ?? Question::DIFFICULTY_MEDIUM;
            $questionData['wrong_answers'] = $questionData['wrong_answers'] ?? [];
            
            Question::create($questionData);
        }

        $this->command->info('Seeded ' . count($questions) . ' Dota 2 questions');
        $this->command->info('Note: To add 1000+ questions, run the extended seeder: php artisan db:seed --class=ExtendedDota2QuestionsSeeder');
    }
}
