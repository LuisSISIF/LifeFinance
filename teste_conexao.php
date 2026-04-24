<?php
require_once __DIR__ . '/Conexao.php';

try {
    $pdo = Conexao::getInstancia();
    echo 'Conexão estabelecida com sucesso!';
} catch (PDOException $e) {
    echo 'Erro na conexão: ' . $e->getMessage();
}