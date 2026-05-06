<?php

require_once '../app/Core/Database.php';
require_once '../app/Models/Meta.php';

class MetasController extends Controller
{
    private $metaModel;

    public function __construct()
    {
        if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
            $this->redirect('/auth/login');
        }
        $this->metaModel = new Meta();
    }

    public function index()
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);

        $metas = $this->metaModel->getAllByUserId($userId);

        $this->view('metas/index', [
            'metas' => $metas
        ]);
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Logic for store/edit will be migrated here
            $this->redirect('/metas');
        }
    }

    public function delete()
    {
        $id = (int)($_GET['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);

        if ($id > 0) {
            $this->metaModel->delete($id, $userId);
        }

        $this->redirect('/metas');
    }
}
