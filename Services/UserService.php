<?php
namespace Services;

use Config\Database;
use PDO;

class UserService
{
    private PDO $db;
    private Logger $logger;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger();
    }
    
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        return $user ?: null;
    }
    
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        return $user ?: null;
    }
    
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO users (name, email, password, role, is_verified, verification_code, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $data['name'],
            $data['email'],
            $data['password'],
            $data['role'] ?? 'user',
            $data['is_verified'] ?? 0,
            $data['verification_code'] ?? null
        ]);
        
        $userId = (int)$this->db->lastInsertId();
        $this->logger->info("User created", ['user_id' => $userId, 'email' => $data['email']]);
        
        return $userId;
    }
    
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $values[] = $value;
        }
        
        $values[] = $id;
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($values);
        
        $this->logger->info("User updated", ['user_id' => $id]);
        
        return $result;
    }
    
    public function verifyEmail(int $userId): bool
    {
        return $this->update($userId, ['is_verified' => 1, 'verification_code' => null]);
    }
    
    public function hasRole(int $userId, string $role): bool
    {
        $user = $this->findById($userId);
        return $user && $user['role'] === $role;
    }
    
    public function isAdmin(int $userId): bool
    {
        return $this->hasRole($userId, 'admin');
    }
}