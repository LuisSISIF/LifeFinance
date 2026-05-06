<?php

class User
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstancia();
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateProfile($id, $data)
    {
        $stmt = $this->db->prepare("
            UPDATE usuarios
            SET
                email = :email,
                telefone = :telefone,
                tema = :tema,
                moeda_padrao = :moeda_padrao,
                idioma = :idioma,
                notificacoes_email = :notif_email,
                notificacoes_app = :notif_app,
                metas_ativas = :metas_ativas,
                atualizado_em = NOW()
            WHERE id = :id
        ");
        return $stmt->execute([
            ':email' => $data['email'],
            ':telefone' => $data['telefone'],
            ':tema' => $data['tema'],
            ':moeda_padrao' => $data['moeda_padrao'],
            ':idioma' => $data['idioma'],
            ':notif_email' => $data['notificacoes_email'],
            ':notif_app' => $data['notificacoes_app'],
            ':metas_ativas' => $data['metas_ativas'],
            ':id' => $id
        ]);
    }

    public function updatePassword($id, $hash, $coluna)
    {
        $stmt = $this->db->prepare("UPDATE usuarios SET $coluna = :senha, atualizado_em = NOW() WHERE id = :id");
        return $stmt->execute([':senha' => $hash, ':id' => $id]);
    }
}
