@extends('admin.layout')

@section('title', 'Панель управления')
@section('page-title', 'Панель управления')

@section('content')
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2>⚙️ Управление автоматическими викторинами</h2>
        <label class="switch" style="position: relative; display: inline-block; width: 60px; height: 34px;">
            <input type="checkbox" id="autoQuizToggle" {{ $autoQuizEnabled ? 'checked' : '' }} onchange="toggleAutoQuiz(this.checked)">
            <span class="slider" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px;"></span>
        </label>
    </div>
    <div class="card-body">
        <p id="autoQuizStatus" style="margin: 0;">
            Статус: <strong>{{ $autoQuizEnabled ? 'Включены' : 'Выключены' }}</strong>
            <br>
            <small>Автоматические викторины запускаются каждые 6 минут во всех активных чатах</small>
        </p>
    </div>
</div>

<div class="card" style="margin-bottom: 20px;">
    <div class="card-header">
        <h2>🚀 Запустить викторину сейчас</h2>
    </div>
    <div class="card-body">
        <button type="button" class="btn btn-success" onclick="showStartQuizModal()" style="font-size: 16px; padding: 10px 20px;">
            ▶️ Запустить викторину
        </button>
        <p style="margin-top: 10px; margin-bottom: 0;">
            <small>Запустить викторину в выбранных чатах или во всех чатах сразу</small>
        </p>
    </div>
</div>

<!-- Модальное окно для запуска викторины -->
<div id="startQuizModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
    <div style="background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 600px; border-radius: 8px; max-height: 80vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>Запустить викторину</h2>
            <span style="color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer;" onclick="closeStartQuizModal()">&times;</span>
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: flex; align-items: center; cursor: pointer;">
                <input type="checkbox" id="startEverywhere" onchange="toggleChatSelection()" style="margin-right: 10px; width: 20px; height: 20px;">
                <strong>Запустить во всех чатах</strong>
            </label>
        </div>
        
        <div id="chatsSelection" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
            <strong style="display: block; margin-bottom: 10px;">Выберите чаты:</strong>
            @foreach($allChats as $chat)
            <label style="display: block; padding: 8px; cursor: pointer; border-radius: 4px; margin-bottom: 5px;" 
                   onmouseover="this.style.backgroundColor='#f0f0f0'" 
                   onmouseout="this.style.backgroundColor='transparent'">
                <input type="checkbox" name="chat_ids[]" value="{{ $chat->chat_id }}" class="chat-checkbox" style="margin-right: 10px;">
                <strong>{{ $chat->chat_title ?? 'Без названия' }}</strong>
                <small style="color: #666;"> (ID: {{ $chat->chat_id }}, {{ $chat->chat_type }})</small>
            </label>
            @endforeach
        </div>
        
        <div style="margin-top: 20px; display: flex; gap: 10px;">
            <button type="button" class="btn btn-success" onclick="startQuiz()" id="startQuizBtn" style="flex: 1;">
                ▶️ Запустить
            </button>
            <button type="button" class="btn btn-secondary" onclick="closeStartQuizModal()" style="flex: 1;">
                Отмена
            </button>
        </div>
        
        <div id="quizResult" style="margin-top: 15px; display: none;"></div>
    </div>
</div>

<style>
.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider:before {
    position: absolute;
    content: "";
    height: 26px;
    width: 26px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: #4CAF50;
}

input:checked + .slider:before {
    transform: translateX(26px);
}
</style>

<script>
function toggleAutoQuiz(enabled) {
    fetch('{{ route("admin.dashboard.toggle-auto-quiz") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ enabled: enabled })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('autoQuizStatus').innerHTML = 
                'Статус: <strong>' + (data.enabled ? 'Включены' : 'Выключены') + '</strong><br>' +
                '<small>Автоматические викторины запускаются каждые 6 минут во всех активных чатах</small>';
        } else {
            alert('Ошибка при изменении настроек');
            document.getElementById('autoQuizToggle').checked = !enabled;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Ошибка при изменении настроек');
        document.getElementById('autoQuizToggle').checked = !enabled;
    });
}

function showStartQuizModal() {
    document.getElementById('startQuizModal').style.display = 'block';
}

function closeStartQuizModal() {
    document.getElementById('startQuizModal').style.display = 'none';
    document.getElementById('quizResult').style.display = 'none';
    document.getElementById('quizResult').innerHTML = '';
    document.getElementById('startEverywhere').checked = false;
    document.querySelectorAll('.chat-checkbox').forEach(cb => cb.checked = false);
}

function toggleChatSelection() {
    const everywhere = document.getElementById('startEverywhere').checked;
    const chatsSelection = document.getElementById('chatsSelection');
    const checkboxes = document.querySelectorAll('.chat-checkbox');
    
    if (everywhere) {
        chatsSelection.style.opacity = '0.5';
        chatsSelection.style.pointerEvents = 'none';
        checkboxes.forEach(cb => cb.checked = false);
    } else {
        chatsSelection.style.opacity = '1';
        chatsSelection.style.pointerEvents = 'auto';
    }
}

