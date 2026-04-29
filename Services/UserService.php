<?php
namespace Services;

use Config\Database;
use PDO;

class UserService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("INSERT INTO users (name, email, password, role, is_verified, verification_code, created_at) VALUES (?, ?, ?, 'user', 0, ?, NOW())");
        $stmt->execute([$data['name'], $data['email'], $data['password'], $data['code']]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $vals = [];
        foreach ($data as $k => $v) {
            $fields[] = "$k = ?";
            $vals[] = $v;
        }
        $vals[] = $id;
        return $this->db->prepare("UPDATE users SET " . implode(',', $fields) . " WHERE id = ?")->execute($vals);
    }

    // Этот метод вызывал ошибку ранее, теперь он здесь
    public function verify(int $id): bool
    {
        return $this->update($id, ['is_verified' => 1, 'verification_code' => null]);
    }
}