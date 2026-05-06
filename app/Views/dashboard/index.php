
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Life Finance | Dashboard</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/dashboard.css">
</head>
<body class="dashboard-page">
<div class="dashboard-shell">
    <aside class="sidebar">
        <div class="brand">
            <img src="<?php echo BASE_URL; ?>/assets/images/logoSemFundo.png" alt="Life Finance">
            <div>
                <h1>Life Finance</h1>
                <p>Finanças pessoais</p>
            </div>
        </div>
        <nav class="menu">
            <a href="<?php echo BASE_URL; ?>/dashboard" class="active"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
            <a href="<?php echo BASE_URL; ?>/movimentacoes"><i class="fa-solid fa-right-left"></i> Movimentações</a>
            <a href="<?php echo BASE_URL; ?>/contas"><i class="fa-solid fa-wallet"></i> Contas</a>
            <a href="<?php echo BASE_URL; ?>/categorias"><i class="fa-solid fa-tags"></i> Categorias</a>
            <a href="<?php echo BASE_URL; ?>/metas"><i class="fa-solid fa-bullseye"></i> Metas</a>
            <a href="<?php echo BASE_URL; ?>/relatorios"><i class="fa-solid fa-chart-column"></i> Relatórios</a>
            <a href="<?php echo BASE_URL; ?>/configuracoes"><i class="fa-solid fa-gear"></i> Configurações</a>
            <a href="<?php echo BASE_URL; ?>/auth/logout"><i class="fa-solid fa-arrow-right-from-bracket"></i> Sair</a>
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