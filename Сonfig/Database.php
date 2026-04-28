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
                // Замени логин/пароль если нужно
                self::$instance = new PDO(
                    'mysql:host=localhost;dbname=produkty24;charset=utf8mb4',
                    'root',      
                    '',          
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
            } catch (PDOException $e) {
                throw new PDOException("Connection failed: " . $e->getMessage());
            }
        }
        return self::$instance;
    }
}