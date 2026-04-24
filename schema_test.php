<?php
require_once __DIR__ . '/Conexao.php';
try {
    $pdo = Conexao::getInstancia();
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $schema = [];
    foreach ($tables as $table) {
        $cols = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
        $schema[$table] = $cols;
    }
    echo json_encode($schema, JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage();
}
