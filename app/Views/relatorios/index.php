<?php
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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Life Finance | Relatórios</title>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/relatorios.css">
</head>
<body class="dashboard-page">
<div class="dashboard-shell">
<aside class="sidebar">
<div class="brand">
<img src="<?php echo BASE_URL; ?>/assets/images/logoSemFundo.png" alt="Life Finance">
<div><h1>Life Finance</h1><p>Relatórios</p></div>
</div>
<nav class="menu">
<a href="<?php echo BASE_URL; ?>/dashboard"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
<a href="<?php echo BASE_URL; ?>/movimentacoes"><i class="fa-solid fa-right-left"></i> Movimentações</a>
<a href="<?php echo BASE_URL; ?>/contas"><i class="fa-solid fa-wallet"></i> Contas</a>
<a href="<?php echo BASE_URL; ?>/categorias"><i class="fa-solid fa-tags"></i> Categorias</a>
<a href="<?php echo BASE_URL; ?>/metas"><i class="fa-solid fa-bullseye"></i> Metas</a>
<a href="<?php echo BASE_URL; ?>/relatorios" class="active"><i class="fa-solid fa-chart-column"></i> Relatórios</a>
<a href="<?php echo BASE_URL; ?>/configuracoes"><i class="fa-solid fa-gear"></i> Configurações</a>
<a href="<?php echo BASE_URL; ?>/auth/logout"><i class="fa-solid fa-arrow-right-from-bracket"></i> Sair</a>
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
            {label:'Saldo', data: <?php echo json_encode($saldoArr); ?>, borderColor:'#288CFA', backgroundColor:'rgba(40,140,250,.08)', fill:false, tension:.35}
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