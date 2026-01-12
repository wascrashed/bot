<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход - Админ-панель</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-container { background: white; border-radius: 8px; padding: 40px; width: 100%; max-width: 400px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .login-header { text-align: center; margin-bottom: 30px; }
        .login-header h1 { color: #2c3e50; font-size: 24px; margin-bottom: 10px; }
        .login-header p { color: #7f8c8d; font-size: 14px; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: 500; color: #2c3e50; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        .form-control:focus { outline: none; border-color: #667eea; }
        .btn { width: 100%; padding: 12px; background: #667eea; color: white; border: none; border-radius: 4px; font-size: 16px; font-weight: 500; cursor: pointer; }
        .btn:hover { background: #5568d3; }
        .alert { padding: 12px; border-radius: 4px; margin-bottom: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .remember { display: flex; align-items: center; gap: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🤖 Quiz Bot Admin</h1>
            <p>Войдите в админ-панель</p>
        </div>

        @if ($errors->any())
            <div class="alert">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.login') }}">
            @csrf

            <div class="form-group">
                <label class="form-label" for="username">Имя пользователя</label>
                <input type="text" id="username" name="username" class="form-control" value="{{ old('username') }}" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Пароль</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>

            <div class="remember">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Запомнить меня</label>
            </div>

            <button type="submit" class="btn">Войти</button>
        </form>

        <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 4px; font-size: 12px; color: #856404;">
            <strong>По умолчанию:</strong><br>
            Username: <code>admin</code><br>
            Password: <code>admin</code>
        </div>
    </div>
</body>
</html>
