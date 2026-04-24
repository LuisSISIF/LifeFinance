<?php
/*
|--------------------------------------------------------------------------
| Cadastro de movimentação
|--------------------------------------------------------------------------
| Este arquivo recebe os dados do formulário e insere uma nova
| movimentação financeira vinculada ao usuário autenticado.
*/
session_start();

/*
|--------------------------------------------------------------------------
| Controle de acesso
|--------------------------------------------------------------------------
| Apenas usuários autenticados podem criar movimentações.
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
    | A persistência é feita através de PDO com prepared statement.
    */
    $pdo = Conexao::getInstancia();

    /*
    |--------------------------------------------------------------------------
    | Dados básicos
    |--------------------------------------------------------------------------
    | Normaliza os campos recebidos do formulário.
    */
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $tipo = $_POST['tipo'] ?? '';
    $valor = (float)str_replace(',', '.', $_POST['valor'] ?? '0');
    $descricao = trim($_POST['descricao'] ?? '');
    $observacao = trim($_POST['observacao'] ?? '');
    $status = $_POST['status'] ?? 'PAGO';
    $codigo_moeda = $_POST['codigo_moeda'] ?? 'BRL';
    $id_conta = (int)($_POST['id_conta'] ?? 0);
    $id_categoria = !empty($_POST['id_categoria']) ? (int)$_POST['id_categoria'] : null;
    $id_conta_destino = !empty($_POST['id_conta_destino']) ? (int)$_POST['id_conta_destino'] : null;
    $ocorreu_em = $_POST['ocorreu_em'] ?? date('Y-m-d');
    $vence_em = !empty($_POST['vence_em']) ? $_POST['vence_em'] : null;

    /*
    |--------------------------------------------------------------------------
    | Validação dos dados
    |--------------------------------------------------------------------------
    | Garante que os campos essenciais estejam corretos antes do insert.
    */
    if (!$uid || !$tipo || $valor <= 0 || !$descricao || !$id_conta) {
        throw new Exception('Dados inválidos.');
    }

    /*
    |--------------------------------------------------------------------------
    | Regras específicas para transferência
    |--------------------------------------------------------------------------
    | Transferência exige conta destino; outros tipos não a utilizam.
    */
    if ($tipo !== 'TRANSFERENCIA') {
        $id_conta_destino = null;
    }

    if ($tipo === 'TRANSFERENCIA' && !$id_conta_destino) {
        throw new Exception('Informe a conta destino.');
    }

    /*
    |--------------------------------------------------------------------------
    | Inserção da movimentação
    |--------------------------------------------------------------------------
    | Os dados são gravados vinculados ao usuário logado.
    */
    $stmt = $pdo->prepare("
        INSERT INTO movimentacoes
        (id_usuario, tipo, valor, descricao, observacao, status, codigo_moeda, id_conta, id_categoria, id_conta_destino, ocorreu_em, vence_em)
        VALUES
        (:uid, :tipo, :valor, :descricao, :obs, :status, :moeda, :conta, :cat, :destino, :ocorreu, :vence)
    ");

    $stmt->execute([
        ':uid' => $uid,
        ':tipo' => $tipo,
        ':valor' => $valor,
        ':descricao' => $descricao,
        ':obs' => $observacao ?: null,
        ':status' => $status,
        ':moeda' => $codigo_moeda,
        ':conta' => $id_conta,
        ':cat' => $id_categoria,
        ':destino' => $id_conta_destino,
        ':ocorreu' => $ocorreu_em,
        ':vence' => $vence_em
    ]);

    /*
    |--------------------------------------------------------------------------
    | Redirecionamento após sucesso
    |--------------------------------------------------------------------------
    | Retorna para a página de movimentações com mensagem de sucesso.
    */
    header('Location: movimentacoes.php?success=1');
    exit;
} catch (Throwable $e) {
    die('Erro ao salvar movimentação: ' . $e->getMessage());
}
?>