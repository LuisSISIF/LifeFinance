<?php
/*
|--------------------------------------------------------------------------
| Inspeção de schema
|--------------------------------------------------------------------------
| Este script consulta a estrutura de tabelas específicas do banco
| e retorna apenas os nomes das colunas em formato JSON.
*/
require_once __DIR__ . '/Conexao.php';

try {
    /*
    |--------------------------------------------------------------------------
    | Conexão com o banco
    |--------------------------------------------------------------------------
    */
    $pdo = Conexao::getInstancia();

    /*
    |--------------------------------------------------------------------------
    | Tabelas alvo
    |--------------------------------------------------------------------------
    | Aqui estão as tabelas que serão inspecionadas.
    */
    $schema = [];

    foreach (['tipos_conta', 'cartoes'] as $table) {
        $cols = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
        $schema[$table] = array_map(function ($c) {
            return $c['Field'];
        }, $cols);
    }

    /*
    |--------------------------------------------------------------------------
    | Saída em JSON
    |--------------------------------------------------------------------------
    */
    echo json_encode($schema, JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    echo $e->getMessage();
}
?>