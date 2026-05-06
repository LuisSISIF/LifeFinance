<?php
/*
|--------------------------------------------------------------------------
| Cadastro de conta
|--------------------------------------------------------------------------
| Este arquivo cria uma nova conta para o usuário autenticado.
| Se o tipo de conta for cartão, também cria o registro na tabela de cartões.
*/
session_start();

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: contas.php');
    exit;
}

require_once __DIR__ . '/Conexao.php';

try {
    /*
    |--------------------------------------------------------------------------
    | Conexão com o banco
    |--------------------------------------------------------------------------
    | O processo usa transação porque cria registros relacionados.
    */
    $pdo = Conexao::getInstancia();
    $userId = (int)($_SESSION['user_id'] ?? 1);

    /*
    |--------------------------------------------------------------------------
    | Recebimento e normalização dos dados
    |--------------------------------------------------------------------------
    */
    $nome = trim($_POST['nome'] ?? '');
    $id_tipo_conta = (int)($_POST['id_tipo_conta'] ?? 0);
    $saldoStr = $_POST['saldo'] ?? '0';
    $descricao = trim($_POST['descricao'] ?? '');

    if (empty($nome) || empty($id_tipo_conta)) {
        throw new Exception("Nome e Tipo de conta são obrigatórios.");
    }

    /*
    |--------------------------------------------------------------------------
    | Conversão de valor monetário
    |--------------------------------------------------------------------------
    | Trata valores no formato brasileiro, como 1.250,50.
    */
    $saldoStr = str_replace('.', '', $saldoStr);
    $saldoStr = str_replace(',', '.', $saldoStr);
    $saldo = (float)$saldoStr;

    /*
    |--------------------------------------------------------------------------
    | Identificação do tipo de conta
    |--------------------------------------------------------------------------
    | Verifica se a conta criada é um cartão para processar campos extras.
    */
    $stmt = $pdo->prepare("SELECT categoria FROM tipos_conta WHERE id = ?");
    $stmt->execute([$id_tipo_conta]);
    $tipoConta = $stmt->fetch();

    if (!$tipoConta) {
        throw new Exception("Tipo de conta inválido.");
    }

    $isCartao = ($tipoConta['categoria'] === 'CARTAO');

    /*
    |--------------------------------------------------------------------------
    | Início da transação
    |--------------------------------------------------------------------------
    */
    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | 1. Inserção da conta
    |--------------------------------------------------------------------------
    */
    $stmtConta = $pdo->prepare("
        INSERT INTO contas
        (id_usuario, id_tipo_conta, nome, descricao, codigo_moeda, saldo, ativa, principal, criado_em, atualizado_em)
        VALUES (?, ?, ?, ?, 'BRL', ?, 1, 0, NOW(), NOW())
    ");
    $stmtConta->execute([$userId, $id_tipo_conta, $nome, $descricao, $saldo]);

    $idContaCriada = $pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | 2. Inserção de cartão, se aplicável
    |--------------------------------------------------------------------------
    */
    if ($isCartao) {
        $limiteStr = $_POST['limite'] ?? '0';
        $limiteStr = str_replace('.', '', $limiteStr);
        $limiteStr = str_replace(',', '.', $limiteStr);
        $limite = (float)$limiteStr;

        $bandeira = trim($_POST['bandeira'] ?? '');
        $dia_vencimento = (int)($_POST['dia_vencimento'] ?? 10);
        $dia_fechamento = (int)($_POST['dia_fechamento'] ?? 3);

        $stmtCartao = $pdo->prepare("
            INSERT INTO cartoes
            (id_conta, bandeira, nome_titular, limite, dia_fechamento, dia_vencimento, cartao_virtual, criado_em, atualizado_em)
            VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), NOW())
        ");

        /*
        |--------------------------------------------------------------------------
        | Nome do titular
        |--------------------------------------------------------------------------
        | Se houver perfil cadastrado, usa nome e sobrenome do usuário.
        */
        $stmtPerfil = $pdo->prepare("SELECT nome, sobrenome FROM perfis_usuarios WHERE id_usuario = ?");
        $stmtPerfil->execute([$userId]);
        $perfil = $stmtPerfil->fetch();
        $nomeTitular = $perfil ? trim($perfil['nome'] . ' ' . $perfil['sobrenome']) : $nome;

        $stmtCartao->execute([$idContaCriada, $bandeira, $nomeTitular, $limite, $dia_fechamento, $dia_vencimento]);
    }

    $pdo->commit();
    header('Location: contas.php?msg=success');
    exit;
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Erro ao salvar conta: " . $e->getMessage());
}
?>