function startQuiz() {
    const everywhere = document.getElementById('startEverywhere').checked;
    const chatIds = [];
    
    if (!everywhere) {
        document.querySelectorAll('.chat-checkbox:checked').forEach(cb => {
            chatIds.push(parseInt(cb.value));
        });
        
        if (chatIds.length === 0) {
            alert('Выберите хотя бы один чат или включите "Запустить во всех чатах"');
            return;
        }
    }
    
    const btn = document.getElementById('startQuizBtn');
    const resultDiv = document.getElementById('quizResult');
    btn.disabled = true;
    btn.textContent = '⏳ Запускается...';
    resultDiv.style.display = 'none';
    
    fetch('{{ route("admin.dashboard.start-quiz") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            everywhere: everywhere,
            chat_ids: chatIds
        })
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = '▶️ Запустить';
        resultDiv.style.display = 'block';
        
        if (data.success) {
            let errorHtml = '';
            if (data.errors && data.errors.length > 0) {
                errorHtml = '<div style="margin-top: 15px; padding: 10px; background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">' +
                    '<strong style="color: #856404;">⚠️ Детали ошибок:</strong><ul style="margin: 10px 0; padding-left: 20px; color: #856404;">';
                
                if (data.errors_detailed && data.errors_detailed.length > 0) {
                    // Использовать детальную информацию
                    data.errors_detailed.forEach(function(error) {
                        errorHtml += '<li style="margin-bottom: 8px;"><strong>' + 
                            (error.chat_title || 'Chat ' + error.chat_id) + '</strong> (ID: ' + error.chat_id + ')<br>' +
                            '<small style="color: #856404;">Причина: ' + (error.reason || error.message || 'Неизвестная ошибка') + '</small></li>';
                    });
                } else {
                    // Fallback на простые сообщения
                    data.errors.forEach(function(error) {
                        errorHtml += '<li style="margin-bottom: 5px;">' + error + '</li>';
                    });
                }
                
                errorHtml += '</ul></div>';
            }
            
            resultDiv.innerHTML = '<div style="padding: 10px; background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724;">' +
                '<strong>✅ Успешно!</strong><br>' + data.message +
                errorHtml +
                '</div>';
            
            // Закрыть модальное окно через 3 секунды
            setTimeout(() => {
                closeStartQuizModal();
                // Обновить страницу через 2 секунды для обновления статистики
                setTimeout(() => location.reload(), 2000);
            }, 3000);
        } else {
            resultDiv.innerHTML = '<div style="padding: 10px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;">' +
                '<strong>❌ Ошибка!</strong><br>' + data.message +
                '</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        btn.disabled = false;
        btn.textContent = '▶️ Запустить';
        resultDiv.style.display = 'block';
        resultDiv.innerHTML = '<div style="padding: 10px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;">' +
            '<strong>❌ Ошибка!</strong><br>Не удалось запустить викторину. Попробуйте еще раз.' +
            '</div>';
    });
}

// Закрыть модальное окно при клике вне его
window.onclick = function(event) {
    const modal = document.getElementById('startQuizModal');
    if (event.target == modal) {
        closeStartQuizModal();
    }
}
</script>

<div class="stats-grid">
    <div class="stat-card">
        <h3>Всего вопросов</h3>
        <div class="value">{{ number_format($stats['total_questions']) }}</div>
    </div>
    <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
        <h3>Активных чатов</h3>
        <div class="value">{{ number_format($stats['active_chats']) }}</div>
    </div>
    <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
        <h3>Участников</h3>
        <div class="value">{{ number_format($stats['total_participants']) }}</div>
    </div>
    <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
        <h3>Викторин сегодня</h3>
        <div class="value">{{ number_format($stats['total_quizzes_today']) }}</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>📊 Статистика за сегодня</h2>
    </div>
    @if($todayAnalytics)
        <table class="table">
            <tr>
                <td><strong>Всего викторин:</strong></td>
                <td>{{ number_format($todayAnalytics->total_quizzes) }}</td>
            </tr>
            <tr>
                <td><strong>Всего ответов:</strong></td>
                <td>{{ number_format($todayAnalytics->total_answers) }}</td>
            </tr>
            <tr>
                <td><strong>Правильных ответов:</strong></td>
                <td>{{ number_format($todayAnalytics->correct_answers) }}</td>
            </tr>
            <tr>
                <td><strong>Ошибок:</strong></td>
                <td>{{ number_format($todayAnalytics->errors_count) }}</td>
            </tr>
            <tr>
                <td><strong>Среднее время ответа:</strong></td>
                <td>{{ number_format($todayAnalytics->avg_response_time_ms) }} мс</td>
            </tr>
        </table>
    @else
        <p>Статистика за сегодня пока отсутствует.</p>
    @endif
</div>

<div class="card">
    <div class="card-header">
        <h2>🏆 Топ чатов по активности</h2>
    </div>
    @if($topChats->count() > 0)
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
                @foreach($topChats as $chat)
                <tr>
                    <td>{{ $chat->chat_id }}</td>
                    <td>{{ $chat->chat_title ?? 'Без названия' }}</td>
                    <td>{{ number_format($chat->total_quizzes) }}</td>
                    <td>{{ number_format($chat->total_participants) }}</td>
                    <td>{{ number_format($chat->total_answers) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p>Нет активных чатов.</p>
    @endif
</div>

<div class="card">
    <div class="card-header">
        <h2>🕐 Последние викторины</h2>
    </div>
    @if($recentQuizzes->count() > 0)
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
                @foreach($recentQuizzes as $quiz)
                <tr>
                    <td>{{ $quiz->chat_id }}</td>
                    <td>{{ Str::limit($quiz->question->question ?? 'N/A', 50) }}</td>
                    <td>{{ $quiz->started_at->format('d.m.Y H:i:s') }}</td>
                    <td>
                        @if($quiz->is_active)
                            <span class="badge badge-success">Активна</span>
                        @else
                            <span class="badge badge-secondary">Завершена</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p>Нет викторин.</p>
    @endif
</div>
@endsection
