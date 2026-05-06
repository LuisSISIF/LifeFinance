<?php
/*
|--------------------------------------------------------------------------
| Cadastro de categoria
|--------------------------------------------------------------------------
| Este arquivo cria uma nova categoria vinculada ao usuário autenticado.
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
    | O insert é realizado com PDO e consulta preparada.
    */
    $pdo = Conexao::getInstancia();

    /*
    |--------------------------------------------------------------------------
    | Dados do usuário e do formulário
    |--------------------------------------------------------------------------
    | Campos mínimos necessários para salvar a categoria.
    */
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $tipo = $_POST['tipo'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | Validação de entrada
    |--------------------------------------------------------------------------
    | Garante que o nome não esteja vazio e que o tipo seja válido.
    */
    if (!$uid || $nome === '' || !in_array($tipo, ['RECEITA', 'DESPESA'], true)) {
        throw new Exception('Dados inválidos.');
    }

    /*
    |--------------------------------------------------------------------------
    | Inserção da categoria
    |--------------------------------------------------------------------------
    | A categoria é gravada vinculada ao usuário logado.
    */
    $stmt = $pdo->prepare("
        INSERT INTO categorias (id_usuario, nome, tipo)
        VALUES (:uid, :nome, :tipo)
    ");
    $stmt->execute([
        ':uid' => $uid,
        ':nome' => $nome,
        ':tipo' => $tipo
    ]);

    /*
    |--------------------------------------------------------------------------
    | Redirecionamento após sucesso
    |--------------------------------------------------------------------------
    | Retorna para a listagem com mensagem de confirmação.
    */
    header('Location: categorias.php?success=1');
    exit;
} catch (Throwable $e) {
    die('Erro ao salvar categoria: ' . $e->getMessage());
}
?>