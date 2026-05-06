<?php

class Meta
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstancia();
    }

    public function getAllByUserId($userId)
    {
        $stmt = $this->db->prepare("
            SELECT
                id, nome, descricao, valor_meta, valor_atual, codigo_moeda,
                data_limite, ativa, compartilhada, reserva_emergencia,
                criado_em, atualizado_em
            FROM metas_financeiras
            WHERE id_usuario = :uid
            ORDER BY criado_em DESC, id DESC
        ");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function delete($id, $userId)
    {
        $stmt = $this->db->prepare("
            DELETE FROM metas_financeiras
            WHERE id = :id AND id_usuario = :uid
        ");
        return $stmt->execute([
            ':id' => $id,
            ':uid' => $userId
        ]);
    }

    // save() logic will be moved here later
}
