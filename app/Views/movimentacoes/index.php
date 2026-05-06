<?php
function money($v) {
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}

function fmtDate($v) {
    if (empty($v)) return '-';
    try {
        return (new DateTime($v))->format('d/m/Y');
    } catch(Throwable $e) {
        return '-';
    }
}

function badgeTipo($tipo) {
    return $tipo === 'RECEITA' ? 'ok' : ($tipo === 'DESPESA' ? 'bad' : 'warn');
}

function badgeStatus($status) {
    return $status === 'PAGO' ? 'ok' : ($status === 'PENDENTE' ? 'warn' : 'gray');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Life Finance | Movimentações</title>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/movimentacoes.css">
</head>
</head>
<body class="dashboard-page">
<div class="dashboard-shell">
<aside class="sidebar">
<div class="brand">
    <img src="<?php echo BASE_URL; ?>/assets/images/logoSemFundo.png" alt="Life Finance">
    <div>
        <h1>Life Finance</h1>
        <p>Movimentações</p>
    </div>
</div>
<nav class="menu">
<a href="<?php echo BASE_URL; ?>/dashboard"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
<a href="<?php echo BASE_URL; ?>/movimentacoes" class="active"><i class="fa-solid fa-right-left"></i> Movimentações</a>
<a href="<?php echo BASE_URL; ?>/contas"><i class="fa-solid fa-wallet"></i> Contas</a>
<a href="<?php echo BASE_URL; ?>/categorias"><i class="fa-solid fa-tags"></i> Categorias</a>
<a href="<?php echo BASE_URL; ?>/auth/logout"><i class="fa-solid fa-arrow-right-from-bracket"></i> Sair</a>
</nav>
</aside>

<main class="content">
<div class="topbar">
    <div class="welcome">
        <h2>Movimentações financeiras</h2>
        <p>Cadastre entradas, saídas e transferências do seu fluxo financeiro.</p>
    </div>
    <div class="actions">
        <div class="chip"><i class="fa-solid fa-calendar-day"></i> <?php echo date('m/Y'); ?></div>
        <button class="btn-top" onclick="openCreateModal()"><i class="fa-solid fa-plus"></i> Nova movimentação</button>
    </div>
</div>

<section class="grid-kpis">
    <div class="card kpi">
        <div><h3>Saldo do mês</h3><strong><?php echo money($saldo); ?></strong><small><?php echo $saldo >= 0 ? 'Saldo positivo' : 'Saldo negativo'; ?></small></div>
        <div class="icon"><i class="fa-solid fa-wallet"></i></div>
    </div>
    <div class="card kpi">
        <div><h3>Receitas</h3><strong><?php echo money($dados['receitas'] ?? 0); ?></strong><small><?php echo (int)($dados['pagas'] ?? 0); ?> lançamentos pagos</small></div>
        <div class="icon"><i class="fa-solid fa-arrow-trend-up"></i></div>
    </div>
    <div class="card kpi">
        <div><h3>Despesas</h3><strong><?php echo money($dados['despesas'] ?? 0); ?></strong><small><?php echo (int)($dados['pendentes'] ?? 0); ?> pendências</small></div>
        <div class="icon"><i class="fa-solid fa-arrow-trend-down"></i></div>
    </div>
    <div class="card kpi">
        <div><h3>Transferências</h3><strong><?php echo money($dados['transferencias'] ?? 0); ?></strong><small><?php echo (int)($dados['total'] ?? 0); ?> no mês</small></div>
        <div class="icon"><i class="fa-solid fa-right-left"></i></div>
    </div>
</section>

<section class="card" style="margin-top:16px;">
    <div class="section-title">
        <div>
            <h3>Últimas movimentações</h3>
            <div class="muted">Entradas, saídas e transferências recentes</div>
        </div>
        <span class="pill gray"><?php echo count($movimentacoes); ?> registros</span>
    </div>

    <div class="table-head">
        <div>Data</div>
        <div>Tipo</div>
        <div>Valor</div>
        <div>Descrição</div>
        <div>Conta</div>
        <div>Status</div>
        <div>Categoria</div>
        <div>Ações</div>
    </div>

    <div class="list">
    <?php foreach ($movimentacoes as $m): ?>
        <div class="list-item">
            <div class="muted"><?php echo fmtDate($m['ocorreu_em']); ?></div>
            <div><span class="pill <?php echo badgeTipo($m['tipo']); ?>"><?php echo htmlspecialchars($m['tipo']); ?></span></div>
            <div><strong><?php echo money($m['valor']); ?></strong></div>
            <div><strong><?php echo htmlspecialchars($m['descricao']); ?></strong> <span class="muted"><?php echo htmlspecialchars($m['codigo_moeda'] ?? 'BRL'); ?></span></div>
            <div class="muted"><?php echo htmlspecialchars($m['conta_nome'] ?? '-'); ?></div>
            <div><span class="pill <?php echo badgeStatus($m['status']); ?>"><?php echo htmlspecialchars($m['status']); ?></span></div>
            <div class="muted"><?php echo htmlspecialchars($m['categoria_nome'] ?? '-'); ?></div>
            <div class="user-actions">
                <a class="btn-action btn-edit" href="#" onclick='openEditModal(<?php echo json_encode($m, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>); return false;'><i class="fa-solid fa-pen"></i> Editar</a>
                <a class="btn-action btn-del" href="<?php echo BASE_URL; ?>/movimentacoes/delete?id=<?php echo (int)$m['id']; ?>" onclick="return confirm('Excluir esta movimentação?');"><i class="fa-solid fa-trash"></i> Excluir</a>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
</section>
</main>
</div>

<div class="modal-backdrop" id="movModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Nova movimentação</h3>
            <button class="modal-close" type="button" onclick="closeModal()" style="background:none;border:none;font-size:20px;color:#6b7280;cursor:pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form id="movForm" method="POST" action="<?php echo BASE_URL; ?>/movimentacoes/store">
            <input type="hidden" name="id" id="mov_id" value="">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="field">
                        <label for="tipo">Tipo</label>
                        <select id="tipo" name="tipo" required onchange="toggleTipo()">
                            <option value="">Selecione</option>
                            <option value="RECEITA">Receita</option>
                            <option value="DESPESA">Despesa</option>
                            <option value="TRANSFERENCIA">Transferência</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="valor">Valor</label>
                        <input type="number" step="0.01" id="valor" name="valor" required placeholder="0.00">
                    </div>

                    <div class="field">
                        <label for="id_conta">Conta origem</label>
                        <select id="id_conta" name="id_conta" required>
                            <option value="">Selecione</option>
                            <?php foreach ($contas as $c): ?>
                                <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field" id="box_categoria">
                        <label for="id_categoria">Categoria</label>
                        <select id="id_categoria" name="id_categoria">
                            <option value="">Selecione</option>
                        </select>
                    </div>

                    <div class="field" id="box_destino" style="display:none;">
                        <label for="id_conta_destino">Conta destino</label>
                        <select id="id_conta_destino" name="id_conta_destino">
                            <option value="">Selecione</option>
                            <?php foreach ($contas as $c): ?>
                                <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['nome']); ?></option>
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
                        <input type="text" id="descricao" name="descricao" required placeholder="Ex.: Salário, mercado, aluguel...">
                    </div>

                    <div class="field full">
                        <label for="observacao">Observação</label>
                        <textarea id="observacao" name="observacao" rows="3" placeholder="Detalhes adicionais..."></textarea>
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
                <button type="button" class="btn-secondary" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(){ document.getElementById('movModal').classList.add('active'); }
