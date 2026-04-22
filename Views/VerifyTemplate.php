<?php
namespace Views;
class VerifyTemplate extends BaseTemplate
{
    public static function getTemplate(): string
    {
        $template = parent::getTemplate();
        $title = 'Подтверждение входа';
        if (session_status() === PHP_SESSION_NONE) session_start();
        
        $error = $_SESSION['verify_error'] ?? '';
        unset($_SESSION['verify_error']);

        $errorHtml = $error ? '<div class="alert alert-danger mt-3">' . $error . '</div>' : '';

        $content = '
        <style>
            .verify-box { background: white; border-radius: 20px; padding: 3rem; max-width: 450px; margin: 3rem auto; text-align: center; }
            .btn-auth { background: linear-gradient(135deg, #ff6b35, #f7931e); border: none; padding: 1rem; color: white; font-weight: 700; border-radius: 50px; width: 100%; }
            input.code-input { font-size: 2rem; text-align: center; letter-spacing: 10px; font-weight: bold; }
        </style>
        <section class="container">
            <div class="verify-box">
                <h2 class="mb-3">📩 Введите код</h2>
                <p class="text-muted mb-4">Мы отправили 6-значный код на вашу почту</p>
                ' . $errorHtml . '
                <form method="POST" action="/verify">
                    <input type="text" name="code" class="form-control code-input mb-4" maxlength="6" placeholder="000000" required autofocus>
                    <button type="submit" class="btn-auth">Подтвердить</button>
                </form>
                <div class="mt-4 text-muted small">Не пришел код? Проверьте папку "Спам" или подождите минуту.</div>
            </div>
        </section>';
        return str_replace(['{{TITLE}}', '{{CONTENT}}'], [$title, $content], $template);
    }
}