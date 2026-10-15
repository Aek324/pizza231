<?php
// Используем правильные слеши для Windows
require_once __DIR__ . '\\Config\\Database.php';

use Config\Database;

echo "Пытаемся создать экземпляр...<br>";
try {
    $db = Database::getInstance();
    echo "✅ УСПЕХ! База данных подключена. Класс найден.";
} catch (Exception $e) {
    echo "❌ ОШИБКА: " . $e->getMessage();
}   