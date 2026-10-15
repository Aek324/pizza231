<?php
namespace Config;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            try {
                // Убедитесь, что имя базы данных правильное (produkty24_db)
                self::$instance = new PDO(
                    'mysql:host=localhost;dbname=produkty24_db;charset=utf8mb4',
                    'root',      // Если у вас другой пароль, впишите его сюда
                    '',          
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
            } catch (PDOException $e) {
                die("Ошибка подключения к БД: " . $e->getMessage());
            }
        }
        return self::$instance;
    }
}