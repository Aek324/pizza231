<?php
namespace Controllers;
use Views\{RegisterTemplate, LoginTemplate, ProfileTemplate, VerifyTemplate};
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class UserController
{
    private $usersFile;
    private $ordersFile;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $this->usersFile = __DIR__ . '/../data/users.json';
        $this->ordersFile = __DIR__ . '/../data/orders.json';
        
        if (!file_exists(dirname($this->usersFile))) mkdir(dirname($this->usersFile), 0755, true);
        if (!file_exists($this->usersFile)) file_put_contents($this->usersFile, json_encode([]));
        if (!file_exists($this->ordersFile)) file_put_contents($this->ordersFile, json_encode([]));
    }

    private function getUsers(): array { return json_decode(file_get_contents($this->usersFile), true) ?? []; }
    private function saveUsers(array $users): void { file_put_contents($this->usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); }

    // 🟢 Показать регистрацию
    public function showRegister(): string { return RegisterTemplate::getTemplate(); }

    // 🟢 Обработка регистрации (отправка кода)
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
            $users = $this->getUsers();
            foreach ($users as $u) {
                if (strtolower($u['email']) === strtolower($email)) { $errors[] = 'Email занят'; break; }
            }
        }

        if (!empty($errors)) {
            $_SESSION['auth_errors'] = $errors;
            $_SESSION['auth_data'] = $_POST;
            header('Location: /register'); exit;
        }

        $newUser = [
            'id' => 'user_' . uniqid(),
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'phone' => '',
            'is_verified' => false, // ⛔ Не подтвержден
            'verification_code' => null,
            'created_at' => date('Y-m-d H:i:s')
        ];

        // Генерируем код
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $newUser['verification_code'] = $code;

        array_unshift($users, $newUser);
        $this->saveUsers($users);

        // Отправляем код
        $this->sendVerificationEmail($email, $code);

        // Запоминаем, кто регистрируется, но еще не подтвержден
        $_SESSION['pending_user_id'] = $newUser['id'];
        
        header('Location: /verify'); 
        exit;
    }

    // 🟢 Показать вход
    public function showLogin(): string { return LoginTemplate::getTemplate(); }

    // 🟢 Вход (запрос кода)
    public function login(): void
    {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $errors = [];

        if (empty($email) || empty($password)) $errors[] = 'Заполните поля';

        if (empty($errors)) {
            $users = $this->getUsers();
            $user = null;
            foreach ($users as $u) {
                if (strtolower($u['email']) === strtolower($email)) { $user = $u; break; }
            }

            if (!$user || !password_verify($password, $user['password'])) {
                $errors[] = 'Неверный логин или пароль';
            } else {
                // Если пароль верный — генерируем код для входа (2FA)
                $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                
                // Обновляем код в файле
                foreach ($users as &$u) {
                    if ($u['id'] === $user['id']) {
                        $u['verification_code'] = $code;
                        break;
                    }
                }
                $this->saveUsers($users);

                $this->sendVerificationEmail($user['email'], $code);
                
                // Запоминаем ID для верификации
                $_SESSION['pending_user_id'] = $user['id'];
                header('Location: /verify'); exit;
            }
        }

        if (!empty($errors)) {
            $_SESSION['auth_errors'] = $errors;
            header('Location: /login'); exit;
        }
    }

    // 🟢 Экран ввода кода
    public function showVerify(): string 
    {
        if (!isset($_SESSION['pending_user_id'])) {
            header('Location: /login'); exit;
        }
        return VerifyTemplate::getTemplate(); 
    }

    // 🟢 Проверка кода
    public function verify(): void
    {
        if (!isset($_SESSION['pending_user_id'])) { header('Location: /login'); exit; }
        
        $codeInput = trim($_POST['code'] ?? '');
        $userId = $_SESSION['pending_user_id'];
        $users = $this->getUsers();
        $userFound = null;

        foreach ($users as &$u) {
            if ($u['id'] === $userId) {
                $userFound = $u;
                if ($u['verification_code'] === $codeInput) {
                    // ✅ Код верный
                    $u['is_verified'] = true;
                    $u['verification_code'] = null; // Удаляем код
                    $this->saveUsers($users);
                    
                    // Логиним
                    $_SESSION['user_id'] = $u['id'];
                    $_SESSION['user_name'] = $u['name'];
                    $_SESSION['user_email'] = $u['email'];
                    $_SESSION['user_phone'] = $u['phone'] ?? '';
                    unset($_SESSION['pending_user_id']);
                    
                    header('Location: /profile?success=verified'); exit;
                } else {
                    $_SESSION['verify_error'] = 'Неверный код';
                    header('Location: /verify'); exit;
                }
                break;
            }
        }
        
        header('Location: /login'); exit;
    }

    // 🟢 Выход
    public function logout(): void
    {
        session_unset();
        header('Location: /'); exit;
    }

    // 🟢 Профиль + История заказов
    public function profile(): string
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login'); exit;
        }

        // Загружаем заказы
        $orders = json_decode(file_get_contents($this->ordersFile), true) ?? [];
        $userOrders = [];
        
        // Фильтруем заказы только текущего пользователя
        foreach ($orders as $order) {
            if (isset($order['user_id']) && $order['user_id'] === $_SESSION['user_id']) {
                $userOrders[] = $order;
            }
        }

        return ProfileTemplate::getTemplate($userOrders);
    }

    public function updateProfile(): void
    {
        // ... (Код обновления профиля оставьте как был, он работает) ...
        if (!isset($_SESSION['user_id'])) { header('Location: /login'); exit; }
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) { $_SESSION['profile_errors'] = ['Введите имя']; header('Location: /profile'); exit; }
        
        $users = $this->getUsers();
        foreach ($users as &$u) {
            if ($u['id'] === $_SESSION['user_id']) {
                $u['name'] = $name;
                $_SESSION['user_name'] = $name;
                break;
            }
        }
        $this->saveUsers($users);
        header('Location: /profile'); exit;
    }

    // 📧 Функция отправки письма
    private function sendVerificationEmail($toEmail, $code): void
    {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) return;
        
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'aekbokhan214323@gmail.com';
            $mail->Password = 'tbabwwckhvqybjsm';
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->CharSet = 'UTF-8';
            
            $mail->setFrom('aekbokhan214323@gmail.com', 'Продукты24');
            $mail->addAddress($toEmail);
            
            $mail->Subject = '🔐 Ваш код подтверждения: ' . $code;
            $mail->isHTML(true);
            $mail->Body = "<h3>Здравствуйте!</h3><p>Ваш код подтверждения: <b style='font-size:24px;'>{$code}</b></p><p>Не сообщайте его никому.</p>";
            $mail->send();
        } catch (Exception $e) {
            // Ошибка отправки не блокирует процесс (код всё равно сгенерирован)
            error_log($mail->ErrorInfo);
        }
    }
}