function closeModal(){ document.getElementById('movModal').classList.remove('active'); }

function carregarCategorias(tipo) {
    const select = document.getElementById('id_categoria');
    if (!select) return;

    const t = (tipo || '').trim().toUpperCase();

    if (t !== 'RECEITA' && t !== 'DESPESA') {
        select.innerHTML = '<option value="">Selecione</option>';
        select.required = false;
        return;
    }

    select.disabled = true;
    select.innerHTML = '<option value="">Carregando...</option>';

    fetch('<?php echo BASE_URL; ?>/categorias/porTipo?tipo=' + encodeURIComponent(t), { cache: 'no-store' })
        .then(r => r.text())
        .then(html => {
            select.innerHTML = '<option value="">Selecione</option>' + (html || '');
            select.required = true;
        })
        .catch(() => {
            select.innerHTML = '<option value="">Selecione</option>';
            select.required = false;
        })
        .finally(() => {
            select.disabled = false;
        });
}

function toggleTipo(){
    const tipo = document.getElementById('tipo').value;
    const boxCategoria = document.getElementById('box_categoria');
    const boxDestino = document.getElementById('box_destino');
    const cat = document.getElementById('id_categoria');
    const dest = document.getElementById('id_conta_destino');

    if(tipo === 'TRANSFERENCIA'){
        boxDestino.style.display = 'flex';
        boxCategoria.style.display = 'none';
        dest.required = true;
        cat.required = false;
        cat.value = '';
    } else {
        boxDestino.style.display = 'none';
        boxCategoria.style.display = 'flex';
        dest.required = false;
        dest.value = '';

        if (tipo === 'RECEITA' || tipo === 'DESPESA') {
            carregarCategorias(tipo);
        } else {
            cat.innerHTML = '<option value="">Selecione</option>';
            cat.required = false;
        }
    }
}

