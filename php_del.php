<?php
/*
|--------------------------------------------------------------------------
| Exclusão de movimentação
|--------------------------------------------------------------------------
| Este arquivo remove uma movimentação financeira do usuário autenticado.
| A operação é limitada ao proprietário do registro.
*/
session_start();

/*
|--------------------------------------------------------------------------
| Controle de acesso
|--------------------------------------------------------------------------
| Apenas usuários autenticados podem executar a exclusão.
*/
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
    | A remoção é feita via PDO com prepared statement.
    */
    $pdo = Conexao::getInstancia();

    /*
    |--------------------------------------------------------------------------
    | Validação de parâmetros
    |--------------------------------------------------------------------------
    | Garante que o usuário e o ID da movimentação sejam válidos.
    */
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $id = (int)($_GET['id'] ?? 0);

    if (!$uid || !$id) {
        throw new Exception('ID inválido.');
    }

    /*
    |--------------------------------------------------------------------------
    | Exclusão da movimentação
    |--------------------------------------------------------------------------
    | A consulta é restrita ao usuário logado para evitar exclusão indevida.
    */
    $stmt = $pdo->prepare("
        DELETE FROM movimentacoes
        WHERE id = :id AND id_usuario = :uid
    ");
    $stmt->execute([
        ':id' => $id,
        ':uid' => $uid
    ]);

    /*
    |--------------------------------------------------------------------------
    | Redirecionamento após exclusão
    |--------------------------------------------------------------------------
    | Retorna à página de movimentações com indicador de sucesso.
    */
    header('Location: movimentacoes.php?deleted=1');
    exit;
} catch (Throwable $e) {
    die('Erro ao excluir movimentação: ' . $e->getMessage());
}
?>