<?php
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
    $pdo = Conexao::getInstancia();
    $userId = $_SESSION['user_id'] ?? 1;

    // Recebe e limpa os dados
    $nome = trim($_POST['nome'] ?? '');
    $id_tipo_conta = (int)($_POST['id_tipo_conta'] ?? 0);
    $saldoStr = $_POST['saldo'] ?? '0';
    $descricao = trim($_POST['descricao'] ?? '');

    if (empty($nome) || empty($id_tipo_conta)) {
        throw new Exception("Nome e Tipo de conta são obrigatórios.");
    }

    // Trata valores monetários brasileiros (ex: 1.250,50 -> 1250.50)
    $saldoStr = str_replace('.', '', $saldoStr);
    $saldoStr = str_replace(',', '.', $saldoStr);
    $saldo = (float) $saldoStr;

    // Verifica se a conta a ser criada é um cartão de crédito para salvar detalhes extras
    $stmt = $pdo->prepare("SELECT categoria FROM tipos_conta WHERE id = ?");
    $stmt->execute([$id_tipo_conta]);
    $tipoConta = $stmt->fetch();
    if (!$tipoConta) {
        throw new Exception("Tipo de conta inválido.");
    }
    $isCartao = ($tipoConta['categoria'] === 'CARTAO');

    $pdo->beginTransaction();

    // 1. Inserir a Conta
    $stmtConta = $pdo->prepare("
        INSERT INTO contas (id_usuario, id_tipo_conta, nome, descricao, codigo_moeda, saldo, ativa, principal, criado_em, atualizado_em) 
        VALUES (?, ?, ?, ?, 'BRL', ?, 1, 0, NOW(), NOW())
    ");
    $stmtConta->execute([$userId, $id_tipo_conta, $nome, $descricao, $saldo]);
    
    $idContaCriada = $pdo->lastInsertId();

    // 2. Se for cartão, inserir na tabela de cartões
    if ($isCartao) {
        $limiteStr = $_POST['limite'] ?? '0';
        $limiteStr = str_replace('.', '', $limiteStr);
        $limiteStr = str_replace(',', '.', $limiteStr);
        $limite = (float) $limiteStr;

        $bandeira = trim($_POST['bandeira'] ?? '');
        $dia_vencimento = (int)($_POST['dia_vencimento'] ?? 10);
        $dia_fechamento = (int)($_POST['dia_fechamento'] ?? 3);

        $stmtCartao = $pdo->prepare("
            INSERT INTO cartoes (id_conta, bandeira, nome_titular, limite, dia_fechamento, dia_vencimento, cartao_virtual, criado_em, atualizado_em) 
            VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), NOW())
        ");
        
        // Pega o nome do usuário para 'nome_titular' por padrão
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
    // Para simplificar, direciona de volta com erro
    die("Erro ao salvar conta: " . $e->getMessage());
}
