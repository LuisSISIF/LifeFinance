<?php

class Movimentacao
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstancia();
    }

    public function getStatsByUserId($userId)
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN tipo = 'RECEITA' THEN valor ELSE 0 END) AS receitas,
                SUM(CASE WHEN tipo = 'DESPESA' THEN valor ELSE 0 END) AS despesas,
                SUM(CASE WHEN tipo = 'TRANSFERENCIA' THEN valor ELSE 0 END) AS transferencias,
                SUM(CASE WHEN status = 'PAGO' THEN 1 ELSE 0 END) AS pagas,
                SUM(CASE WHEN status = 'PENDENTE' THEN 1 ELSE 0 END) AS pendentes
            FROM movimentacoes
            WHERE id_usuario = :uid
              AND MONTH(ocorreu_em) = MONTH(CURDATE())
              AND YEAR(ocorreu_em) = YEAR(CURDATE())
        ");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getRecentByUserId($userId, $limit = 20)
    {
        $stmt = $this->db->prepare("
            SELECT
                m.id, m.tipo, m.valor, m.descricao, m.status,
                m.ocorreu_em, m.vence_em, m.codigo_moeda,
                c.nome AS conta_nome,
                cat.nome AS categoria_nome
            FROM movimentacoes m
            LEFT JOIN contas c ON c.id = m.id_conta
            LEFT JOIN categorias cat ON cat.id = m.id_categoria
            WHERE m.id_usuario = :uid
            ORDER BY m.ocorreu_em DESC, m.id DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
