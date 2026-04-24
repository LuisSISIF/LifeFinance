<?php
require_once __DIR__ . '/Conexao.php';
try {
    $pdo = Conexao::getInstancia();
    $tables = ['usuarios', 'perfis_usuarios', 'contas', 'movimentacoes', 'orcamentos', 'itens_orcamento', 'metas_financeiras', 'aportes_meta', 'contas_pagar', 'contas_receber', 'movimentacoes_agendadas', 'categorias', 'regras_movimentacao'];
    $schema = [];
    foreach ($tables as $table) {
        $cols = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
        $schema[$table] = array_map(function($c) { return $c['Field']; }, $cols);
    }
    echo json_encode($schema, JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage();
}
