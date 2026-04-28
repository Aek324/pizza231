<?php
// Включаем отображение ошибок (для разработки)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Автозагрузка
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    require_once __DIR__ . '/Services/Logger.php';
} else {
    die('❌ Ошибка: Не найден vendor/autoload.php');
}

// Глобальный обработчик ошибок
set_error_handler(function($severity, $message, $file, $line) {
    $logger = new \Services\Logger();
    $logger->error("PHP Error", [
        'message' => $message,
        'file' => $file,
        'line' => $line,
        'severity' => $severity
    ]);
    
    if (!(error_reporting() & $severity)) {
        return false;
    }
    
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function($exception) {
    $logger = new \Services\Logger();
    $logger->error("Uncaught Exception", [
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
    
    http_response_code(500);
    echo '<h1>Произошла ошибка</h1><p>Ошибка записана в лог.</p>';
});

// Импортируем классы
use Controllers\{
    HomeController,
    AboutController,
    ProductsController,
    ProductController,
    CartController,
    OrderController,
    ErrorController,
    UserController
};

// ... остальной код роутинга ...

// Получаем текущий URI
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$resource = trim($path, '/');
$resource = preg_replace('/[^a-zA-Z0-9\-_\/]/', '', $resource);

// ==========================================
// 👤 МАРШРУТЫ АВТОРИЗАЦИИ
// ==========================================
if ($resource === 'register' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    echo (new UserController())->showRegister();
    exit;
}
if ($resource === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new UserController())->register();
    exit;
}
if ($resource === 'login' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    echo (new UserController())->showLogin();
    exit;
}
if ($resource === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new UserController())->login();
    exit;
}
if ($resource === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new UserController())->logout();
    exit;
}
if ($resource === 'profile' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    echo (new UserController())->profile();
    exit;
}
if ($resource === 'profile/update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new UserController())->updateProfile();
    exit;
}


// 🆕 Маршрут подтверждения кода (страница ввода)
if ($resource === 'verify' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    echo (new UserController())->showVerify();
    exit;
}
if ($resource === 'verify' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new UserController())->verify();
    exit;
}
// ==========================================
// 🛒 МАРШРУТЫ КОРЗИНЫ
// ==========================================
if ($resource === 'cart/count' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    (new CartController())->getCountJson();
    exit;
}
if ($resource === 'cart/add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new CartController())->add();
    exit;
}
if ($resource === 'cart/remove' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new CartController())->remove();
    exit;
}
if ($resource === 'cart/clear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new CartController())->clear();
    exit;
}
if ($resource === 'cart/update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new CartController())->update();
    exit;
}
if ($resource === 'cart' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    echo (new CartController())->view();
    exit;
}

// ==========================================
// 📦 МАРШРУТЫ ЗАКАЗОВ
// ==========================================
if ($resource === 'order/checkout' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    echo (new OrderController())->checkout();
    exit;
}
if ($resource === 'order/submit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new OrderController())->submit();
    exit;
}
if ($resource === 'order/success' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    echo (new OrderController())->success();
    exit;
}

// ==========================================
// 🔐 МАРШРУТЫ АДМИНКИ
// ==========================================
if ($resource === 'admin/orders') {
    echo (new OrderController())->admin();
    exit;
}
if ($resource === 'admin/update-status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new OrderController())->updateStatus();
    exit;
}

// ==========================================
// 🍎 МАРШРУТЫ ТОВАРОВ
// ==========================================
if (preg_match('/^course\/(\d+)$/', $resource, $matches)) {
    $productId = (int)$matches[1];
    if ($productId >= 1 && $productId <= 6) {
        echo (new ProductController($productId))->get();
    } else {
        http_response_code(404);
        echo (new ErrorController())->get();
    }
    exit;
}

// ==========================================
// 🏠 ОСНОВНЫЕ МАРШРУТЫ
// ==========================================
switch ($resource) {
    case '':
    case 'home':
    case 'index.php':
        echo (new HomeController())->get();
        break;
    case 'products':
    case 'course':
        echo (new ProductsController())->get();
        break;
    case 'about':
        echo (new AboutController())->get();
        break;
    default:
        http_response_code(404);
        echo (new ErrorController())->get();
        break;
}