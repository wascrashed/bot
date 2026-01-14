<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Админ-панель') - Dota 2 Quiz Bot</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f5f5; }
        .sidebar { position: fixed; left: 0; top: 0; width: 250px; height: 100vh; background: #2c3e50; color: white; padding: 20px 0; overflow-y: auto; }
        .sidebar-header { padding: 20px; border-bottom: 1px solid #34495e; margin-bottom: 20px; }
        .sidebar-header h2 { font-size: 20px; }
        .sidebar-menu { list-style: none; }
        .sidebar-menu li { margin: 0; }
        .sidebar-menu a { display: block; padding: 12px 20px; color: #ecf0f1; text-decoration: none; transition: background 0.3s; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: #34495e; }
        .main-content { margin-left: 250px; padding: 30px; min-height: 100vh; }
        .header { background: white; padding: 20px 30px; margin: -30px -30px 30px -30px; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 24px; color: #2c3e50; }
        .user-menu { display: flex; align-items: center; gap: 15px; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 14px; }
        .btn-primary { background: #3498db; color: white; }
        .btn-primary:hover { background: #2980b9; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-danger:hover { background: #c0392b; }
        .btn-success { background: #27ae60; color: white; }
        .btn-success:hover { background: #229954; }
        .btn-secondary { background: #95a5a6; color: white; }
        .btn-secondary:hover { background: #7f8c8d; }
        .card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .card-header { border-bottom: 1px solid #e0e0e0; padding-bottom: 15px; margin-bottom: 20px; }
        .card-header h2 { font-size: 20px; color: #2c3e50; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #e0e0e0; }
        .table th { background: #f8f9fa; font-weight: 600; color: #2c3e50; }
        .table tr:hover { background: #f8f9fa; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: 500; color: #2c3e50; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        .form-control:focus { outline: none; border-color: #3498db; }
        textarea.form-control { min-height: 100px; resize: vertical; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; }
        .stat-card h3 { font-size: 14px; opacity: 0.9; margin-bottom: 10px; }
        .stat-card .value { font-size: 32px; font-weight: bold; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
        .badge-success { background: #27ae60; color: white; }
        .badge-danger { background: #e74c3c; color: white; }
        .badge-warning { background: #f39c12; color: white; }
        .badge-info { background: #3498db; color: white; }
        .badge-secondary { background: #95a5a6; color: white; }
        .pagination { display: flex; gap: 5px; margin-top: 20px; }
        .pagination a, .pagination span { padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #2c3e50; }
        .pagination .active { background: #3498db; color: white; border-color: #3498db; }
    </style>
    @stack('styles')
</head>
<body>
    @auth('admin')
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>🤖 Quiz Bot Admin</h2>
        </div>
        <ul class="sidebar-menu">
            <li><a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">📊 Панель управления</a></li>
            <li><a href="{{ route('admin.questions.index') }}" class="{{ request()->routeIs('admin.questions.*') ? 'active' : '' }}">❓ Вопросы</a></li>
            <li><a href="{{ route('admin.memes.index') }}" class="{{ request()->routeIs('admin.memes.*') ? 'active' : '' }}">😄 Мемы</a></li>
            <li><a href="{{ route('admin.meme-suggestions.index') }}" class="{{ request()->routeIs('admin.meme-suggestions.*') ? 'active' : '' }}">
                📥 Предложения мемов
                @php
                    try {
                        $pendingCount = \App\Models\MemeSuggestion::where('status', 'pending')->count();
                    } catch (\Exception $e) {
                        // Таблица еще не создана, скрываем бейдж
                        $pendingCount = 0;
                    }
                @endphp
                @if($pendingCount > 0)
                    <span class="badge badge-warning" style="margin-left: 5px;">{{ $pendingCount }}</span>
                @endif
            </a></li>
            <li><a href="{{ route('admin.statistics.index') }}" class="{{ request()->routeIs('admin.statistics.*') ? 'active' : '' }}">📈 Статистика</a></li>
            <li><a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">👥 Пользователи</a></li>
            <li><a href="{{ route('admin.chats.index') }}" class="{{ request()->routeIs('admin.chats.*') ? 'active' : '' }}">💬 Чаты</a></li>
            <li><a href="{{ route('admin.logs.index') }}" class="{{ request()->routeIs('admin.logs.*') ? 'active' : '' }}">📋 Логи</a></li>
        </ul>
    </div>
    @endauth

    <div class="main-content" style="{{ Auth::guard('admin')->check() ? '' : 'margin-left: 0;' }}">
        @auth('admin')
        <div class="header">
            <h1>@yield('page-title', 'Панель управления')</h1>
            <div class="user-menu">
                <span>{{ Auth::guard('admin')->user()->name ?? Auth::guard('admin')->user()->username }}</span>
                <form action="{{ route('admin.logout') }}" method="POST" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-secondary">Выход</button>
                </form>
            </div>
        </div>
        @endauth

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul style="margin: 0; padding-left: 20px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </div>

    @stack('scripts')
</body>
</html>
