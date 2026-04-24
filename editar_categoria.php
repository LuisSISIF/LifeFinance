<?php
/*
|--------------------------------------------------------------------------
| Edição de categoria
|--------------------------------------------------------------------------
| Este arquivo processa a atualização de uma categoria pertencente
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
    | A conexão PDO é obtida pela classe centralizada Conexao.
    */
    $pdo = Conexao::getInstancia();

    /*
    |--------------------------------------------------------------------------
    | Dados enviados pelo formulário
    |--------------------------------------------------------------------------
    | Todos os campos são normalizados e validados antes do update.
    */
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $id = (int)($_POST['id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $tipo = $_POST['tipo'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | Validação de entrada
    |--------------------------------------------------------------------------
    | Garante que os dados mínimos estejam presentes e que o tipo seja válido.
    */
    if (
        !$uid ||
        !$id ||
        $nome === '' ||
        !in_array($tipo, ['RECEITA', 'DESPESA'], true)
    ) {
        throw new Exception('Dados inválidos.');
    }

    /*
    |--------------------------------------------------------------------------
    | Atualização da categoria
    |--------------------------------------------------------------------------
    | A atualização é limitada ao usuário logado para evitar acesso indevido.
    */
    $stmt = $pdo->prepare("
        UPDATE categorias
        SET nome = :nome, tipo = :tipo
        WHERE id = :id AND id_usuario = :uid
    ");
    $stmt->execute([
        ':nome' => $nome,
        ':tipo' => $tipo,
        ':id' => $id,
        ':uid' => $uid
    ]);

    /*
    |--------------------------------------------------------------------------
    | Redirecionamento após sucesso
    |--------------------------------------------------------------------------
    | Retorna para a listagem com indicador de edição concluída.
    */
    header('Location: categorias.php?edited=1');
    exit;
} catch (Throwable $e) {
    die('Erro ao editar categoria: ' . $e->getMessage());
}
?>