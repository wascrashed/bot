

<?php $__env->startSection('title', 'Чаты'); ?>
<?php $__env->startSection('page-title', 'Управление чатами'); ?>

<?php $__env->startSection('content'); ?>
<div class="card">
    <div class="card-header">
        <h2>Список чатов</h2>
    </div>

    <?php if($chats->count() > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>ID чата</th>
                    <th>Название</th>
                    <th>Тип</th>
                    <th>Всего викторин</th>
                    <th>Участников</th>
                    <th>Ответов</th>
                    <th>Правильных</th>
                    <th>Последняя викторина</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $chats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $chat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($chat->chat_id); ?></td>
                    <td><?php echo e($chat->chat_title ?? 'Без названия'); ?></td>
                    <td><?php echo e($chat->chat_type); ?></td>
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
                    <td>
                        <a href="<?php echo e(route('admin.chats.show', $chat->chat_id)); ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">Просмотр</a>
                        <form action="<?php echo e(route('admin.chats.toggle-active', $chat->chat_id)); ?>" method="POST" style="display: inline;">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="btn btn-<?php echo e($chat->is_active ? 'danger' : 'success'); ?>" style="padding: 5px 10px; font-size: 12px;">
                                <?php echo e($chat->is_active ? 'Деактивировать' : 'Активировать'); ?>

                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>

        <div class="pagination">
            <?php echo e($chats->links()); ?>

        </div>
    <?php else: ?>
        <p>Чаты не найдены.</p>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\Users\Administrator\Documents\bot\resources\views/admin/chats/index.blade.php ENDPATH**/ ?>