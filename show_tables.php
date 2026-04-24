<?php
require_once __DIR__ . '/Conexao.php';
try {
    $pdo = Conexao::getInstancia();
    $schema = [];
    foreach (['tipos_conta', 'cartoes'] as $table) {
        $cols = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
        $schema[$table] = array_map(function($c) { return $c['Field']; }, $cols);
    }
    echo json_encode($schema, JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    echo $e->getMessage();
}
