<?php
namespace Views;

class RegisterTemplate extends BaseTemplate
{
    public static function getTemplate(): string
    {
        $template = parent::getTemplate();
        $title = 'Регистрация - Продукты24';
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $errors = $_SESSION['auth_errors'] ?? [];
        $data = $_SESSION['auth_data'] ?? [];
        unset($_SESSION['auth_errors'], $_SESSION['auth_data']);

        $errorHtml = '';
        if (!empty($errors)) {
            $errorHtml = '<div class="alert alert-danger"><ul class="mb-0">';
            foreach ($errors as $error) {
                $errorHtml .= '<li>' . htmlspecialchars($error) . '</li>';
            }
            $errorHtml .= '</ul></div>';
        }

        $content = '
        <style>
            .auth-box {
                background: white;
                border-radius: 20px;
                padding: 3rem;
                max-width: 500px;
                margin: 3rem auto;
                box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            }
            .btn-auth {
                background: linear-gradient(135deg, #ff6b35, #f7931e);
                border: none;
                padding: 1rem;
                color: white;
                font-weight: 700;
                border-radius: 50px;
                width: 100%;
            }
        </style>
        <section class="container">
            <div class="auth-box">
                <h2 class="text-center mb-4">📝 Регистрация</h2>
                ' . $errorHtml . '
                <form method="POST" action="/register">
                    <div class="mb-3">
                        <label class="form-label">Имя</label>
                        <input type="text" name="name" class="form-control" value="' . htmlspecialchars($data['name'] ?? '') . '" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="' . htmlspecialchars($data['email'] ?? '') . '" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Пароль</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Подтвердите пароль</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn-auth">Зарегистрироваться</button>
                </form>
                <p class="text-center mt-3">Уже есть аккаунт? <a href="/login">Войти</a></p>
            </div>
        </section>';
        
        return str_replace(['{{TITLE}}', '{{CONTENT}}'], [$title, $content], $template);
    }
}