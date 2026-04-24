<?php
session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/Conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'] ?? 1; // Fallback
    
    $tipo = $_POST['tipo'] ?? '';
    $valorRaw = $_POST['valor'] ?? '0';
    $valor = (float) str_replace(',', '.', $valorRaw);
    
    $id_conta = !empty($_POST['id_conta']) ? (int)$_POST['id_conta'] : null;
    $id_conta_destino = !empty($_POST['id_conta_destino']) ? (int)$_POST['id_conta_destino'] : null;
    $id_categoria = !empty($_POST['id_categoria']) ? (int)$_POST['id_categoria'] : null;
    
    $ocorreu_em = $_POST['ocorreu_em'] ?? date('Y-m-d');
    $vence_em = !empty($_POST['vence_em']) ? $_POST['vence_em'] : null;
    
    $descricao = trim($_POST['descricao'] ?? '');
    $observacao = trim($_POST['observacao'] ?? '');
    $status = $_POST['status'] ?? 'PENDENTE';
    $codigo_moeda = $_POST['codigo_moeda'] ?? 'BRL';

    // Validação básica
    if (!$tipo || $valor <= 0 || !$id_conta || !$descricao) {
        die("Preencha todos os campos obrigatórios corretamente.");
    }
    
    if ($tipo === 'TRANSFERENCIA' && !$id_conta_destino) {
        die("Para transferência, informe a conta de destino.");
    }
    
    if ($tipo === 'TRANSFERENCIA' && $id_conta === $id_conta_destino) {
        die("A conta de destino não pode ser igual à de origem.");
    }

    try {
        $pdo = Conexao::getInstancia();
        $pdo->beginTransaction();

        // 1. Inserir na tabela movimentacoes
        $stmt = $pdo->prepare('
            INSERT INTO movimentacoes 
            (id_usuario, id_conta, id_conta_destino, id_categoria, tipo, status, valor, codigo_moeda, descricao, observacao, ocorreu_em, vence_em, criado_em, atualizado_em) 
            VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ');
        $stmt->execute([
            $userId, 
            $id_conta, 
            $id_conta_destino, 
            $id_categoria, 
            $tipo, 
            $status, 
            $valor, 
            $codigo_moeda, 
            $descricao, 
            $observacao, 
            $ocorreu_em, 
            $vence_em
        ]);

        // 2. Atualizar Saldos se estiver PAGO
        if ($status === 'PAGO') {
            if ($tipo === 'RECEITA') {
                $stmtUp = $pdo->prepare('UPDATE contas SET saldo = saldo + ?, saldo_atualizado_em = NOW() WHERE id = ? AND id_usuario = ?');
                $stmtUp->execute([$valor, $id_conta, $userId]);
            } 
            elseif ($tipo === 'DESPESA') {
                $stmtUp = $pdo->prepare('UPDATE contas SET saldo = saldo - ?, saldo_atualizado_em = NOW() WHERE id = ? AND id_usuario = ?');
                $stmtUp->execute([$valor, $id_conta, $userId]);
            } 
            elseif ($tipo === 'TRANSFERENCIA') {
                // Tira da origem
                $stmtOrig = $pdo->prepare('UPDATE contas SET saldo = saldo - ?, saldo_atualizado_em = NOW() WHERE id = ? AND id_usuario = ?');
                $stmtOrig->execute([$valor, $id_conta, $userId]);
                
                // Coloca no destino
                $stmtDest = $pdo->prepare('UPDATE contas SET saldo = saldo + ?, saldo_atualizado_em = NOW() WHERE id = ? AND id_usuario = ?');
                $stmtDest->execute([$valor, $id_conta_destino, $userId]);
            }
            elseif ($tipo === 'AJUSTE') {
                // Se for ajuste, depende se é valor positivo ou negativo, mas aqui trataremos como adição (ajuste para cima). 
                // Se fosse ajuste para baixo, o valor enviado poderia ser negativo.
                $stmtUp = $pdo->prepare('UPDATE contas SET saldo = saldo + ?, saldo_atualizado_em = NOW() WHERE id = ? AND id_usuario = ?');
                $stmtUp->execute([$valor, $id_conta, $userId]);
            }
        }

        $pdo->commit();
        header('Location: dashboard.php?msg=success');
        exit;

    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Erro ao salvar movimentação: " . $e->getMessage());
    }
} else {
    header('Location: dashboard.php');
    exit;
}
