<?php
namespace Controllers;

use Services\UserService;
use Services\Logger;
use Views\{RegisterTemplate, LoginTemplate, ProfileTemplate, VerifyTemplate};
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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

    public function showLogin(): string
    {
        return LoginTemplate::getTemplate();
    }

    public function showVerify(): string
    {
        if (!isset($_SESSION['pending_user_id'])) {
            header('Location: /login');
            exit;
        }
        return VerifyTemplate::getTemplate();
    }

    public function register(): void
    {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass = $_POST['password'] ?? '';
        $conf = $_POST['confirm_password'] ?? '';
        $errors = [];

        if (!$name) $errors[] = 'Введите имя';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Неверный email';
        if (strlen($pass) < 6) $errors[] = 'Пароль мин. 6 символов';
        if ($pass !== $conf) $errors[] = 'Пароли не совпадают';
        if (!$errors && $this->userService->findByEmail($email)) {
            $errors[] = 'Email уже зарегистрирован';
        }

        if ($errors) {
            $_SESSION['auth_errors'] = $errors;
            $_SESSION['auth_data'] = $_POST;
            header('Location: /register');
            exit;
        }

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $id = $this->userService->create([
            'name' => $name,
            'email' => $email,
            'password' => $hash,
            'code' => $code
        ]);

        $this->sendCode($email, $code);
        $_SESSION['pending_user_id'] = $id;
        header('Location: /verify');
        exit;
    }

    public function login(): void
    {
        $email = trim($_POST['email'] ?? '');
        $pass = $_POST['password'] ?? '';
        $user = $this->userService->findByEmail($email);

        if (!$user || !password_verify($pass, $user['password'])) {
            $_SESSION['auth_errors'] = ['Неверный логин или пароль'];
            header('Location: /login');
            exit;
        }

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->userService->update($user['id'], ['verification_code' => $code]);
        $this->sendCode($user['email'], $code);
        
        $_SESSION['pending_user_id'] = $user['id'];
        header('Location: /verify');
        exit;
    }

    public function verify(): void
    {
        if (!isset($_SESSION['pending_user_id'])) {
            header('Location: /login');
            exit;
        }
        $id = $_SESSION['pending_user_id'];
        $user = $this->userService->findById($id);

        if ($user && $user['verification_code'] === ($_POST['code'] ?? '')) {
            $this->userService->verify($id);
            $_SESSION['user_id'] = $id;
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            unset($_SESSION['pending_user_id']);
            header('Location: /profile');
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

        $db = \Config\Database::getInstance();
        $stmt = $db->prepare("
            SELECT o.*, 
                   (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count
            FROM orders o
            WHERE o.user_id = ? OR o.customer_email = ?
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$_SESSION['user_id'], $_SESSION['user_email']]);
        $userOrders = $stmt->fetchAll();

        return ProfileTemplate::getTemplate($userOrders);
    }

    public function updateProfile(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            $this->userService->update($_SESSION['user_id'], ['name' => $name]);
            $_SESSION['user_name'] = $name;
        }
        header('Location: /profile');
        exit;
    }

    public function logout(): void
    {
        session_unset();
        header('Location: /');
        exit;
    }

    private function sendCode(string $email, string $code): void
    {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $this->logger->error("PHPMailer не найден");
            return;
        }
        
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'aekbokhan214323@gmail.com';
            $mail->Password = 'tbabwwckhvqybjsm';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            
            $mail->setFrom('aekbokhan214323@gmail.com', 'Produkty24');
            $mail->addAddress($email);
            
            $mail->isHTML(true);
            $mail->Subject = 'Kod podtverzhdeniya: ' . $code;
            $mail->Body = "<html><head><meta charset='UTF-8'></head><body>";
            $mail->Body .= "<h3>Zdravstvuyte!</h3>";
            $mail->Body .= "<p>Vash kod podtverzhdeniya:</p>";
            $mail->Body .= "<p style='font-size: 24px; font-weight: bold; color: #ff6b35;'>{$code}</p>";
            $mail->Body .= "<p>Ne soobshchayte etot kod nikomu.</p>";
            $mail->Body .= "</body></html>";
            
            $mail->AltBody = 'Vash kod podtverzhdeniya: ' . $code;
            
            $mail->send();
            $this->logger->info("Kod otpravlen na {$email}");
        } catch (\Exception $e) {
            $this->logger->error("Oshibka otpravki: " . $e->getMessage());
        }
    }
}