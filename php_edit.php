<?php
/*
|--------------------------------------------------------------------------
| Edição de movimentação
|--------------------------------------------------------------------------
| Este arquivo atualiza uma movimentação financeira pertencente
| ao usuário autenticado.
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
    | A atualização é feita via PDO com consulta preparada.
    */
    $pdo = Conexao::getInstancia();

    /*
    |--------------------------------------------------------------------------
    | Identificação do usuário e do registro
    |--------------------------------------------------------------------------
    | O update é restrito ao usuário logado.
    */
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $id = (int)($_POST['id'] ?? 0);

    if (!$uid || !$id) {
        throw new Exception('ID inválido.');
    }

    /*
    |--------------------------------------------------------------------------
    | Atualização da movimentação
    |--------------------------------------------------------------------------
    | Todos os campos relevantes são persistidos na tabela.
    */
    $stmt = $pdo->prepare("
        UPDATE movimentacoes
        SET
            tipo = :tipo,
            valor = :valor,
            descricao = :descricao,
            observacao = :obs,
            status = :status,
            codigo_moeda = :moeda,
            id_conta = :conta,
            id_categoria = :cat,
            id_conta_destino = :destino,
            ocorreu_em = :ocorreu,
            vence_em = :vence
        WHERE id = :id AND id_usuario = :uid
    ");

    $stmt->execute([
        ':tipo' => $_POST['tipo'] ?? 'RECEITA',
        ':valor' => (float)str_replace(',', '.', $_POST['valor'] ?? '0'),
        ':descricao' => trim($_POST['descricao'] ?? ''),
        ':obs' => trim($_POST['observacao'] ?? '') ?: null,
        ':status' => $_POST['status'] ?? 'PAGO',
        ':moeda' => $_POST['codigo_moeda'] ?? 'BRL',
        ':conta' => (int)($_POST['id_conta'] ?? 0),
        ':cat' => !empty($_POST['id_categoria']) ? (int)$_POST['id_categoria'] : null,
        ':destino' => !empty($_POST['id_conta_destino']) ? (int)$_POST['id_conta_destino'] : null,
        ':ocorreu' => $_POST['ocorreu_em'] ?? date('Y-m-d'),
        ':vence' => !empty($_POST['vence_em']) ? $_POST['vence_em'] : null,
        ':id' => $id,
        ':uid' => $uid
    ]);

    /*
    |--------------------------------------------------------------------------
    | Redirecionamento após sucesso
    |--------------------------------------------------------------------------
    | Retorna para a listagem com indicação de edição concluída.
    */
    header('Location: movimentacoes.php?edited=1');
    exit;
} catch (Throwable $e) {
    die('Erro ao editar movimentação: ' . $e->getMessage());
}
?>