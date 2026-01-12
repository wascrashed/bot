

<?php $__env->startSection('title', 'Статистика'); ?>
<?php $__env->startSection('page-title', 'Статистика'); ?>

<?php $__env->startSection('content'); ?>
<div class="card">
    <div class="card-header">
        <h2>📊 Статистика за сегодня</h2>
    </div>
    <?php if($todayAnalytics): ?>
        <table class="table">
            <tr>
                <td><strong>Активных чатов:</strong></td>
                <td><?php echo e(number_format($todayAnalytics->active_chats)); ?></td>
            </tr>
            <tr>
                <td><strong>Всего участников:</strong></td>
                <td><?php echo e(number_format($todayAnalytics->total_participants)); ?></td>
            </tr>
            <tr>
                <td><strong>Всего викторин:</strong></td>
                <td><?php echo e(number_format($todayAnalytics->total_quizzes)); ?></td>
            </tr>
            <tr>
                <td><strong>Всего ответов:</strong></td>
                <td><?php echo e(number_format($todayAnalytics->total_answers)); ?></td>
            </tr>
            <tr>
                <td><strong>Правильных ответов:</strong></td>
                <td><?php echo e(number_format($todayAnalytics->correct_answers)); ?></td>
            </tr>
            <tr>
                <td><strong>Процент правильных:</strong></td>
                <td><?php echo e($todayAnalytics->total_answers > 0 ? number_format(($todayAnalytics->correct_answers / $todayAnalytics->total_answers) * 100, 2) : 0); ?>%</td>
            </tr>
            <tr>
                <td><strong>Ошибок:</strong></td>
                <td><?php echo e(number_format($todayAnalytics->errors_count)); ?></td>
            </tr>
            <tr>
                <td><strong>Среднее время ответа:</strong></td>
                <td><?php echo e(number_format($todayAnalytics->avg_response_time_ms)); ?> мс</td>
            </tr>
        </table>
    <?php else: ?>
        <p>Статистика за сегодня отсутствует.</p>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <h2>💬 Статистика по чатам</h2>
    </div>
    <?php if($chatStats->count() > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>ID чата</th>
                    <th>Название</th>
                    <th>Всего викторин</th>
                    <th>Участников</th>
                    <th>Ответов</th>
                    <th>Правильных</th>
                    <th>Последняя викторина</th>
                    <th>Статус</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $chatStats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $chat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($chat->chat_id); ?></td>
                    <td><?php echo e($chat->chat_title ?? 'Без названия'); ?></td>
                    <td><?php echo e(number_format($chat->total_quizzes)); ?></td>
                    <td><?php echo e(number_format($chat->total_participants)); ?></td>
                    <td><?php echo e(number_format($chat->total_answers)); ?></td>
                    <td><?php echo e(number_format($chat->correct_answers)); ?></td>
                    <td><?php echo e($chat->last_quiz_at ? $chat->last_quiz_at->format('d.m.Y H:i') : 'Никогда'); ?></td>
                    <td>
                        <?php if($chat->is_active): ?>
                            <span class="badge badge-success">Активен</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Неактивен</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Нет активных чатов.</p>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <h2>🏆 Топ пользователей (глобальный)</h2>
    </div>
    <?php if($topUsers->count() > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Место</th>
                    <th>Пользователь</th>
                    <th>Всего очков</th>
                    <th>Правильных ответов</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $topUsers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($index + 1); ?></td>
                    <td><?php echo e($user->first_name ?? $user->username ?? "User {$user->user_id}"); ?></td>
                    <td><strong><?php echo e(number_format($user->total_points)); ?></strong></td>
                    <td><?php echo e(number_format($user->correct_answers)); ?></td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Нет данных о пользователях.</p>
    <?php endif; ?>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
    <div class="card">
        <div class="card-header">
            <h2>📊 По категориям</h2>
        </div>
        <?php if($categoryStats->count() > 0): ?>
            <table class="table">
                <?php $__currentLoopData = $categoryStats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td>
                        <?php
                            $categories = ['heroes' => 'Герои', 'abilities' => 'Способности', 'items' => 'Предметы', 'lore' => 'Лор', 'esports' => 'Киберспорт', 'memes' => 'Мемы'];
                            echo $categories[$stat->category] ?? $stat->category;
                        ?>
                    </td>
                    <td><strong><?php echo e(number_format($stat->count)); ?></strong></td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>📋 По типам</h2>
        </div>
        <?php if($typeStats->count() > 0): ?>
            <table class="table">
                <?php $__currentLoopData = $typeStats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td>
                        <?php
                            $types = ['multiple_choice' => 'Выбор', 'text' => 'Текст', 'true_false' => 'В/Н', 'image' => 'Картинка'];
                            echo $types[$stat->question_type] ?? $stat->question_type;
                        ?>
                    </td>
                    <td><strong><?php echo e(number_format($stat->count)); ?></strong></td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>⚡ По сложности</h2>
        </div>
        <?php if($difficultyStats->count() > 0): ?>
            <table class="table">
                <?php $__currentLoopData = $difficultyStats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td>
                        <?php
                            $difficulties = ['easy' => 'Легкий', 'medium' => 'Средний', 'hard' => 'Сложный'];
                            echo $difficulties[$stat->difficulty] ?? $stat->difficulty;
                        ?>
                    </td>
                    <td><strong><?php echo e(number_format($stat->count)); ?></strong></td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\Users\Administrator\Documents\bot\resources\views/admin/statistics/index.blade.php ENDPATH**/ ?>