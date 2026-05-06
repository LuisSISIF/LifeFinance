<?php

class Categoria
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstancia();
    }

    public function getAllByUserId($userId)
    {
        $stmt = $this->db->prepare("
            SELECT id, nome, tipo, criado_em, atualizado_em
            FROM categorias
            WHERE id_usuario = :uid
            ORDER BY tipo ASC, nome ASC
        ");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStatsByUserId($userId)
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN tipo = 'RECEITA' THEN 1 ELSE 0 END) AS receitas,
                SUM(CASE WHEN tipo = 'DESPESA' THEN 1 ELSE 0 END) AS despesas
            FROM categorias
            WHERE id_usuario = :uid
        ");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($userId, $nome, $tipo)
    {
        $stmt = $this->db->prepare("
            INSERT INTO categorias (id_usuario, nome, tipo)
            VALUES (:uid, :nome, :tipo)
        ");
        return $stmt->execute([
            ':uid' => $userId,
            ':nome' => $nome,
            ':tipo' => $tipo
        ]);
    }

    public function update($id, $userId, $nome, $tipo)
    {
        $stmt = $this->db->prepare("
            UPDATE categorias
            SET nome = :nome, tipo = :tipo
            WHERE id = :id AND id_usuario = :uid
        ");
        return $stmt->execute([
            ':id' => $id,
            ':uid' => $userId,
            ':nome' => $nome,
            ':tipo' => $tipo
        ]);
    }

    public function delete($id, $userId)
    {
        // Verifica dependências
        $check = $this->db->prepare("
            SELECT COUNT(*)
            FROM movimentacoes
            WHERE id_categoria = :id AND id_usuario = :uid
        ");
        $check->execute([':id' => $id, ':uid' => $userId]);

        if ((int)$check->fetchColumn() > 0) {
            throw new Exception('Não é possível excluir categoria vinculada a movimentações.');
        }

        $stmt = $this->db->prepare("
            DELETE FROM categorias
            WHERE id = :id AND id_usuario = :uid
        ");
        return $stmt->execute([
            ':id' => $id,
            ':uid' => $userId
        ]);
    }
}
