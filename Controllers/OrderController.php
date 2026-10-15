<?php
namespace Controllers;

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Config\Database;

class OrderController
{
    public function checkout()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['cart']) || count($_SESSION['cart']) === 0) {
            header('Location: /cart?error=empty_cart');
            exit;
        }
        return \Views\OrderTemplate::getTemplate();
    }

    public function submit()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['cart']) || count($_SESSION['cart']) === 0) {
            header('Location: /cart?error=empty_cart');
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');

        $errors = [];
        if (empty($name)) $errors[] = 'Укажите ФИО';
        if (empty($phone) || !preg_match('/^\+?[0-9\s\-()]{10,18}$/', $phone)) {
            $errors[] = 'Некорректный номер телефона';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Некорректный email';

        if (!empty($errors)) {
            $_SESSION['order_errors'] = $errors;
            $_SESSION['order_data'] = $_POST;
            header('Location: /order/checkout');
            exit;
        }

        try {
            $db = Database::getInstance();
            
            $totalPrice = 0;
            foreach ($_SESSION['cart'] as $item) {
                $priceNum = (int)preg_replace('/[^0-9]/', '', $item['price']);
                $quantity = $item['quantity'] ?? 1;
                $totalPrice += $priceNum * $quantity;
            }

            $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            $userId = $_SESSION['user_id'] ?? null;
            
            if ($userId === null && !empty($email)) {
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                if ($user) {
                    $userId = $user['id'];
                }
            }
            
            $db->beginTransaction();
            
            $stmt = $db->prepare("
                INSERT INTO orders (order_number, user_id, customer_name, customer_phone, 
                                   customer_email, total_price, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'new', NOW())
            ");
            
            $stmt->execute([
                $orderNumber,
                $userId,
                $name,
                $phone,
                $email,
                $totalPrice
            ]);
            
            $orderId = (int)$db->lastInsertId();
            
            foreach ($_SESSION['cart'] as $item) {
                $priceNum = (int)preg_replace('/[^0-9]/', '', $item['price']);
                $quantity = $item['quantity'] ?? 1;
                
                $stmt = $db->prepare("
                    INSERT INTO order_items (order_id, product_id, product_title, 
                                            quantity, price_at_moment)
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $orderId,
                    $item['id'] ?? 0,
                    $item['title'],
                    $quantity,
                    $priceNum
                ]);
            }
            
            $db->commit();
            
            // Отправка email
            $this->sendOrderEmail([
                'order_number' => $orderNumber,
                'customer_name' => $name,
                'customer_phone' => $phone,
                'customer_email' => $email,
                'total_price' => $totalPrice,
                'items' => $_SESSION['cart']
            ]);
            
            $_SESSION['cart'] = [];
            $_SESSION['last_order_id'] = $orderNumber;
            
            header('Location: /order/success');
            exit;
            
        } catch (\Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['order_errors'] = ['Ошибка: ' . $e->getMessage()];
            header('Location: /order/checkout');
            exit;
        }
    }

    public function success(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($_SESSION['last_order_id'])) {
            header('Location: /home');
            exit;
        }
        
        return \Views\OrderSuccessTemplate::getTemplate();
    }

    public function admin()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_POST['admin_password'])) {
            if ($_POST['admin_password'] === 'admin123') {
                $_SESSION['admin_logged'] = true;
            } else {
                $_SESSION['admin_error'] = 'Неверный пароль';
            }
        }
        if (empty($_SESSION['admin_logged'])) {
            return \Views\AdminLoginTemplate::getTemplate();
        }
        $orders = json_decode(file_get_contents(__DIR__ . '/../data/orders.json'), true) ?? [];
        return \Views\AdminOrdersTemplate::getTemplate($orders);
    }

    public function updateStatus()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['admin_logged'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'unauthorized']);
            exit;
        }
        $orderId = $_POST['order_id'] ?? '';
        $status = $_POST['status'] ?? '';
        $validStatuses = ['new', 'processing', 'paid', 'shipped', 'completed', 'cancelled'];
        if (!in_array($status, $validStatuses)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'invalid_status']);
            exit;
        }
        $orders = json_decode(file_get_contents(__DIR__ . '/../data/orders.json'), true) ?? [];
        foreach ($orders as &$order) {
            if ($order['id'] === $orderId) {
                $order['status'] = $status;
                $order['updated_at'] = date('Y-m-d H:i:s');
                break;
            }
        }
        file_put_contents(__DIR__ . '/../data/orders.json', json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo json_encode(['success' => true]);
        exit;
    }

    private function sendOrderEmail(array $order): void
    {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
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
            $mail->addAddress('aekbokhan214323@gmail.com');
            $mail->addReplyTo($order['customer_email'], $order['customer_name']);
            
            $itemsHtml = '';
            foreach ($order['items'] as $item) {
                $itemsHtml .= "<tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'>{$item['title']}</td></tr>";
            }
            
            $mail->isHTML(true);
            $mail->Subject = 'Noviy zakaz: ' . $order['order_number'];
            $mail->Body = "<html><head><meta charset='UTF-8'></head><body>";
            $mail->Body .= "<h2>Noviy zakaz #{$order['order_number']}</h2>";
            $mail->Body .= "<p><strong>Klient:</strong> {$order['customer_name']}</p>";
            $mail->Body .= "<p><strong>Telefon:</strong> {$order['customer_phone']}</p>";
            $mail->Body .= "<p><strong>Email:</strong> {$order['customer_email']}</p>";
            $mail->Body .= "<h3>Tovari:</h3><table style='width:100%; border-collapse: collapse;'>{$itemsHtml}</table>";
            $mail->Body .= "<p><strong>Itogo: {$order['total_price']} ₽</strong></p>";
            $mail->Body .= "</body></html>";
            
            $mail->AltBody = "Zakaz #{$order['order_number']}\nKlient: {$order['customer_name']}\nTelefon: {$order['customer_phone']}\nSumma: {$order['total_price']} ₽";
            
            $mail->send();
        } catch (\Exception $e) {
            error_log("Email error: " . $e->getMessage());
        }
    }
}