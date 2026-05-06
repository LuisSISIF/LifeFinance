<?php

require_once '../app/Core/Database.php';
require_once '../app/Services/ContasService.php';

class ContasController extends Controller
{
    public function __construct()
    {
        if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
            $this->redirect('/auth/login');
        }
    }

    public function index()
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $pdo = Database::getInstancia();

        $dados = ContasService::getDadosPaginaContas($pdo, $userId);
        $tiposConta = ContasService::getTiposConta($pdo);

        $saldoTotalStr = 'R$ ' . number_format($dados['saldoConsolidado'], 2, ',', '.');
        $metaVal = $dados['painelLateral']['metaSaldo']['valor_meta'] ?? 0;
        $metaFalta = 'R$ ' . number_format($dados['painelLateral']['metaSaldo']['falta'] ?? 0, 2, ',', '.');

        $this->view('contas/index', [
            'dados' => $dados,
            'tiposConta' => $tiposConta,
            'saldoTotalStr' => $saldoTotalStr,
            'metaVal' => $metaVal,
            'metaFalta' => $metaFalta
        ]);
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = (int)($_SESSION['user_id'] ?? 1);
            $pdo = Database::getInstancia();

            $nome = trim($_POST['nome'] ?? '');
            $id_tipo_conta = (int)($_POST['id_tipo_conta'] ?? 0);
            $saldoStr = $_POST['saldo'] ?? '0';
            $descricao = trim($_POST['descricao'] ?? '');

            if (!empty($nome) && !empty($id_tipo_conta)) {
                $saldoStr = str_replace('.', '', $saldoStr);
                $saldoStr = str_replace(',', '.', $saldoStr);
                $saldo = (float)$saldoStr;

                $stmt = $pdo->prepare("SELECT categoria FROM tipos_conta WHERE id = ?");
                $stmt->execute([$id_tipo_conta]);
                $tipoConta = $stmt->fetch();

                if ($tipoConta) {
                    $isCartao = ($tipoConta['categoria'] === 'CARTAO');

                    try {
                        $pdo->beginTransaction();

                        $stmtConta = $pdo->prepare("
                            INSERT INTO contas
                            (id_usuario, id_tipo_conta, nome, descricao, codigo_moeda, saldo, ativa, principal, criado_em, atualizado_em)
                            VALUES (?, ?, ?, ?, 'BRL', ?, 1, 0, NOW(), NOW())
                        ");
                        $stmtConta->execute([$userId, $id_tipo_conta, $nome, $descricao, $saldo]);

                        $idContaCriada = $pdo->lastInsertId();

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

                            $stmtPerfil = $pdo->prepare("SELECT nome, sobrenome FROM perfis_usuarios WHERE id_usuario = ?");
                            $stmtPerfil->execute([$userId]);
                            $perfil = $stmtPerfil->fetch();
                            $nomeTitular = $perfil ? trim($perfil['nome'] . ' ' . $perfil['sobrenome']) : $nome;

                            $stmtCartao->execute([$idContaCriada, $bandeira, $nomeTitular, $limite, $dia_fechamento, $dia_vencimento]);
                        }

                        $pdo->commit();
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                    }
                }
            }
        }
        $this->redirect('/contas');
    }

    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = (int)($_SESSION['user_id'] ?? 1);
            $pdo = Database::getInstancia();

            $id = (int)($_POST['id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            $id_tipo_conta = (int)($_POST['id_tipo_conta'] ?? 0);
            $saldoStr = $_POST['saldo'] ?? '0';
            $descricao = trim($_POST['descricao'] ?? '');

            if ($id && !empty($nome) && !empty($id_tipo_conta)) {
                $saldoStr = str_replace('.', '', $saldoStr);
                $saldoStr = str_replace(',', '.', $saldoStr);
                $saldo = (float)$saldoStr;

                $stmt = $pdo->prepare("UPDATE contas SET nome = ?, id_tipo_conta = ?, saldo = ?, descricao = ?, atualizado_em = NOW() WHERE id = ? AND id_usuario = ?");
                $stmt->execute([$nome, $id_tipo_conta, $saldo, $descricao, $id, $userId]);
            }
        }
        $this->redirect('/contas');
    }

    public function delete()
    {
        if (isset($_GET['id'])) {
            $userId = (int)($_SESSION['user_id'] ?? 1);
            $id = (int)$_GET['id'];
            $pdo = Database::getInstancia();

            $stmt = $pdo->prepare("DELETE FROM contas WHERE id = ? AND id_usuario = ?");
            $stmt->execute([$id, $userId]);
        }
        $this->redirect('/contas');
    }
}
