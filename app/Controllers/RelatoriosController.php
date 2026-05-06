<?php

require_once '../app/Core/Database.php';
require_once '../app/Models/Relatorio.php';

class RelatoriosController extends Controller
{
    private $relatorioModel;

    public function __construct()
    {
        if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
            $this->redirect('/auth/login');
        }
        $this->relatorioModel = new Relatorio();
    }

    public function index()
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);

        $mes = (int)($_GET['mes'] ?? date('n'));
        $ano = (int)($_GET['ano'] ?? date('Y'));

        if ($mes < 1 || $mes > 12) $mes = (int)date('n');
        if ($ano < 2000 || $ano > 2100) $ano = (int)date('Y');

        $inicio = sprintf('%04d-%02d-01', $ano, $mes);
        $fim = date('Y-m-t', strtotime($inicio));
        $inicio12 = date('Y-m-01', strtotime($fim . ' -11 months'));

        $kpi = $this->relatorioModel->getKPIs($userId, $inicio, $fim);
        $statusResumo = $this->relatorioModel->getStatusResumo($userId, $inicio, $fim);
        $gastosCategoria = $this->relatorioModel->getGastosCategoria($userId, $inicio, $fim);
        $rowsMes = $this->relatorioModel->getMesesFluxo($userId, $inicio12, $fim);

        $labels = [];
        $receitas = [];
        $despesas = [];
        $saldoArr = [];
        $cursor = new DateTime($inicio12);

        for ($i = 0; $i < 12; $i++) {
            $ym = $cursor->format('Y-m');
            $labels[] = $cursor->format('M/Y');

            $found = null;
            foreach ($rowsMes as $r) {
                if ($r['ym'] === $ym) {
                    $found = $r;
                    break;
                }
            }

            $rec = (float)($found['receitas'] ?? 0);
            $des = (float)($found['despesas'] ?? 0);

            $receitas[] = $rec;
            $despesas[] = $des;
            $saldoArr[] = $rec - $des;

            $cursor->modify('+1 month');
        }

        $topMov = $this->relatorioModel->getMaioresMovimentacoes($userId, $inicio, $fim);
        $fluxoDia = $this->relatorioModel->getFluxoDiario($userId, $inicio, $fim);
        $tipos = $this->relatorioModel->getDistribuicaoTipos($userId, $inicio, $fim);
        $alertas = $this->relatorioModel->getAlertas($userId, $inicio, $fim);

        $total = (float)($kpi['total'] ?? 0);
        $receitaTotal = (float)($kpi['receitas'] ?? 0);
        $despesaTotal = (float)($kpi['despesas'] ?? 0);
        $saldoMes = $receitaTotal - $despesaTotal;
        $margem = $receitaTotal > 0 ? round(($saldoMes / $receitaTotal) * 100, 1) : 0;
        $gastoMedio = $total > 0 ? ($despesaTotal / $total) : 0;
        $pagas = (int)($statusResumo['pagas'] ?? 0);
        $pendentes = (int)($statusResumo['pendentes'] ?? 0);
        $transferTotal = (float)($kpi['transferencias'] ?? 0);

        $this->view('relatorios/index', [
            'mes' => $mes,
            'ano' => $ano,
            'total' => $total,
            'receitaTotal' => $receitaTotal,
            'despesaTotal' => $despesaTotal,
            'saldoMes' => $saldoMes,
            'margem' => $margem,
            'gastoMedio' => $gastoMedio,
            'pagas' => $pagas,
            'pendentes' => $pendentes,
            'transferTotal' => $transferTotal,
            'labels' => $labels,
            'receitas' => $receitas,
            'despesas' => $despesas,
            'saldoArr' => $saldoArr,
            'gastosCategoria' => $gastosCategoria,
            'topMov' => $topMov,
            'fluxoDia' => $fluxoDia,
            'tipos' => $tipos,
            'alertas' => $alertas
        ]);
    }
}
