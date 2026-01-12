

<?php $__env->startSection('title', 'Вопросы'); ?>
<?php $__env->startSection('page-title', 'Управление вопросами'); ?>

<?php $__env->startSection('content'); ?>
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2>Список вопросов</h2>
        <a href="<?php echo e(route('admin.questions.create')); ?>" class="btn btn-primary">+ Добавить вопрос</a>
    </div>

    <form method="GET" action="<?php echo e(route('admin.questions.index')); ?>" style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
        <input type="text" name="search" class="form-control" placeholder="Поиск..." value="<?php echo e(request('search')); ?>" style="flex: 1; min-width: 200px;">
        <select name="category" class="form-control" style="width: 150px;">
            <option value="">Все категории</option>
            <option value="heroes" <?php echo e(request('category') == 'heroes' ? 'selected' : ''); ?>>Герои</option>
            <option value="abilities" <?php echo e(request('category') == 'abilities' ? 'selected' : ''); ?>>Способности</option>
            <option value="items" <?php echo e(request('category') == 'items' ? 'selected' : ''); ?>>Предметы</option>
            <option value="lore" <?php echo e(request('category') == 'lore' ? 'selected' : ''); ?>>Лор</option>
            <option value="esports" <?php echo e(request('category') == 'esports' ? 'selected' : ''); ?>>Киберспорт</option>
            <option value="memes" <?php echo e(request('category') == 'memes' ? 'selected' : ''); ?>>Мемы</option>
        </select>
        <select name="type" class="form-control" style="width: 150px;">
            <option value="">Все типы</option>
            <option value="multiple_choice" <?php echo e(request('type') == 'multiple_choice' ? 'selected' : ''); ?>>Выбор</option>
            <option value="text" <?php echo e(request('type') == 'text' ? 'selected' : ''); ?>>Текст</option>
            <option value="true_false" <?php echo e(request('type') == 'true_false' ? 'selected' : ''); ?>>Верно/Неверно</option>
            <option value="image" <?php echo e(request('type') == 'image' ? 'selected' : ''); ?>>Картинка</option>
        </select>
        <select name="difficulty" class="form-control" style="width: 120px;">
            <option value="">Все уровни</option>
            <option value="easy" <?php echo e(request('difficulty') == 'easy' ? 'selected' : ''); ?>>Легкий</option>
            <option value="medium" <?php echo e(request('difficulty') == 'medium' ? 'selected' : ''); ?>>Средний</option>
            <option value="hard" <?php echo e(request('difficulty') == 'hard' ? 'selected' : ''); ?>>Сложный</option>
        </select>
        <button type="submit" class="btn btn-primary">Фильтровать</button>
        <a href="<?php echo e(route('admin.questions.index')); ?>" class="btn btn-secondary">Сбросить</a>
    </form>

    <?php if($questions->count() > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Вопрос</th>
                    <th>Категория</th>
                    <th>Тип</th>
                    <th>Сложность</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $questions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $question): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($question->id); ?></td>
                    <td><?php echo e(Str::limit($question->question, 60)); ?></td>
                    <td>
                        <?php
                            $categories = ['heroes' => 'Герои', 'abilities' => 'Способности', 'items' => 'Предметы', 'lore' => 'Лор', 'esports' => 'Киберспорт', 'memes' => 'Мемы'];
                            echo $categories[$question->category] ?? $question->category;
                        ?>
                    </td>
                    <td>
                        <?php
                            $types = ['multiple_choice' => 'Выбор', 'text' => 'Текст', 'true_false' => 'В/Н', 'image' => 'Картинка'];
                            echo $types[$question->question_type] ?? $question->question_type;
                        ?>
                    </td>
                    <td>
                        <?php if($question->difficulty == 'easy'): ?>
                            <span class="badge badge-success">Легкий</span>
                        <?php elseif($question->difficulty == 'medium'): ?>
                            <span class="badge badge-warning">Средний</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Сложный</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?php echo e(route('admin.questions.edit', $question)); ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">Редактировать</a>
                        <form action="<?php echo e(route('admin.questions.destroy', $question)); ?>" method="POST" style="display: inline;" onsubmit="return confirm('Удалить этот вопрос?');">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('DELETE'); ?>
                            <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;">Удалить</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>

        <div class="pagination">
            <?php echo e($questions->links()); ?>

        </div>
    <?php else: ?>
        <p>Вопросы не найдены.</p>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\Users\Administrator\Documents\bot\resources\views/admin/questions/index.blade.php ENDPATH**/ ?>