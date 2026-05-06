<?php

class Usuario
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstancia();
    }

    public function findByEmail($email)
    {
        $stmt = $this->db->prepare('SELECT id, email, senha_hash, status, role FROM usuarios WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        return $stmt->fetch();
    }

    public function create($data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO usuarios (email, senha_hash, status, role)
            VALUES (:email, :senha_hash, 'ATIVO', 'USER')
        ");
        return $stmt->execute([
            ':email' => $data['email'],
            ':senha_hash' => password_hash($data['senha'], PASSWORD_DEFAULT)
        ]);
    }
}
