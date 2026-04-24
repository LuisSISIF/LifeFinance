<?php
/*
|--------------------------------------------------------------------------
| Inspeção automática do schema
|--------------------------------------------------------------------------
| Este script consulta todas as tabelas existentes no banco e retorna
| suas estruturas completas em formato JSON.
| Útil para diagnóstico, documentação e validação de schema.
*/
require_once __DIR__ . '/Conexao.php';

try {
    /*
    |--------------------------------------------------------------------------
    | Conexão com o banco
    |--------------------------------------------------------------------------
    | A leitura do schema é feita via PDO.
    */
    $pdo = Conexao::getInstancia();

    /*
    |--------------------------------------------------------------------------
    | Lista de tabelas
    |--------------------------------------------------------------------------
    | SHOW TABLES retorna todas as tabelas disponíveis no banco atual.
    */
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    /*
    |--------------------------------------------------------------------------
    | Estrutura do schema
    |--------------------------------------------------------------------------
    | Para cada tabela, o DESCRIBE retorna metadados das colunas.
    */
    $schema = [];

    foreach ($tables as $table) {
        $cols = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
        $schema[$table] = $cols;
    }

    /*
    |--------------------------------------------------------------------------
    | Saída em JSON
    |--------------------------------------------------------------------------
    | Retorna os dados formatados para leitura ou integração.
    */
    echo json_encode($schema, JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage();
}
?>