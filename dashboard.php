<?php
session_start();

/*
|--------------------------------------------------------------------------
| Controle de acesso
|--------------------------------------------------------------------------
| O dashboard só pode ser acessado por usuários autenticados.
*/
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/Conexao.php';
require_once __DIR__ . '/DashboardService.php';

try {
    /*
    |--------------------------------------------------------------------------
    | Conexão com o banco
    |--------------------------------------------------------------------------
    | A página depende da camada de serviço para montar os dados do painel.
    */
    $pdo = Conexao::getInstancia();
    $userId = (int)($_SESSION['user_id'] ?? 0);

    if ($userId <= 0) {
        throw new Exception('Usuário não identificado na sessão.');
    }

    /*
    |--------------------------------------------------------------------------
    | Dados do dashboard
    |--------------------------------------------------------------------------
    | O serviço centraliza toda a lógica de consulta e consolidação.
    */
    $dados = DashboardService::getDashboardData($pdo, $userId);
    $filtrosModal = DashboardService::getFiltrosModal($pdo, $userId);
} catch (Throwable $e) {
    die("Erro ao carregar dashboard: " . $e->getMessage());
}

/*
|--------------------------------------------------------------------------
| Valores auxiliares para exibição
|--------------------------------------------------------------------------
| Padroniza formato monetário e evita repetição no HTML.
*/
$nomeUsuario = $dados['nomeUsuario'] ?? 'Usuário';
$saldoTotal = 'R$ ' . number_format((float)($dados['saldoTotal'] ?? 0), 2, ',', '.');
$receitasMes = 'R$ ' . number_format((float)($dados['receitasMes'] ?? 0), 2, ',', '.');
$despesasMes = 'R$ ' . number_format((float)($dados['despesasMes'] ?? 0), 2, ',', '.');
$orcamentoMes = $dados['orcamentoMes'] ?? 0;
$metaMes = $dados['metaMesProgresso'] ?? 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Life Finance | Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body.dashboard-page{background:#f4f7fb;color:#1f2937;}
        .dashboard-shell{display:grid;grid-template-columns:260px 1fr;min-height:100vh;}
        .sidebar{background:linear-gradient(180deg,#0f172a 0%,#111827 100%);color:#fff;padding:24px;position:sticky;top:0;height:100vh;}
        .brand{display:flex;align-items:center;gap:14px;margin-bottom:28px;}
        .brand img{width:56px;height:56px;border-radius:16px;object-fit:cover;border:2px solid rgba(255,255,255,.15);}
        .brand h1{font-size:20px;margin:0;}
        .brand p{margin:2px 0 0;color:#94a3b8;font-size:13px;}
        .menu{display:grid;gap:8px;margin-top:20px;}
        .menu a{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:12px;color:#e5e7eb;text-decoration:none;transition:.2s;background:transparent;}
        .menu a:hover,.menu a.active{background:rgba(40,140,250,.18);color:#fff;}
        .content{padding:24px;}
        .topbar{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:24px;}
        .topbar .welcome h2{margin:0 0 6px;font-size:28px;}
        .topbar .welcome p{margin:0;color:#6b7280;}
        .actions{display:flex;gap:10px;flex-wrap:wrap;}
        .chip,.btn-top{border:0;border-radius:12px;padding:12px 16px;display:inline-flex;align-items:center;gap:10px;font-weight:600;}
        .btn-top{background:#288CFA;color:#fff;box-shadow:0 10px 18px rgba(40,140,250,.18);cursor:pointer;transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;}
        .btn-top:hover{transform: translateY(-2px); filter: brightness(1.1); box-shadow: 0 14px 28px rgba(40,140,250,.3);}
        .chip{background:#fff;color:#374151;box-shadow:0 8px 20px rgba(15,23,42,.06);}
        .grid-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:20px;}
        .card{background:#fff;border-radius:18px;box-shadow:0 10px 24px rgba(15,23,42,.06);padding:20px;border:1px solid #eef2f7; transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.3s ease; animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) backwards;}
        .card:hover{transform: translateY(-6px); box-shadow: 0 16px 36px rgba(15,23,42,.1);}
        .grid-kpis .card:nth-child(1){animation-delay: 0.1s;}
        .grid-kpis .card:nth-child(2){animation-delay: 0.2s;}
        .grid-kpis .card:nth-child(3){animation-delay: 0.3s;}
        .grid-kpis .card:nth-child(4){animation-delay: 0.4s;}
        .grid-main .card:nth-child(1){animation-delay: 0.5s;}
        .grid-main .card:nth-child(2){animation-delay: 0.6s;}
        .double-grid .card:nth-child(1){animation-delay: 0.7s;}
        .double-grid .card:nth-child(2){animation-delay: 0.8s;}
        .kpi{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;}
        .kpi .icon{width:48px;height:48px;border-radius:14px;display:grid;place-items:center;background:#eef6ff;color:#288CFA;font-size:20px;}
        .kpi h3{margin:0 0 8px;font-size:14px;color:#6b7280;font-weight:600;}
        .kpi strong{font-size:24px;color:#111827;display:block;}
        .kpi small{color:#10b981;font-weight:600;}
        .grid-main{display:grid;grid-template-columns:1.5fr 1fr;gap:16px;align-items:start;}
        .section-title{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}
        .section-title h3{margin:0;font-size:18px;}
        .muted{color:#6b7280;font-size:13px;}
        .chart-box{height:320px;}
        .list{display:grid;gap:12px;}
        .list-item{display:flex;justify-content:space-between;gap:12px;padding:14px;border-radius:14px;background:#f9fbff;border:1px solid #eef2f7; transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;}
        .list-item:hover{transform: translateX(4px); background: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.05);}
        .list-item strong{display:block;margin-bottom:4px;}
        .pill{padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700;}
        .pill.ok{background:#dcfce7;color:#166534;}
        .pill.warn{background:#fef3c7;color:#92400e;}
        .pill.bad{background:#fee2e2;color:#991b1b;}
        .double-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px;}
        .progress{height:10px;background:#e5e7eb;border-radius:999px;overflow:hidden;margin-top:10px;}
        .progress span{display:block;height:100%;background:linear-gradient(90deg,#288CFA,#2E865F);border-radius:999px; animation: slideRight 1.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;}
        .calendar{display:grid;grid-template-columns:repeat(7,1fr);gap:8px;font-size:12px;margin-top:12px;}
        .day{background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:10px;text-align:center;min-height:56px;}
        .day.today{background:#e0f2fe;border-color:#7dd3fc;font-weight:700;}
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideRight { from { width: 0%; opacity: 0; } to { opacity: 1; } }
        @keyframes modalFadeIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .modal-backdrop { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 1000; justify-content: center; align-items: center; padding: 20px; opacity: 0; transition: opacity 0.3s ease;}
        .modal-backdrop.active { display: flex; opacity: 1; }
        .modal-backdrop.active .modal { animation: modalFadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .modal { background: #fff; border-radius: 16px; width: 100%; max-width: 600px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; }
        .modal-header { padding: 20px 24px; border-bottom: 1px solid #eef2f7; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { margin: 0; font-size: 20px; color: #111827; }
        .modal-close { background: none; border: none; font-size: 20px; color: #6b7280; cursor: pointer; padding: 4px; }
        .modal-body { padding: 24px; overflow-y: auto; flex: 1; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .field { display: flex; flex-direction: column; gap: 6px; }
        .field.full { grid-column: 1 / -1; }
        .field label { font-size: 14px; font-weight: 600; color: #374151; }
        .field input, .field select, .field textarea { padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; width: 100%; font-family: inherit; }
        .field textarea { resize: vertical; min-height: 80px; }
        .modal-footer { padding: 20px 24px; border-top: 1px solid #eef2f7; display: flex; justify-content: flex-end; gap: 12px; background: #f9fbff; }
        .btn-secondary { background: #fff; border: 1px solid #d1d5db; color: #374151; padding: 10px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: .2s; }
        .btn-secondary:hover { background: #f3f4f6; }
        .btn-save { background: #288CFA; border: none; color: #fff; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: .2s; }
        .btn-save:hover { background: #1c7ad0; }
        @media (max-width: 1200px){.grid-kpis{grid-template-columns:repeat(2,minmax(0,1fr));}.grid-main,.double-grid{grid-template-columns:1fr;}.dashboard-shell{grid-template-columns:1fr;}.sidebar{height:auto;position:relative;}}
        @media (max-width: 640px){.grid-kpis{grid-template-columns:1fr;}.topbar{flex-direction:column;align-items:flex-start;}.content{padding:16px;}.form-grid{grid-template-columns:1fr;}}
    </style>
</head>
<body class="dashboard-page">
<div class="dashboard-shell">
    <aside class="sidebar">
        <div class="brand">
            <img src="assets/images/logoSemFundo.png" alt="Life Finance">
            <div>
                <h1>Life Finance</h1>
                <p>Finanças pessoais</p>
            </div>
        </div>
        <nav class="menu">
            <a href="#" class="active"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
            <a href="movimentacoes.php"><i class="fa-solid fa-right-left"></i> Movimentações</a>
            <a href="contas.php"><i class="fa-solid fa-wallet"></i> Contas</a>
            <a href="categoria.php"><i class="fa-solid fa-tags"></i> Categorias</a>
            <a href="metas.php"><i class="fa-solid fa-bullseye"></i> Metas</a>
            <a href="relatorios.php"><i class="fa-solid fa-chart-column"></i> Relatórios</a>
            <a href="configuracoes.php"><i class="fa-solid fa-gear"></i> Configurações</a>
            <a href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Sair</a>
        </nav>
    </aside>

    <main class="content">
        <div class="topbar">
            <div class="welcome">
                <h2>Bem-vindo, <?php echo htmlspecialchars($nomeUsuario); ?> 👋</h2>
                <p>Hoje é um bom dia para acompanhar sua saúde financeira e planejar os próximos passos.</p>
            </div>
            <div class="actions">
                <div class="chip"><i class="fa-solid fa-calendar-day"></i> <?php echo htmlspecialchars($dados['mesExtenso']); ?></div>
                <button class="btn-top" onclick="abrirModal()"><i class="fa-solid fa-plus"></i> Novo lançamento</button>
            </div>
        </div>

        <section class="grid-kpis">
            <div class="card kpi"><div><h3>Saldo total</h3><strong><?php echo $saldoTotal; ?></strong><small><?php echo ($dados['variacaoSaldo'] > 0 ? '+' : '') . number_format($dados['variacaoSaldo'], 1, ',', '.') . '% no mês'; ?></small></div><div class="icon"><i class="fa-solid fa-sack-dollar"></i></div></div>
            <div class="card kpi"><div><h3>Receitas do mês</h3><strong><?php echo $receitasMes; ?></strong><small><?php echo $dados['qtdReceitas']; ?> entradas</small></div><div class="icon"><i class="fa-solid fa-arrow-trend-up"></i></div></div>
            <div class="card kpi"><div><h3>Despesas do mês</h3><strong><?php echo $despesasMes; ?></strong><small><?php echo $dados['qtdDespesas']; ?> saídas</small></div><div class="icon"><i class="fa-solid fa-arrow-trend-down"></i></div></div>
            <div class="card kpi"><div><h3>Orçamento usado</h3><strong><?php echo $orcamentoMes; ?>%</strong><small>Dentro do limite</small></div><div class="icon"><i class="fa-solid fa-chart-pie"></i></div></div>
        </section>

        <section class="grid-main">
            <div class="card">
                <div class="section-title"><div><h3>Fluxo financeiro</h3><div class="muted">Receitas x despesas e saldo projetado</div></div><span class="pill ok">Saldo positivo</span></div>
                <div class="chart-box"><canvas id="financeChart"></canvas></div>
            </div>

            <div class="card">
                <div class="section-title"><div><h3>Próximos compromissos</h3><div class="muted">Contas e alertas relevantes</div></div><span class="pill warn"><?php echo count($dados['compromissos']); ?> pendências</span></div>
                <div class="list">
                    <?php if (empty($dados['compromissos'])): ?>
                        <div class="list-item"><div><strong>Nenhum compromisso</strong><span class="muted">Tudo em dia!</span></div></div>
                    <?php else: ?>
                        <?php foreach ($dados['compromissos'] as $comp): 
                            $vence_em = new DateTime($comp['vence_em']);
                            $hoje = new DateTime(date('Y-m-d'));
                            $dias = $hoje->diff($vence_em)->days;
                            $strDias = $dias == 0 ? 'Vence hoje' : ($dias == 1 ? 'Vence amanhã' : "Vence em {$dias} dias");
                            $strValor = 'R$ ' . number_format($comp['valor_total'], 2, ',', '.');
                            $cor = $comp['tipo'] == 'PAGAR' ? 'bad' : 'ok';
                        ?>
                        <div class="list-item"><div><strong><?php echo htmlspecialchars($comp['nome']); ?></strong><span class="muted"><?php echo $strDias; ?></span></div><span class="pill <?php echo $cor; ?>"><?php echo $strValor; ?></span></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="double-grid">
            <div class="card">
                <div class="section-title"><div><h3>Metas financeiras</h3><div class="muted">Acompanhe progresso e reserva de emergência</div></div><span class="pill ok"><?php echo $metaMes; ?>%</span></div>
                <?php if (empty($dados['metas'])): ?>
                    <div class="muted">Nenhuma meta configurada.</div>
                <?php else: ?>
                    <?php foreach ($dados['metas'] as $index => $meta): 
                        $pct = $meta['valor_meta'] > 0 ? min(100, round(($meta['valor_atual'] / $meta['valor_meta']) * 100)) : 0;
                        $strMeta = 'R$ ' . number_format($meta['valor_meta'], 2, ',', '.');
                        $strAtual = 'R$ ' . number_format($meta['valor_atual'], 2, ',', '.');
                    ?>
                    <div style="<?php echo $index > 0 ? 'margin-top:18px;' : ''; ?>">
                        <strong><?php echo htmlspecialchars($meta['nome']); ?></strong>
                        <div class="muted">Meta: <?php echo $strMeta; ?></div>
                        <div class="progress"><span style="width:<?php echo $pct; ?>%"></span></div>
                        <div class="muted" style="margin-top:8px;"><?php echo $strAtual; ?> acumulados</div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="section-title"><div><h3>Calendário financeiro</h3><div class="muted">Vencimentos, receitas e alertas do mês</div></div><span class="pill ok">Hoje</span></div>
                <div class="calendar">
                    <?php echo $dados['calendarioHtml']; ?>
                </div>
            </div>
        </section>

        <section class="double-grid">
            <div class="card">
                <div class="section-title"><div><h3>Gastos por categoria</h3><div class="muted">As maiores saídas do mês</div></div><span class="pill warn">Top <?php echo count($dados['gastosCategoria']); ?></span></div>
                <div class="list">
                    <?php if (empty($dados['gastosCategoria'])): ?>
                        <div class="list-item"><div><strong>Nenhum gasto</strong><span class="muted">Você não teve saídas.</span></div></div>
                    <?php else: ?>
                        <?php foreach ($dados['gastosCategoria'] as $cat): 
                            $strValor = 'R$ ' . number_format($cat['total'], 2, ',', '.');
                            $pct = $dados['despesasMes'] > 0 ? round(($cat['total'] / $dados['despesasMes']) * 100) : 0;
                        ?>
                        <div class="list-item"><div><strong><?php echo htmlspecialchars($cat['nome']); ?></strong><span class="muted"><?php echo $pct; ?>% do total</span></div><span class="pill warn"><?php echo $strValor; ?></span></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="section-title"><div><h3>Alertas inteligentes</h3><div class="muted">Sinais relevantes para agir agora</div></div><span class="pill bad"><?php echo count($dados['alertas']); ?> alertas</span></div>
                <div class="list">
                    <?php foreach ($dados['alertas'] as $alerta): ?>
                    <div class="list-item"><div><strong><?php echo htmlspecialchars($alerta['titulo']); ?></strong><span class="muted"><?php echo htmlspecialchars($alerta['desc']); ?></span></div><span class="pill <?php echo htmlspecialchars($alerta['tipo']); ?>">Info</span></div>
                    <?php endforeach; ?>
                </div>
                <div class="muted" style="margin-top:14px;">Dica: revise os gastos recorrentes e compare com o orçamento da categoria.</div>
            </div>
        </section>
    </main>
</div>

<div class="modal-backdrop" id="novoLancamentoModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Novo lançamento</h3>
            <button class="modal-close" type="button" onclick="fecharModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="salvar_movimentacao.php">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="field">
                        <label for="tipo">Tipo</label>
                        <select id="tipo" name="tipo" required onchange="toggleDestino()">
                            <option value="">Selecione</option>
                            <option value="RECEITA">Receita</option>
                            <option value="DESPESA">Despesa</option>
                            <option value="TRANSFERENCIA">Transferência</option>
                            <option value="AJUSTE">Ajuste</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="valor">Valor</label>
                        <input type="number" step="0.01" id="valor" name="valor" placeholder="0.00" required>
                    </div>

                    <div class="field">
                        <label for="id_conta">Conta</label>
                        <select id="id_conta" name="id_conta" required>
                            <option value="">Selecione</option>
                            <?php foreach ($filtrosModal['contas'] as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field" id="box_categoria">
                        <label for="id_categoria">Categoria</label>
                        <select id="id_categoria" name="id_categoria">
                            <option value="">Selecione</option>
                            <?php foreach ($filtrosModal['categorias'] as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nome']); ?> (<?php echo $cat['tipo']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field" id="box_conta_destino" style="display: none;">
                        <label for="id_conta_destino">Conta destino</label>
                        <select id="id_conta_destino" name="id_conta_destino">
                            <option value="">Selecione</option>
                            <?php foreach ($filtrosModal['contas'] as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="ocorreu_em">Data</label>
                        <input type="date" id="ocorreu_em" name="ocorreu_em" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="field">
                        <label for="vence_em">Vencimento</label>
                        <input type="date" id="vence_em" name="vence_em">
                    </div>

                    <div class="field full">
                        <label for="descricao">Descrição</label>
                        <input type="text" id="descricao" name="descricao" placeholder="Ex.: Mercado, salário, aluguel..." required>
                    </div>

                    <div class="field full">
                        <label for="observacao">Observação</label>
                        <textarea id="observacao" name="observacao" placeholder="Detalhes adicionais..."></textarea>
                    </div>

                    <div class="field">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="PAGO">Pago / Efetivado</option>
                            <option value="PENDENTE">Pendente</option>
                            <option value="VENCIDO">Vencido</option>
                            <option value="CANCELADO">Cancelado</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="codigo_moeda">Moeda</label>
                        <select id="codigo_moeda" name="codigo_moeda">
                            <option value="BRL">BRL</option>
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="fecharModal()">Cancelar</button>
                <button type="submit" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
function carregarCategorias(tipo) {
    const selectCategoria = document.getElementById('id_categoria');
    if (!selectCategoria) return;

    const t = (tipo || '').trim().toUpperCase();
    const useFiltro = (t === 'RECEITA' || t === 'DESPESA');

    if (!useFiltro) {
        selectCategoria.innerHTML = '<option value="">Selecione</option>';
        return;
    }

    selectCategoria.disabled = true;
    selectCategoria.innerHTML = '<option value="">Carregando...</option>';

    fetch('categorias_por_tipo.php?tipo=' + encodeURIComponent(t), {
        cache: 'no-store'
    })
        .then(r => r.text())
        .then(html => {
            const conteudo = (html || '').trim();
            selectCategoria.innerHTML = '<option value="">Selecione</option>' + conteudo;
        })
        .catch(() => {
            selectCategoria.innerHTML = '<option value="">Selecione</option>';
        })
        .finally(() => {
            selectCategoria.disabled = false;
        });
}

function abrirModal() {
    document.getElementById('novoLancamentoModal').classList.add('active');
}

function fecharModal() {
    document.getElementById('novoLancamentoModal').classList.remove('active');
}

function toggleDestino() {
    const tipo = document.getElementById('tipo').value;
    const boxDestino = document.getElementById('box_conta_destino');
    const boxCat = document.getElementById('box_categoria');
    const inputDestino = document.getElementById('id_conta_destino');
    const selectCategoria = document.getElementById('id_categoria');

    if (tipo === 'TRANSFERENCIA') {
        boxDestino.style.display = 'flex';
        inputDestino.required = true;
        boxCat.style.display = 'none';
        selectCategoria.innerHTML = '<option value="">Selecione</option>';
    } else if (tipo === 'AJUSTE') {
        boxDestino.style.display = 'none';
        inputDestino.required = false;
        inputDestino.value = '';
        boxCat.style.display = 'flex';
        selectCategoria.innerHTML = '<option value="">Selecione</option>';
    } else if (tipo === 'RECEITA' || tipo === 'DESPESA') {
        boxDestino.style.display = 'none';
        inputDestino.required = false;
        inputDestino.value = '';
        boxCat.style.display = 'flex';
        carregarCategorias(tipo);
    } else {
        boxDestino.style.display = 'none';
        inputDestino.required = false;
        inputDestino.value = '';
        boxCat.style.display = 'flex';
        selectCategoria.innerHTML = '<option value="">Selecione</option>';
    }
}

function abrirModal() {
    document.getElementById('novoLancamentoModal').classList.add('active');
}

function fecharModal() {
    document.getElementById('novoLancamentoModal').classList.remove('active');
}

function toggleDestino() {
    const tipo = document.getElementById('tipo').value;
    const boxDestino = document.getElementById('box_conta_destino');
    const boxCat = document.getElementById('box_categoria');
    const inputDestino = document.getElementById('id_conta_destino');

    if (tipo === 'TRANSFERENCIA') {
        boxDestino.style.display = 'flex';
        inputDestino.required = true;
        boxCat.style.display = 'none';
    } else {
        boxDestino.style.display = 'none';
        inputDestino.required = false;
        inputDestino.value = '';
        boxCat.style.display = 'flex';
        carregarCategorias(tipo);
    }
}

const ctx = document.getElementById('financeChart');
if (ctx) {
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($dados['grafico']['labels']); ?>,
            datasets: [
                {label: 'Receitas', data: <?php echo json_encode($dados['grafico']['receitas']); ?>, borderColor: '#2E865F', backgroundColor: 'rgba(46,134,95,.12)', tension: .35, fill: true},
                {label: 'Despesas', data: <?php echo json_encode($dados['grafico']['despesas']); ?>, borderColor: '#D63939', backgroundColor: 'rgba(214,57,57,.10)', tension: .35, fill: true}
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {legend: {position: 'bottom'}},
            scales: {y: {beginAtZero: true}}
        }
    });
}

window.addEventListener('keydown', e => {
    if (e.key === 'Escape') fecharModal();
});
document.getElementById('novoLancamentoModal').addEventListener('click', e => {
    if (e.target === e.currentTarget) fecharModal();
});
</script>
</body>
</html>