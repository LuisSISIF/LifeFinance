<?php
/*
|--------------------------------------------------------------------------
| Página de relatórios
|--------------------------------------------------------------------------
| Este arquivo consolida indicadores e gráficos financeiros por período.
| A tela permite análise mensal e visão histórica dos últimos 12 meses.
*/
session_start();

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/Conexao.php';

function money($v) {
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}

function pct($a, $b) {
    return $b > 0 ? round(($a / $b) * 100, 1) : 0;
}

function fmtDate($v) {
    if (empty($v)) return '-';
    try {
        return (new DateTime($v))->format('d/m/Y');
    } catch(Throwable $e) {
        return '-';
    }
}

try {
    /*
    |--------------------------------------------------------------------------
    | Conexão e usuário logado
    |--------------------------------------------------------------------------
    */
    $pdo = Conexao::getInstancia();
    $userId = (int)($_SESSION['user_id'] ?? 0);

    if ($userId <= 0) {
        throw new Exception('Usuário não identificado.');
    }

    /*
    |--------------------------------------------------------------------------
    | Período selecionado
    |--------------------------------------------------------------------------
    | Se não houver filtro na URL, usa mês e ano atuais.
    */
    $mes = (int)($_GET['mes'] ?? date('n'));
    $ano = (int)($_GET['ano'] ?? date('Y'));

    if ($mes < 1 || $mes > 12) $mes = (int)date('n');
    if ($ano < 2000 || $ano > 2100) $ano = (int)date('Y');

    $inicio = sprintf('%04d-%02d-01', $ano, $mes);
    $fim = date('Y-m-t', strtotime($inicio));
    $inicio12 = date('Y-m-01', strtotime($fim . ' -11 months'));

    /*
    |--------------------------------------------------------------------------
    | KPIs principais
    |--------------------------------------------------------------------------
    | Total de lançamentos, receitas, despesas e transferências no período.
    */
    $qkpi = $pdo->prepare("
        SELECT
            COUNT(*) total,
            SUM(CASE WHEN tipo = 'RECEITA' THEN valor ELSE 0 END) receitas,
            SUM(CASE WHEN tipo = 'DESPESA' THEN valor ELSE 0 END) despesas,
            SUM(CASE WHEN tipo = 'TRANSFERENCIA' THEN valor ELSE 0 END) transferencias
        FROM movimentacoes
        WHERE id_usuario = :uid AND ocorreu_em BETWEEN :ini AND :fim
    ");
    $qkpi->execute([':uid' => $userId, ':ini' => $inicio, ':fim' => $fim]);
    $kpi = $qkpi->fetch(PDO::FETCH_ASSOC) ?: [];

    /*
    |--------------------------------------------------------------------------
    | Resumo de status
    |--------------------------------------------------------------------------
    */
    $qStatus = $pdo->prepare("
        SELECT
            SUM(CASE WHEN status = 'PAGO' THEN 1 ELSE 0 END) pagas,
            SUM(CASE WHEN status = 'PENDENTE' THEN 1 ELSE 0 END) pendentes
        FROM movimentacoes
        WHERE id_usuario = :uid AND ocorreu_em BETWEEN :ini AND :fim
    ");
    $qStatus->execute([':uid' => $userId, ':ini' => $inicio, ':fim' => $fim]);
    $statusResumo = $qStatus->fetch(PDO::FETCH_ASSOC) ?: [];

    /*
    |--------------------------------------------------------------------------
    | Gastos por categoria
    |--------------------------------------------------------------------------
    | Mostra as 10 maiores categorias de despesa no período.
    */
    $qCat = $pdo->prepare("
        SELECT COALESCE(cat.nome, 'Sem categoria') nome, SUM(m.valor) total
        FROM movimentacoes m
        LEFT JOIN categorias cat ON cat.id = m.id_categoria
        WHERE m.id_usuario = :uid
          AND m.tipo = 'DESPESA'
          AND m.ocorreu_em BETWEEN :ini AND :fim
        GROUP BY COALESCE(cat.nome, 'Sem categoria')
        ORDER BY total DESC
        LIMIT 10
    ");
    $qCat->execute([':uid' => $userId, ':ini' => $inicio, ':fim' => $fim]);
    $gastosCategoria = $qCat->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Série mensal de 12 meses
    |--------------------------------------------------------------------------
    | Prepara receitas, despesas e saldo para o gráfico anual.
    */
    $qMes = $pdo->prepare("
        SELECT
            DATE_FORMAT(ocorreu_em, '%Y-%m') ym,
            SUM(CASE WHEN tipo = 'RECEITA' THEN valor ELSE 0 END) receitas,
            SUM(CASE WHEN tipo = 'DESPESA' THEN valor ELSE 0 END) despesas
        FROM movimentacoes
        WHERE id_usuario = :uid AND ocorreu_em BETWEEN :ini12 AND :fim
        GROUP BY DATE_FORMAT(ocorreu_em, '%Y-%m')
        ORDER BY ym ASC
    ");
    $qMes->execute([':uid' => $userId, ':ini12' => $inicio12, ':fim' => $fim]);
    $rowsMes = $qMes->fetchAll(PDO::FETCH_ASSOC);

    $labels = [];
    $receitas = [];
    $despesas = [];
    $saldo = [];
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
        $saldo[] = $rec - $des;

        $cursor->modify('+1 month');
    }

    /*
    |--------------------------------------------------------------------------
    | Maiores movimentações
    |--------------------------------------------------------------------------
    */
    $qTop = $pdo->prepare("
        SELECT tipo, descricao, valor, status, ocorreu_em, codigo_moeda
        FROM movimentacoes
        WHERE id_usuario = :uid AND ocorreu_em BETWEEN :ini AND :fim
        ORDER BY valor DESC, ocorreu_em DESC
        LIMIT 10
    ");
    $qTop->execute([':uid' => $userId, ':ini' => $inicio, ':fim' => $fim]);
    $topMov = $qTop->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Fluxo diário
    |--------------------------------------------------------------------------
    | Base para o gráfico de barras do mês selecionado.
    */
    $qFluxo = $pdo->prepare("
        SELECT
            DAY(ocorreu_em) dia,
            SUM(CASE WHEN tipo = 'RECEITA' THEN valor ELSE 0 END) receitas,
            SUM(CASE WHEN tipo = 'DESPESA' THEN valor ELSE 0 END) despesas
        FROM movimentacoes
        WHERE id_usuario = :uid AND ocorreu_em BETWEEN :ini AND :fim
        GROUP BY DAY(ocorreu_em)
        ORDER BY dia ASC
    ");
    $qFluxo->execute([':uid' => $userId, ':ini' => $inicio, ':fim' => $fim]);
    $fluxoDia = $qFluxo->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Distribuição por tipo
    |--------------------------------------------------------------------------
    */
    $qTipos = $pdo->prepare("
        SELECT tipo, COUNT(*) total, SUM(valor) soma
        FROM movimentacoes
        WHERE id_usuario = :uid AND ocorreu_em BETWEEN :ini AND :fim
        GROUP BY tipo
    ");
    $qTipos->execute([':uid' => $userId, ':ini' => $inicio, ':fim' => $fim]);
    $tipos = $qTipos->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Alertas do período
    |--------------------------------------------------------------------------
    | Lista lançamentos pendentes e vencidos.
    */
    $qAlertas = $pdo->prepare("
        SELECT descricao, valor, status, ocorreu_em, vence_em, tipo
        FROM movimentacoes
        WHERE id_usuario = :uid
          AND ocorreu_em BETWEEN :ini AND :fim
          AND (status = 'PENDENTE' OR status = 'VENCIDO')
        ORDER BY COALESCE(vence_em, ocorreu_em) ASC
        LIMIT 8
    ");
    $qAlertas->execute([':uid' => $userId, ':ini' => $inicio, ':fim' => $fim]);
    $alertas = $qAlertas->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Cálculos complementares
    |--------------------------------------------------------------------------
    */
    $total = (float)($kpi['total'] ?? 0);
    $receitaTotal = (float)($kpi['receitas'] ?? 0);
    $despesaTotal = (float)($kpi['despesas'] ?? 0);
    $saldoMes = $receitaTotal - $despesaTotal;
    $margem = $receitaTotal > 0 ? round(($saldoMes / $receitaTotal) * 100, 1) : 0;
    $gastoMedio = $total > 0 ? ($despesaTotal / $total) : 0;
    $pagas = (int)($statusResumo['pagas'] ?? 0);
    $pendentes = (int)($statusResumo['pendentes'] ?? 0);
    $transferTotal = (float)($kpi['transferencias'] ?? 0);
} catch (Throwable $e) {
    die('Erro ao carregar relatórios: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Life Finance | Relatórios</title>
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body.dashboard-page{background:linear-gradient(180deg,#eef4ff 0%,#f4f7fb 100%);color:#1f2937;}
.dashboard-shell{display:grid;grid-template-columns:260px 1fr;min-height:100vh;}
.sidebar{background:linear-gradient(180deg,#0f172a 0%,#111827 100%);color:#fff;padding:24px;position:sticky;top:0;height:100vh;box-shadow:8px 0 24px rgba(15,23,42,.08);}
.brand{display:flex;align-items:center;gap:14px;margin-bottom:28px;}
.brand img{width:56px;height:56px;border-radius:16px;object-fit:cover;border:2px solid rgba(255,255,255,.15);}
.brand h1{font-size:20px;margin:0;}
.brand p{margin:2px 0 0;color:#94a3b8;font-size:13px;}
.menu{display:grid;gap:8px;margin-top:20px;}
.menu a{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:12px;color:#e5e7eb;text-decoration:none;transition:.2s;}
.menu a:hover,.menu a.active{background:rgba(40,140,250,.18);color:#fff;transform:translateX(4px);}
.content{padding:24px;}
.topbar{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:24px;flex-wrap:wrap;}
.topbar h2{margin:0 0 6px;font-size:28px;}
.topbar p{margin:0;color:#6b7280;}
.actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
.chip,.btn-top,.btn-filter{border:0;border-radius:12px;padding:12px 16px;display:inline-flex;align-items:center;gap:10px;font-weight:600;}
.btn-top,.btn-filter{background:linear-gradient(135deg,#288CFA,#1c7ad0);color:#fff;cursor:pointer;}
.chip{background:#fff;color:#374151;box-shadow:0 8px 20px rgba(15,23,42,.06);}
.grid-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:20px;}
.card{background:#fff;border-radius:18px;box-shadow:0 10px 24px rgba(15,23,42,.06);padding:20px;border:1px solid #eef2f7;transition:transform .25s ease,box-shadow .25s ease;}
.card:hover{transform:translateY(-5px);box-shadow:0 16px 36px rgba(15,23,42,.1);}
.kpi{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;}
.kpi .icon{width:48px;height:48px;border-radius:14px;display:grid;place-items:center;background:#eef6ff;color:#288CFA;font-size:20px;}
.kpi h3{margin:0 0 8px;font-size:14px;color:#6b7280;font-weight:600;}
.kpi strong{font-size:24px;color:#111827;display:block;}
.kpi small{color:#10b981;font-weight:600;}
.grid-main{display:grid;grid-template-columns:1.5fr 1fr;gap:16px;align-items:start;}
.section-title{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:12px;flex-wrap:wrap;}
.section-title h3{margin:0;font-size:18px;}
.muted{color:#6b7280;font-size:13px;}
.chart-box{height:320px;}
.chart-lg{height:380px;}
.list{display:grid;gap:12px;}
.list-item{display:flex;justify-content:space-between;gap:12px;padding:14px;border-radius:14px;background:#f9fbff;border:1px solid #eef2f7;}
.pill{padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;}
.pill.ok{background:#dcfce7;color:#166534;}
.pill.warn{background:#fef3c7;color:#92400e;}
.pill.bad{background:#fee2e2;color:#991b1b;}
.pill.gray{background:#e5e7eb;color:#374151;}
.double-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px;}
.progress{height:10px;background:#e5e7eb;border-radius:999px;overflow:hidden;}
.progress span{display:block;height:100%;background:linear-gradient(90deg,#288CFA,#2E865F);border-radius:999px;}
.report-table{width:100%;border-collapse:collapse;}
.report-table th,.report-table td{padding:12px 10px;border-bottom:1px solid #eef2f7;text-align:left;font-size:14px;}
.report-table th{color:#6b7280;font-size:12px;text-transform:uppercase;letter-spacing:.04em;}
.badge{display:inline-flex;padding:5px 10px;border-radius:999px;font-size:12px;font-weight:700;}
@media (max-width:1200px){.grid-kpis{grid-template-columns:repeat(2,minmax(0,1fr));}.grid-main,.double-grid,.dashboard-shell{grid-template-columns:1fr;}.sidebar{height:auto;position:relative;}.content{padding:16px;}}
@media (max-width:640px){.grid-kpis{grid-template-columns:1fr;}.topbar{flex-direction:column;align-items:flex-start;}}
</style>
</head>
<body class="dashboard-page">
<div class="dashboard-shell">
<aside class="sidebar">
<div class="brand">
<img src="assets/images/logoSemFundo.png" alt="Life Finance">
<div><h1>Life Finance</h1><p>Relatórios</p></div>
</div>
<nav class="menu">
<a href="dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
<a href="movimentacoes.php"><i class="fa-solid fa-right-left"></i> Movimentações</a>
<a href="contas.php"><i class="fa-solid fa-wallet"></i> Contas</a>
<a href="categorias.php"><i class="fa-solid fa-tags"></i> Categorias</a>
<a href="metas.php"><i class="fa-solid fa-bullseye"></i> Metas</a>
<a href="relatorios.php" class="active"><i class="fa-solid fa-chart-column"></i> Relatórios</a>
<a href="configuracoes.php"><i class="fa-solid fa-gear"></i> Configurações</a>
<a href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Sair</a>
</nav>
</aside>

<main class="content">
<div class="topbar">
    <div>
        <h2>Relatórios analíticos</h2>
        <p>Visão detalhada do comportamento financeiro no período selecionado.</p>
    </div>
    <form class="actions" method="GET">
        <select name="mes" class="chip">
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?php echo $m; ?>" <?php echo $m === $mes ? 'selected' : ''; ?>><?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?></option>
            <?php endfor; ?>
        </select>
        <select name="ano" class="chip">
            <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                <option value="<?php echo $y; ?>" <?php echo $y === $ano ? 'selected' : ''; ?>><?php echo $y; ?></option>
            <?php endfor; ?>
        </select>
        <button class="btn-filter" type="submit"><i class="fa-solid fa-filter"></i> Filtrar</button>
    </form>
</div>

<section class="grid-kpis">
    <div class="card kpi"><div><h3>Total de lançamentos</h3><strong><?php echo (int)$total; ?></strong><small>No período</small></div><div class="icon"><i class="fa-solid fa-list"></i></div></div>
    <div class="card kpi"><div><h3>Receitas</h3><strong><?php echo money($receitaTotal); ?></strong><small><?php echo pct($receitaTotal, max(1, $total)); ?>% do volume</small></div><div class="icon"><i class="fa-solid fa-arrow-trend-up"></i></div></div>
    <div class="card kpi"><div><h3>Despesas</h3><strong><?php echo money($despesaTotal); ?></strong><small><?php echo pct($despesaTotal, max(1, $total)); ?>% do volume</small></div><div class="icon"><i class="fa-solid fa-arrow-trend-down"></i></div></div>
    <div class="card kpi"><div><h3>Saldo líquido</h3><strong><?php echo money($saldoMes); ?></strong><small><?php echo $margem; ?>% de margem</small></div><div class="icon"><i class="fa-solid fa-scale-balanced"></i></div></div>
</section>

<section class="grid-kpis" style="margin-top:16px;">
    <div class="card kpi"><div><h3>Pagas</h3><strong><?php echo $pagas; ?></strong><small>Lançamentos quitados</small></div><div class="icon"><i class="fa-solid fa-circle-check"></i></div></div>
    <div class="card kpi"><div><h3>Pendentes</h3><strong><?php echo $pendentes; ?></strong><small>Lançamentos em aberto</small></div><div class="icon"><i class="fa-solid fa-clock"></i></div></div>
    <div class="card kpi"><div><h3>Transferências</h3><strong><?php echo money($transferTotal); ?></strong><small>Movimentações internas</small></div><div class="icon"><i class="fa-solid fa-right-left"></i></div></div>
    <div class="card kpi"><div><h3>Gasto médio</h3><strong><?php echo money($gastoMedio); ?></strong><small>Por lançamento</small></div><div class="icon"><i class="fa-solid fa-receipt"></i></div></div>
</section>

<section class="grid-main" style="margin-top:16px;">
    <div class="card">
        <div class="section-title"><div><h3>Fluxo de 12 meses</h3><div class="muted">Receitas, despesas e saldo por mês</div></div><span class="pill ok">Comparativo anual</span></div>
        <div class="chart-box chart-lg"><canvas id="chartMensal"></canvas></div>
    </div>
    <div class="card">
        <div class="section-title"><div><h3>Distribuição por tipo</h3><div class="muted">Quantidade e valor agregado</div></div><span class="pill gray">Tipos</span></div>
        <div class="chart-box"><canvas id="chartTipos"></canvas></div>
        <div style="margin-top:14px;" class="muted">Média de gasto por lançamento: <?php echo money($gastoMedio); ?></div>
    </div>
</section>

<section class="double-grid">
    <div class="card">
        <div class="section-title"><div><h3>Gastos por categoria</h3><div class="muted">Top 10 categorias de despesa</div></div><span class="pill bad">Despesa</span></div>
        <?php if (empty($gastosCategoria)): ?>
            <div class="muted">Sem despesas no período.</div>
        <?php else: ?>
            <div class="list">
                <?php foreach ($gastosCategoria as $cat): 
                    $width = $despesaTotal > 0 ? round(($cat['total'] / $despesaTotal) * 100) : 0;
                ?>
                <div>
                    <div style="display:flex;justify-content:space-between;gap:10px;margin-bottom:6px;">
                        <strong><?php echo htmlspecialchars($cat['nome']); ?></strong>
                        <span><?php echo money($cat['total']); ?></span>
                    </div>
                    <div class="progress"><span style="width:<?php echo $width; ?>%"></span></div>
                    <div class="muted" style="margin-top:6px;"><?php echo $width; ?>% das despesas</div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="section-title"><div><h3>Movimentações críticas</h3><div class="muted">Maiores valores do período</div></div><span class="pill warn">Top 10</span></div>
        <?php if (empty($topMov)): ?>
            <div class="muted">Nenhum lançamento encontrado.</div>
        <?php else: ?>
            <table class="report-table">
                <thead><tr><th>Data</th><th>Tipo</th><th>Descrição</th><th>Status</th><th>Valor</th></tr></thead>
                <tbody>
                <?php foreach ($topMov as $m): ?>
                    <tr>
                        <td><?php echo fmtDate($m['ocorreu_em']); ?></td>
                        <td><span class="badge <?php echo $m['tipo'] === 'RECEITA' ? 'pill ok' : ($m['tipo'] === 'DESPESA' ? 'pill bad' : 'pill warn'); ?>"><?php echo htmlspecialchars($m['tipo']); ?></span></td>
                        <td><?php echo htmlspecialchars($m['descricao']); ?></td>
                        <td><span class="badge <?php echo $m['status'] === 'PAGO' ? 'pill ok' : ($m['status'] === 'PENDENTE' ? 'pill warn' : 'pill gray'); ?>"><?php echo htmlspecialchars($m['status']); ?></span></td>
                        <td><?php echo money($m['valor']) . ' ' . htmlspecialchars($m['codigo_moeda'] ?? 'BRL'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>

<section class="double-grid">
    <div class="card">
        <div class="section-title"><div><h3>Alertas do período</h3><div class="muted">Pendências e vencimentos</div></div><span class="pill warn"><?php echo count($alertas); ?> alertas</span></div>
        <div class="list">
            <?php if (empty($alertas)): ?>
                <div class="muted">Nenhum alerta encontrado.</div>
            <?php else: ?>
                <?php foreach ($alertas as $a): ?>
                <div class="list-item" style="grid-template-columns:1fr auto;">
                    <div>
                        <strong><?php echo htmlspecialchars($a['descricao']); ?></strong>
                        <div class="muted"><?php echo fmtDate($a['ocorreu_em']); ?> <?php echo !empty($a['vence_em']) ? '• vence em ' . fmtDate($a['vence_em']) : ''; ?></div>
                    </div>
                    <div>
                        <span class="pill <?php echo $a['status'] === 'PENDENTE' ? 'warn' : 'bad'; ?>"><?php echo htmlspecialchars($a['status']); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="section-title"><div><h3>Evolução diária</h3><div class="muted">Receitas x despesas dentro do mês</div></div><span class="pill ok">Dia a dia</span></div>
        <div class="chart-box"><canvas id="chartDiario"></canvas></div>
    </div>
</section>
</main>
</div>

<script>
new Chart(document.getElementById('chartMensal'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode($labels); ?>,
        datasets: [
            {label:'Receitas', data: <?php echo json_encode($receitas); ?>, borderColor:'#2E865F', backgroundColor:'rgba(46,134,95,.12)', fill:true, tension:.35},
            {label:'Despesas', data: <?php echo json_encode($despesas); ?>, borderColor:'#D63939', backgroundColor:'rgba(214,57,57,.10)', fill:true, tension:.35},
            {label:'Saldo', data: <?php echo json_encode($saldo); ?>, borderColor:'#288CFA', backgroundColor:'rgba(40,140,250,.08)', fill:false, tension:.35}
        ]
    },
    options: {responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'bottom'}}, scales:{y:{beginAtZero:true}}}
});

new Chart(document.getElementById('chartTipos'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($tipos, 'tipo')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_map(fn($x) => (float)$x['soma'], $tipos)); ?>,
            backgroundColor: ['#288CFA','#2E865F','#F59E0B'],
            borderWidth: 0
        }]
    },
    options: {responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'bottom'}}}
});

new Chart(document.getElementById('chartDiario'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_map(fn($x) => 'D'.$x['dia'], $fluxoDia)); ?>,
        datasets: [
            {label:'Receitas', data: <?php echo json_encode(array_map(fn($x) => (float)$x['receitas'], $fluxoDia)); ?>, backgroundColor:'#2E865F'},
            {label:'Despesas', data: <?php echo json_encode(array_map(fn($x) => (float)$x['despesas'], $fluxoDia)); ?>, backgroundColor:'#D63939'}
        ]
    },
    options: {responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'bottom'}}, scales:{y:{beginAtZero:true}}}
});
</script>
</body>
</html>