<?php

require_once '../app/Core/Database.php';
require_once '../app/Services/DashboardService.php';

class DashboardController extends Controller
{
    public function index()
    {
        // Verifica se está autenticado
        if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
            $this->redirect('/auth/login');
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        $pdo = Database::getInstancia();

        $dados = DashboardService::getDashboardData($pdo, $userId);
        $filtrosModal = DashboardService::getFiltrosModal($pdo, $userId);

        // Prepara os dados para a view
        $nomeUsuario = $dados['nomeUsuario'] ?? 'Usuário';
        $saldoTotal = 'R$ ' . number_format((float)($dados['saldoTotal'] ?? 0), 2, ',', '.');
        $receitasMes = 'R$ ' . number_format((float)($dados['receitasMes'] ?? 0), 2, ',', '.');
        $despesasMes = 'R$ ' . number_format((float)($dados['despesasMes'] ?? 0), 2, ',', '.');
        $orcamentoMes = $dados['orcamentoMes'] ?? 0;
        $metaMes = $dados['metaMesProgresso'] ?? 0;

        $viewData = [
            'dados' => $dados,
            'filtrosModal' => $filtrosModal,
            'nomeUsuario' => $nomeUsuario,
            'saldoTotal' => $saldoTotal,
            'receitasMes' => $receitasMes,
            'despesasMes' => $despesasMes,
            'orcamentoMes' => $orcamentoMes,
            'metaMes' => $metaMes
        ];

        $this->view('dashboard/index', $viewData);
    }
}
