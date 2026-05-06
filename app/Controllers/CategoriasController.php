<?php

require_once '../app/Core/Database.php';

class CategoriasController extends Controller
{
    private $categoriaModel;

    public function __construct()
    {
        if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
            $this->redirect('/auth/login');
        }
        $this->categoriaModel = new Categoria();
    }

    public function index()
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $categorias = $this->categoriaModel->getAllByUserId($userId);
        $stats = $this->categoriaModel->getStatsByUserId($userId);

        $this->view('categorias/index', [
            'categorias' => $categorias,
            'stats' => $stats
        ]);
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = (int)($_SESSION['user_id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            $tipo = $_POST['tipo'] ?? '';

            if ($userId && $nome !== '' && in_array($tipo, ['RECEITA', 'DESPESA'], true)) {
                $this->categoriaModel->create($userId, $nome, $tipo);
            }
        }
        $this->redirect('/categorias');
    }

    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = (int)($_SESSION['user_id'] ?? 0);
            $id = (int)($_POST['id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            $tipo = $_POST['tipo'] ?? '';

            if ($userId && $id && $nome !== '' && in_array($tipo, ['RECEITA', 'DESPESA'], true)) {
                $this->categoriaModel->update($id, $userId, $nome, $tipo);
            }
        }
        $this->redirect('/categorias');
    }

    public function delete()
    {
        if (isset($_GET['id'])) {
            $userId = (int)($_SESSION['user_id'] ?? 0);
            $id = (int)$_GET['id'];
            try {
                $this->categoriaModel->delete($id, $userId);
            } catch (Exception $e) {
                // Em um cenário ideal, retornaria um erro flash na sessão
            }
        }
        $this->redirect('/categorias');
    }

    public function porTipo()
    {
        $tipo = strtoupper(trim($_GET['tipo'] ?? ''));
        if (!in_array($tipo, ['RECEITA', 'DESPESA', 'AJUSTE'])) {
            exit;
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        $pdo = Database::getInstancia();
        $stmt = $pdo->prepare("
            SELECT id, nome FROM categorias 
            WHERE id_usuario = ? AND tipo = ? 
            ORDER BY nome ASC
        ");
        $stmt->execute([$userId, $tipo]);
        $cats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($cats as $c) {
            echo '<option value="' . (int)$c['id'] . '">' . htmlspecialchars($c['nome']) . '</option>';
        }
    }
}