function openEditModal(m) {
    document.getElementById('movForm').action = '<?php echo BASE_URL; ?>/movimentacoes/update';
    document.getElementById('movModal').querySelector('.modal-header h3').innerText = 'Editar movimentação';
    document.getElementById('mov_id').value = m.id;
    document.getElementById('tipo').value = m.tipo;
    document.getElementById('valor').value = parseFloat(m.valor).toFixed(2);
    document.getElementById('id_conta').value = m.id_conta || '';
    document.getElementById('id_conta_destino').value = m.id_conta_destino || '';
    document.getElementById('ocorreu_em').value = m.ocorreu_em ? m.ocorreu_em.substring(0,10) : '';
    document.getElementById('vence_em').value = m.vence_em ? m.vence_em.substring(0,10) : '';
    document.getElementById('descricao').value = m.descricao || '';
    document.getElementById('observacao').value = m.observacao || '';
    document.getElementById('status').value = m.status || '';
    document.getElementById('codigo_moeda').value = m.codigo_moeda || 'BRL';

    toggleTipo();
    if (m.tipo === 'RECEITA' || m.tipo === 'DESPESA') {
        const select = document.getElementById('id_categoria');
        select.disabled = true;
        select.innerHTML = '<option value="">Carregando...</option>';
        fetch('<?php echo BASE_URL; ?>/categorias/porTipo?tipo=' + encodeURIComponent(m.tipo), { cache: 'no-store' })
            .then(r => r.text())
            .then(html => {
                select.innerHTML = '<option value="">Selecione</option>' + (html || '');
                select.value = m.id_categoria || '';
                select.required = true;
            })
            .finally(() => {
                select.disabled = false;
            });
    }
    openModal();
}

function openCreateModal() {
    document.getElementById('movForm').action = '<?php echo BASE_URL; ?>/movimentacoes/store';
    document.getElementById('movModal').querySelector('.modal-header h3').innerText = 'Nova movimentação';
    document.getElementById('movForm').reset();
    document.getElementById('mov_id').value = '';
    document.getElementById('ocorreu_em').value = '<?php echo date('Y-m-d'); ?>';
    toggleTipo();
    openModal();
}

window.addEventListener('keydown', e => { if(e.key === 'Escape') closeModal(); });
document.getElementById('movModal').addEventListener('click', e => {
    if(e.target === e.currentTarget) closeModal();
});
</script>
</body>
</html>