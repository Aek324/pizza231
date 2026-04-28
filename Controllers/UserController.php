<?php
namespace Controllers;

use Services\UserService;
use Services\Logger;
use Views\{RegisterTemplate, LoginTemplate, ProfileTemplate, VerifyTemplate};

class UserController
{
    private UserService $userService;
    private Logger $logger;
    
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->userService = new UserService();
        $this->logger = new Logger();
    }
    
    public function showRegister(): string
    {
        return RegisterTemplate::getTemplate();
    }
    
    public function register(): void
    {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        $errors = [];
        
        if (empty($name)) $errors[] = 'Введите имя';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Некорректный email';
        if (strlen($password) < 6) $errors[] = 'Пароль мин. 6 символов';
        if ($password !== $confirm) $errors[] = 'Пароли не совпадают';
        
        if (empty($errors)) {
            $existingUser = $this->userService->findByEmail($email);
            if ($existingUser) {
                $errors[] = 'Email уже зарегистрирован';
            }
        }
        
        if (!empty($errors)) {
            $_SESSION['auth_errors'] = $errors;
            $_SESSION['auth_data'] = $_POST;
            header('Location: /register');
            exit;
        }
        
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $userId = $this->userService->create([
            'name' => $name,
            'email' => $email,
            'password' => $hashedPassword,
            'role' => 'user',
            'is_verified' => 0,
            'verification_code' => $code
        ]);
        
        // Отправка email с кодом (упрощенно)
        $this->sendVerificationEmail($email, $code);
        
        $_SESSION['pending_user_id'] = $userId;
        $this->logger->info("Registration started", ['user_id' => $userId, 'email' => $email]);
        
        header('Location: /verify');
        exit;
    }
    
    public function showLogin(): string
    {
        return LoginTemplate::getTemplate();
    }
    
    public function login(): void
    {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        $user = $this->userService->findByEmail($email);
        
        if (!$user || !password_verify($password, $user['password'])) {
            $_SESSION['auth_errors'] = ['Неверный email или пароль'];
            $this->logger->warning("Login failed", ['email' => $email]);
            header('Location: /login');
            exit;
        }
        
        // Генерация кода для 2FA
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->userService->update($user['id'], ['verification_code' => $code]);
        
        $this->sendVerificationEmail($user['email'], $code);
        $_SESSION['pending_user_id'] = $user['id'];
        
        $this->logger->info("2FA code sent", ['user_id' => $user['id']]);
        header('Location: /verify');
        exit;
    }
    
    public function showVerify(): string
    {
        if (!isset($_SESSION['pending_user_id'])) {
            header('Location: /login');
            exit;
        }
        return VerifyTemplate::getTemplate();
    }
    
    public function verify(): void
    {
        if (!isset($_SESSION['pending_user_id'])) {
            header('Location: /login');
            exit;
        }
        
        $codeInput = trim($_POST['code'] ?? '');
        $userId = $_SESSION['pending_user_id'];
        $user = $this->userService->findById($userId);
        
        if (!$user) {
            header('Location: /login');
            exit;
        }
        
        if ($user['verification_code'] === $codeInput) {
            $this->userService->verifyEmail($userId);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            unset($_SESSION['pending_user_id']);
            
            $this->logger->info("User logged in", ['user_id' => $userId]);
            header('Location: /profile?success=verified');
            exit;
        }
        
        $_SESSION['verify_error'] = 'Неверный код';
        header('Location: /verify');
        exit;
    }
    
    public function profile(): string
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }
        
        // Здесь нужно получить заказы пользователя из БД
        return ProfileTemplate::getTemplate([]);
    }
    
    public function logout(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        session_unset();
        
        if ($userId) {
            $this->logger->info("User logged out", ['user_id' => $userId]);
        }
        
        header('Location: /');
        exit;
    }

        private function sendVerificationEmail(string $toEmail, string $code): void
    {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $this->logger->warning("PHPMailer не найден. Код не отправлен.");
            return;
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'aekbokhan214323@gmail.com';
            $mail->Password = 'tbabwwckhvqybjsm'; // Ваш App Password
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom('aekbokhan214323@gmail.com', 'Продукты24');
            $mail->addAddress($toEmail);

            $mail->Subject = '🔐 Ваш код подтверждения: ' . $code;
            $mail->isHTML(true);
            $mail->Body = "<h3>Здравствуйте!</h3><p>Ваш код подтверждения для входа/регистрации: <b style='font-size:24px; color:#ff6b35;'>{$code}</b></p><p>Никому не сообщайте этот код. Если это были не вы — проигнорируйте письмо.</p>";
            $mail->AltBody = 'Ваш код подтверждения: ' . $code;

            $mail->send();
            $this->logger->info("Код успешно отправлен на {$toEmail}");
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            $this->logger->error("Ошибка отправки email: " . $mail->ErrorInfo);
        }
    }
}