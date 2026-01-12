

<?php $__env->startSection('title', 'Панель управления'); ?>
<?php $__env->startSection('page-title', 'Панель управления'); ?>

<?php $__env->startSection('content'); ?>
<div class="stats-grid">
    <div class="stat-card">
        <h3>Всего вопросов</h3>
        <div class="value"><?php echo e(number_format($stats['total_questions'])); ?></div>
    </div>
    <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
        <h3>Активных чатов</h3>
        <div class="value"><?php echo e(number_format($stats['active_chats'])); ?></div>
    </div>
    <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
        <h3>Участников</h3>
        <div class="value"><?php echo e(number_format($stats['total_participants'])); ?></div>
    </div>
    <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
        <h3>Викторин сегодня</h3>
        <div class="value"><?php echo e(number_format($stats['total_quizzes_today'])); ?></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>📊 Статистика за сегодня</h2>
    </div>
    <?php if($todayAnalytics): ?>
        <table class="table">
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
                <td><strong>Ошибок:</strong></td>
                <td><?php echo e(number_format($todayAnalytics->errors_count)); ?></td>
            </tr>
            <tr>
                <td><strong>Среднее время ответа:</strong></td>
                <td><?php echo e(number_format($todayAnalytics->avg_response_time_ms)); ?> мс</td>
            </tr>
        </table>
    <?php else: ?>
        <p>Статистика за сегодня пока отсутствует.</p>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <h2>🏆 Топ чатов по активности</h2>
    </div>
    <?php if($topChats->count() > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>ID чата</th>
                    <th>Название</th>
                    <th>Всего викторин</th>
                    <th>Участников</th>
                    <th>Ответов</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $topChats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $chat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($chat->chat_id); ?></td>
                    <td><?php echo e($chat->chat_title ?? 'Без названия'); ?></td>
                    <td><?php echo e(number_format($chat->total_quizzes)); ?></td>
                    <td><?php echo e(number_format($chat->total_participants)); ?></td>
                    <td><?php echo e(number_format($chat->total_answers)); ?></td>
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
        <h2>🕐 Последние викторины</h2>
    </div>
    <?php if($recentQuizzes->count() > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>ID чата</th>
                    <th>Вопрос</th>
                    <th>Время начала</th>
                    <th>Статус</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $recentQuizzes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $quiz): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($quiz->chat_id); ?></td>
                    <td><?php echo e(Str::limit($quiz->question->question ?? 'N/A', 50)); ?></td>
                    <td><?php echo e($quiz->started_at->format('d.m.Y H:i:s')); ?></td>
                    <td>
                        <?php if($quiz->is_active): ?>
                            <span class="badge badge-success">Активна</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Завершена</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Нет викторин.</p>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\Users\Administrator\Documents\bot\resources\views/admin/dashboard.blade.php ENDPATH**/ ?>