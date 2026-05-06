<?php

/*
|--------------------------------------------------------------------------
| Inspeção do esquema do banco
|--------------------------------------------------------------------------
| Este arquivo consulta a estrutura das tabelas informadas e retorna,
| em formato JSON, os nomes das colunas existentes em cada uma delas.
| É útil para diagnóstico, documentação e validação de schema.
*/
require_once __DIR__ . '/Conexao.php';

try {
    /*
    |--------------------------------------------------------------------------
    | Conexão com o banco
    |--------------------------------------------------------------------------
    | A conexão é obtida através da classe centralizada Conexao.
    */
    $pdo = Conexao::getInstancia();

    /*
    |--------------------------------------------------------------------------
    | Tabelas analisadas
    |--------------------------------------------------------------------------
    | Lista fixa de tabelas relevantes para o projeto.
    */
    $tables = [
        'usuarios',
        'perfis_usuarios',
        'contas',
        'movimentacoes',
        'orcamentos',
        'itens_orcamento',
        'metas_financeiras',
        'aportes_meta',
        'contas_pagar',
        'contas_receber',
        'movimentacoes_agendadas',
        'categorias',
        'regras_movimentacao'
    ];

    /*
    |--------------------------------------------------------------------------
    | Estrutura do schema
    |--------------------------------------------------------------------------
    | Cada tabela recebe um array com os nomes de suas colunas.
    */
    $schema = [];

    foreach ($tables as $table) {
        $cols = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
        $schema[$table] = array_map(function ($c) {
            return $c['Field'];
        }, $cols);
    }

    /*
    |--------------------------------------------------------------------------
    | Saída em JSON
    |--------------------------------------------------------------------------
    | Retorna a estrutura do banco de forma legível.
    */
    echo json_encode($schema, JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    /*
    |--------------------------------------------------------------------------
    | Tratamento de erro
    |--------------------------------------------------------------------------
    | Caso ocorra falha ao consultar o banco, retorna a mensagem de erro.
    */
    echo "Error: " . $e->getMessage();
}
?>