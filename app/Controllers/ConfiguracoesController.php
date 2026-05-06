<?php

require_once '../app/Core/Database.php';
require_once '../app/Models/User.php';

class ConfiguracoesController extends Controller
{
    private $userModel;

    public function __construct()
    {
        if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
            $this->redirect('/auth/login');
        }
        $this->userModel = new User();
    }

    public function index()
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $usuario = $this->userModel->getById($userId);

        if (!$usuario) {
            die("Usuário não encontrado.");
        }

        $db = Database::getInstancia();
        $stmt = $db->prepare("
            SELECT
                COUNT(*) total,
                SUM(CASE WHEN tipo='RECEITA' THEN valor ELSE 0 END) receitas,
                SUM(CASE WHEN tipo='DESPESA' THEN valor ELSE 0 END) despesas
            FROM movimentacoes
            WHERE id_usuario = :uid
        ");
        $stmt->execute([':uid' => $userId]);
        $resumo = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'receitas'=>0,'despesas'=>0];

        $this->view('configuracoes/index', [
            'usuario' => $usuario,
            'resumo' => $resumo
        ]);
    }

    public function updateProfile()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = (int)($_SESSION['user_id'] ?? 0);
            $data = [
                'email' => trim($_POST['email'] ?? ''),
                'telefone' => trim($_POST['telefone'] ?? ''),
                'tema' => trim($_POST['tema'] ?? 'claro'),
                'moeda_padrao' => strtoupper(trim($_POST['moeda_padrao'] ?? 'BRL')),
                'idioma' => trim($_POST['idioma'] ?? 'pt-BR'),
                'notificacoes_email' => isset($_POST['notificacoes_email']) ? 1 : 0,
                'notificacoes_app' => isset($_POST['notificacoes_app']) ? 1 : 0,
                'metas_ativas' => isset($_POST['metas_ativas']) ? 1 : 0,
            ];

            $this->userModel->updateProfile($userId, $data);
            $this->redirect('/configuracoes?salvo=1');
        }
    }

    public function updatePassword()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = (int)($_SESSION['user_id'] ?? 0);
            $usuario = $this->userModel->getById($userId);

            $senhaAtual = $_POST['senha_atual'] ?? '';
            $novaSenha = $_POST['nova_senha'] ?? '';
            $confirmaSenha = $_POST['confirmar_senha'] ?? '';

            if ($novaSenha !== $confirmaSenha || strlen($novaSenha) < 6) {
                $this->redirect('/configuracoes?error=senha_invalida');
                return;
            }

            $senhaBanco = $usuario['senha'] ?? ($usuario['senha_hash'] ?? '');
            $ok = password_verify($senhaAtual, $senhaBanco) || hash('sha256', $senhaAtual) === $senhaBanco;

            if (!$ok) {
                $this->redirect('/configuracoes?error=senha_incorreta');
                return;
            }

            $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
            $coluna = array_key_exists('senha', $usuario) ? 'senha' : 'senha_hash';

            $this->userModel->updatePassword($userId, $hash, $coluna);
            $this->redirect('/configuracoes?senha=1');
        }
    }
}
