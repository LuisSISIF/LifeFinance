<?php

require_once '../app/Core/Database.php';

class MovimentacoesController extends Controller
{
    private $movimentacaoModel;
    private $db;

    public function __construct()
    {
        if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
            $this->redirect('/auth/login');
        }
        $this->movimentacaoModel = new Movimentacao();
        $this->db = Database::getInstancia();
    }

    public function index()
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);

        // Fetch Contas and Categorias for the modal
        $contaStmt = $this->db->prepare("SELECT id, nome FROM contas WHERE id_usuario = :uid ORDER BY nome ASC");
        $contaStmt->execute([':uid' => $userId]);
        $contas = $contaStmt->fetchAll(PDO::FETCH_ASSOC);

        $catStmt = $this->db->prepare("SELECT id, nome, tipo FROM categorias WHERE id_usuario = :uid ORDER BY nome ASC");
        $catStmt->execute([':uid' => $userId]);
        $categorias = $catStmt->fetchAll(PDO::FETCH_ASSOC);

        $dados = $this->movimentacaoModel->getStatsByUserId($userId);
        $movimentacoes = $this->movimentacaoModel->getRecentByUserId($userId);

        $saldo = (float)($dados['receitas'] ?? 0) - (float)($dados['despesas'] ?? 0);

        $this->view('movimentacoes/index', [
            'dados' => $dados,
            'movimentacoes' => $movimentacoes,
            'contas' => $contas,
            'categorias' => $categorias,
            'saldo' => $saldo
        ]);
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = (int)($_SESSION['user_id'] ?? 1);
            $pdo = Database::getInstancia();

            $tipo = $_POST['tipo'] ?? '';
            $valorRaw = $_POST['valor'] ?? '0';
            $valor = (float)str_replace(',', '.', $valorRaw);

            $id_conta = !empty($_POST['id_conta']) ? (int)$_POST['id_conta'] : null;
            $id_conta_destino = !empty($_POST['id_conta_destino']) ? (int)$_POST['id_conta_destino'] : null;
            $id_categoria = !empty($_POST['id_categoria']) ? (int)$_POST['id_categoria'] : null;

            $ocorreu_em = $_POST['ocorreu_em'] ?? date('Y-m-d');
            $vence_em = !empty($_POST['vence_em']) ? $_POST['vence_em'] : null;

            $descricao = trim($_POST['descricao'] ?? '');
            $observacao = trim($_POST['observacao'] ?? '');
            $status = $_POST['status'] ?? 'PENDENTE';
            $codigo_moeda = $_POST['codigo_moeda'] ?? 'BRL';

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
                $pdo->beginTransaction();

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

                if ($status === 'PAGO') {
                    if ($tipo === 'RECEITA') {
                        $stmtUp = $pdo->prepare('UPDATE contas SET saldo = saldo + ?, saldo_atualizado_em = NOW() WHERE id = ? AND id_usuario = ?');
                        $stmtUp->execute([$valor, $id_conta, $userId]);
                    } elseif ($tipo === 'DESPESA') {
                        $stmtUp = $pdo->prepare('UPDATE contas SET saldo = saldo - ?, saldo_atualizado_em = NOW() WHERE id = ? AND id_usuario = ?');
                        $stmtUp->execute([$valor, $id_conta, $userId]);
                    } elseif ($tipo === 'TRANSFERENCIA') {
                        $stmtOrig = $pdo->prepare('UPDATE contas SET saldo = saldo - ?, saldo_atualizado_em = NOW() WHERE id = ? AND id_usuario = ?');
                        $stmtOrig->execute([$valor, $id_conta, $userId]);

                        $stmtDest = $pdo->prepare('UPDATE contas SET saldo = saldo + ?, saldo_atualizado_em = NOW() WHERE id = ? AND id_usuario = ?');
                        $stmtDest->execute([$valor, $id_conta_destino, $userId]);
                    } elseif ($tipo === 'AJUSTE') {
                        $stmtUp = $pdo->prepare('UPDATE contas SET saldo = saldo + ?, saldo_atualizado_em = NOW() WHERE id = ? AND id_usuario = ?');
                        $stmtUp->execute([$valor, $id_conta, $userId]);
                    }
                }

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }
        }
        $this->redirect('/movimentacoes');
    }
    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = (int)($_SESSION['user_id'] ?? 1);
            $pdo = Database::getInstancia();

            $id = (int)($_POST['id'] ?? 0);
            $tipo = $_POST['tipo'] ?? '';
            $valorRaw = $_POST['valor'] ?? '0';
            $valor = (float)str_replace(',', '.', $valorRaw);

            $id_conta = !empty($_POST['id_conta']) ? (int)$_POST['id_conta'] : null;
            $id_conta_destino = !empty($_POST['id_conta_destino']) ? (int)$_POST['id_conta_destino'] : null;
            $id_categoria = !empty($_POST['id_categoria']) ? (int)$_POST['id_categoria'] : null;

            $ocorreu_em = $_POST['ocorreu_em'] ?? date('Y-m-d');
            $vence_em = !empty($_POST['vence_em']) ? $_POST['vence_em'] : null;

            $descricao = trim($_POST['descricao'] ?? '');
            $observacao = trim($_POST['observacao'] ?? '');
            $status = $_POST['status'] ?? 'PENDENTE';
            $codigo_moeda = $_POST['codigo_moeda'] ?? 'BRL';

            if ($id && $tipo && $valor > 0 && $id_conta && $descricao) {
                $stmt = $pdo->prepare('
                    UPDATE movimentacoes
                    SET id_conta = ?, id_conta_destino = ?, id_categoria = ?, tipo = ?, status = ?, valor = ?, codigo_moeda = ?, descricao = ?, observacao = ?, ocorreu_em = ?, vence_em = ?, atualizado_em = NOW()
                    WHERE id = ? AND id_usuario = ?
                ');
                $stmt->execute([
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
                    $vence_em,
                    $id,
                    $userId
                ]);
            }
        }
        $this->redirect('/movimentacoes');
    }

    public function delete()
    {
        if (isset($_GET['id'])) {
            $userId = (int)($_SESSION['user_id'] ?? 1);
            $id = (int)$_GET['id'];
            $pdo = Database::getInstancia();

            $stmt = $pdo->prepare("DELETE FROM movimentacoes WHERE id = ? AND id_usuario = ?");
            $stmt->execute([$id, $userId]);
        }
        $this->redirect('/movimentacoes');
    }
}
