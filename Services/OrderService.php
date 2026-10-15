<?php
namespace Services;

use Config\Database;
use PDO;

class OrderService
{
    private PDO $db;
    private Logger $logger;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger();
    }
    
    public function create(array $data, int $userId = null): int
    {
        $this->db->beginTransaction();
        
        try {
            $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            
            $stmt = $this->db->prepare("
                INSERT INTO orders (order_number, user_id, customer_name, customer_phone, 
                                   customer_email, total_amount, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'new', NOW())
            ");
            
            $stmt->execute([
                $orderNumber,
                $userId,
                $data['customer_name'],
                $data['customer_phone'],
                $data['customer_email'],
                $data['total_amount']
            ]);
            
            $orderId = (int)$this->db->lastInsertId();
            
            // Сохраняем товары
            foreach ($data['items'] as $item) {
                $stmt = $this->db->prepare("
                    INSERT INTO order_items (order_id, product_id, product_title, 
                                           product_price, quantity)
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $orderId,
                    $item['id'],
                    $item['title'],
                    preg_replace('/[^0-9]/', '', $item['price']),
                    $item['quantity'] ?? 1
                ]);
            }
            
            $this->db->commit();
            $this->logger->info("Order created", ['order_id' => $orderId, 'order_number' => $orderNumber]);
            
            return $orderId;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->logger->error("Order creation failed", ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    public function findByUserId(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT o.*, 
                   (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count
            FROM orders o
            WHERE o.user_id = ?
            ORDER BY o.created_at DESC
        ");
        
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    public function findAll(): array
    {
        $stmt = $this->db->query("
            SELECT o.*, u.name as user_name,
                   (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            ORDER BY o.created_at DESC
        ");
        
        return $stmt->fetchAll();
    }
    
    public function updateStatus(int $orderId, string $status): bool
    {
        $stmt = $this->db->prepare("
            UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?
        ");
        
        $result = $stmt->execute([$status, $orderId]);
        $this->logger->info("Order status updated", ['order_id' => $orderId, 'status' => $status]);
        
        return $result;
    }
}