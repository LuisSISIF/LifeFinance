<?php

class Relatorio
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstancia();
    }

    public function getKPIs($userId, $inicio, $fim)
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) total,
                SUM(CASE WHEN tipo = 'RECEITA' THEN valor ELSE 0 END) receitas,
                SUM(CASE WHEN tipo = 'DESPESA' THEN valor ELSE 0 END) despesas,
                SUM(CASE WHEN tipo = 'TRANSFERENCIA' THEN valor ELSE 0 END) transferencias
            FROM movimentacoes
            WHERE id_usuario = :uid AND ocorreu_em BETWEEN :ini AND :fim
        ");
        $stmt->execute([':uid' => $userId, ':ini' => $inicio, ':fim' => $fim]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getStatusResumo($userId, $inicio, $fim)
    {
        $stmt = $this->db->prepare("
            SELECT
                SUM(CASE WHEN status = 'PAGO' THEN 1 ELSE 0 END) pagas,
                SUM(CASE WHEN status = 'PENDENTE' THEN 1 ELSE 0 END) pendentes
            FROM movimentacoes
            WHERE id_usuario = :uid AND ocorreu_em BETWEEN :ini AND :fim
        ");
        $stmt->execute([':uid' => $userId, ':ini' => $inicio, ':fim' => $fim]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getGastosCategoria($userId, $inicio, $fim)
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(cat.nome, 'Sem categoria') nome, SUM(m.valor) total
            FROM movimentacoes m
            LEFT JOIN categorias cat ON cat.id = m.id_categoria
            WHERE m.id_usuario = :uid
              AND m.tipo = 'DESPESA'
              AND m.ocorreu_em BETWEEN :ini AND :fim
            GROUP BY COALESCE(cat.nome, 'Sem categoria')
            ORDER BY total DESC
            LIMIT 10
        ");
        $stmt->execute([':uid' => $userId, ':ini' => $inicio, ':fim' => $fim]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMesesFluxo($userId, $inicio12, $fim)
    {
        $stmt = $this->db->prepare("
            SELECT
                DATE_FORMAT(ocorreu_em, '%Y-%m') ym,
                SUM(CASE WHEN tipo = 'RECEITA' THEN valor ELSE 0 END) receitas,
                SUM(CASE WHEN tipo = 'DESPESA' THEN valor ELSE 0 END) despesas
            FROM movimentacoes
            WHERE id_usuario = :uid AND ocorreu_em BETWEEN :ini12 AND :fim
            GROUP BY DATE_FORMAT(ocorreu_em, '%Y-%m')
            ORDER BY ym ASC
        ");
        $stmt->execute([':uid' => $userId, ':ini12' => $inicio12, ':fim' => $fim]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMaioresMovimentacoes($userId, $inicio, $fim)
    {
        $stmt = $this->db->prepare("
            SELECT tipo, descricao, valor, status, ocorreu_em, codigo_moeda
            FROM movimentacoes
            WHERE id_usuario = :uid AND ocorreu_em BETWEEN :ini AND :fim
            ORDER BY valor DESC, ocorreu_em DESC
            LIMIT 10
        ");
        $stmt->execute([':uid' => $userId, ':ini' => $inicio, ':fim' => $fim]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFluxoDiario($userId, $inicio, $fim)
    {
        $stmt = $this->db->prepare("
            SELECT
                DAY(ocorreu_em) dia,
                SUM(CASE WHEN tipo = 'RECEITA' THEN valor ELSE 0 END) receitas,
                SUM(CASE WHEN tipo = 'DESPESA' THEN valor ELSE 0 END) despesas
            FROM movimentacoes
            WHERE id_usuario = :uid AND ocorreu_em BETWEEN :ini AND :fim
            GROUP BY DAY(ocorreu_em)
            ORDER BY dia ASC
        ");
        $stmt->execute([':uid' => $userId, ':ini' => $inicio, ':fim' => $fim]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDistribuicaoTipos($userId, $inicio, $fim)
    {
        $stmt = $this->db->prepare("
            SELECT tipo, COUNT(*) total, SUM(valor) soma
            FROM movimentacoes
            WHERE id_usuario = :uid AND ocorreu_em BETWEEN :ini AND :fim
            GROUP BY tipo
        ");
        $stmt->execute([':uid' => $userId, ':ini' => $inicio, ':fim' => $fim]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAlertas($userId, $inicio, $fim)
    {
        $stmt = $this->db->prepare("
            SELECT descricao, valor, status, ocorreu_em, vence_em, tipo
            FROM movimentacoes
            WHERE id_usuario = :uid
              AND ocorreu_em BETWEEN :ini AND :fim
              AND (status = 'PENDENTE' OR status = 'VENCIDO')
            ORDER BY COALESCE(vence_em, ocorreu_em) ASC
            LIMIT 8
        ");
        $stmt->execute([':uid' => $userId, ':ini' => $inicio, ':fim' => $fim]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
