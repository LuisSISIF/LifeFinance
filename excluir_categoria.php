<?php
/*
|--------------------------------------------------------------------------
| Exclusão de categoria
|--------------------------------------------------------------------------
| Este arquivo remove uma categoria pertencente ao usuário autenticado.
| Antes da exclusão, o sistema verifica se existem movimentações vinculadas.
*/
session_start();

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/Conexao.php';

try {
    /*
    |--------------------------------------------------------------------------
    | Conexão com o banco
    |--------------------------------------------------------------------------
    | A exclusão é processada via PDO com consulta preparada.
    */
    $pdo = Conexao::getInstancia();

    /*
    |--------------------------------------------------------------------------
    | Dados de sessão e parâmetro da requisição
    |--------------------------------------------------------------------------
    | O usuário e o ID da categoria são validados antes de prosseguir.
    */
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $id = (int)($_GET['id'] ?? 0);

    if (!$uid || !$id) {
        throw new Exception('ID inválido.');
    }

    /*
    |--------------------------------------------------------------------------
    | Validação de integridade
    |--------------------------------------------------------------------------
    | Impede a exclusão de categorias que já estejam sendo usadas
    | em movimentações financeiras.
    */
    $check = $pdo->prepare("
        SELECT COUNT(*)
        FROM movimentacoes
        WHERE id_categoria = :id AND id_usuario = :uid
    ");
    $check->execute([
        ':id' => $id,
        ':uid' => $uid
    ]);

    if ((int)$check->fetchColumn() > 0) {
        throw new Exception('Não é possível excluir categoria vinculada a movimentações.');
    }

    /*
    |--------------------------------------------------------------------------
    | Exclusão da categoria
    |--------------------------------------------------------------------------
    | A remoção é limitada ao usuário logado para evitar acesso indevido.
    */
    $stmt = $pdo->prepare("
        DELETE FROM categorias
        WHERE id = :id AND id_usuario = :uid
    ");
    $stmt->execute([
        ':id' => $id,
        ':uid' => $uid
    ]);

    /*
    |--------------------------------------------------------------------------
    | Redirecionamento após sucesso
    |--------------------------------------------------------------------------
    | Retorna à listagem com indicação de exclusão concluída.
    */
    header('Location: categorias.php?deleted=1');
    exit;
} catch (Throwable $e) {
    die('Erro ao excluir categoria: ' . $e->getMessage());
}
?>