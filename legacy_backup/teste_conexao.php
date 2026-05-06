<?php
/*
|--------------------------------------------------------------------------
| Teste de conexão com o banco
|--------------------------------------------------------------------------
| Este script apenas verifica se a conexão PDO pode ser estabelecida com sucesso.
*/
require_once __DIR__ . '/Conexao.php';

try {
    /*
    |--------------------------------------------------------------------------
    | Obtenção da instância de conexão
    |--------------------------------------------------------------------------
    | Se a conexão estiver correta, a instância será retornada sem erro.
    */
    $pdo = Conexao::getInstancia();

    echo 'Conexão estabelecida com sucesso!';
} catch (PDOException $e) {
    /*
    |--------------------------------------------------------------------------
    | Tratamento de erro
    |--------------------------------------------------------------------------
    | Em ambiente real, o ideal é registrar o erro em log e não exibi-lo diretamente.
    */
    echo 'Erro na conexão: ' . $e->getMessage();
}
?>