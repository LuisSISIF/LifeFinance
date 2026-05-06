<?php
function money($v) {
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}

function fmtDate($v) {
    if (empty($v)) return '-';
    try {
        return (new DateTime($v))->format('d/m/Y');
    } catch (Throwable $e) {
        return '-';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Life Finance | Metas</title>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/metas.css">
</head>
<body class="dashboard-page">
<div class="dashboard-shell">
<aside class="sidebar">
<div class="brand">
    <img src="<?php echo BASE_URL; ?>/assets/images/logoSemFundo.png" alt="Life Finance">
    <div><h1>Life Finance</h1><p>Metas financeiras</p></div>
</div>
<nav class="menu">
<a href="<?php echo BASE_URL; ?>/dashboard"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
<a href="<?php echo BASE_URL; ?>/movimentacoes"><i class="fa-solid fa-right-left"></i> Movimentações</a>
<a href="<?php echo BASE_URL; ?>/contas"><i class="fa-solid fa-wallet"></i> Contas</a>
<a href="<?php echo BASE_URL; ?>/categorias"><i class="fa-solid fa-tags"></i> Categorias</a>
<a href="<?php echo BASE_URL; ?>/metas" class="active"><i class="fa-solid fa-bullseye"></i> Metas</a>
<a href="<?php echo BASE_URL; ?>/relatorios"><i class="fa-solid fa-chart-column"></i> Relatórios</a>
<a href="<?php echo BASE_URL; ?>/auth/logout"><i class="fa-solid fa-arrow-right-from-bracket"></i> Sair</a>
</nav>
</aside>

<main class="content">
<div class="topbar">
    <div class="welcome">
        <h2>Metas financeiras</h2>
        <p>Acompanhe seus objetivos e o progresso de cada meta.</p>
    </div>
    <div class="actions">
        <div class="chip"><i class="fa-solid fa-bullseye"></i> <?php echo count($metas); ?> metas</div>
        <button class="btn-top" onclick="openModal()"><i class="fa-solid fa-plus"></i> Nova meta</button>
    </div>
</div>

<section class="grid-kpis">
    <div class="card kpi"><div><h3>Total de metas</h3><strong><?php echo count($metas); ?></strong><small>Objetivos cadastrados</small></div><div class="icon"><i class="fa-solid fa-bullseye"></i></div></div>
    <div class="card kpi"><div><h3>Valor meta</h3><strong><?php echo money(array_sum(array_column($metas, 'valor_meta'))); ?></strong><small>Somatório das metas</small></div><div class="icon"><i class="fa-solid fa-piggy-bank"></i></div></div>
    <div class="card kpi"><div><h3>Valor acumulado</h3><strong><?php echo money(array_sum(array_column($metas, 'valor_atual'))); ?></strong><small>Total já guardado</small></div><div class="icon"><i class="fa-solid fa-sack-dollar"></i></div></div>
    <div class="card kpi"><div><h3>Progresso médio</h3><strong>
<?php
$progressoMedio = 0;
if (count($metas) > 0) {
    $sum = 0;
    foreach ($metas as $m) {
        $sum += ($m['valor_meta'] > 0) ? min(100, round(($m['valor_atual'] / $m['valor_meta']) * 100)) : 0;
    }
    $progressoMedio = round($sum / count($metas));
}
echo $progressoMedio . '%';
?>
</strong><small>Andamento geral</small></div><div class="icon"><i class="fa-solid fa-chart-line"></i></div></div>
</section>

<section class="card" style="margin-top:16px;">
    <div class="section-title">
        <div>
            <h3>Lista de metas</h3>
            <div class="muted">Edite, acompanhe ou exclua suas metas</div>
        </div>
    </div>

    <div class="list">
        <?php if (empty($metas)): ?>
            <div class="muted">Nenhuma meta cadastrada ainda.</div>
        <?php else: ?>
            <?php foreach ($metas as $m): 
                $pct = $m['valor_meta'] > 0 ? min(100, round(($m['valor_atual'] / $m['valor_meta']) * 100)) : 0;
                $ativaTxt = !empty($m['ativa']) ? 'Ativa' : 'Inativa';
                $pillClass = !empty($m['ativa']) ? 'ok' : 'gray';
            ?>
            <div class="list-item">
                <div>
                    <strong><?php echo htmlspecialchars($m['nome']); ?></strong>
                    <div class="muted">
                        Meta criada em <?php echo fmtDate($m['criado_em']); ?>
                        <?php if (!empty($m['data_limite'])): ?> • Limite <?php echo fmtDate($m['data_limite']); ?><?php endif; ?>
                    </div>
                    <?php if (!empty($m['descricao'])): ?>
                        <div class="muted"><?php echo htmlspecialchars($m['descricao']); ?></div>
                    <?php endif; ?>
                </div>
                <div>
                    <strong><?php echo money($m['valor_meta']); ?></strong>
                    <div class="muted"><?php echo htmlspecialchars($m['codigo_moeda'] ?? 'BRL'); ?></div>
                </div>
                <div>
                    <strong><?php echo money($m['valor_atual']); ?></strong>
                    <div class="muted">Acumulado</div>
                </div>
                <div>
                    <div class="progress"><span style="width:<?php echo $pct; ?>%"></span></div>
                    <div class="muted" style="margin-top:6px;"><?php echo $pct; ?>%</div>
                </div>
                <div><span class="pill <?php echo $pillClass; ?>"><?php echo $ativaTxt; ?></span></div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
                    <button class="btn-action btn-edit" type="button" onclick='openEditModal(<?php echo json_encode($m, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>)'><i class="fa-solid fa-pen"></i> Editar</button>
                    <a class="btn-action btn-del" href="<?php echo BASE_URL; ?>/metas/delete?id=<?php echo (int)$m['id']; ?>" onclick="return confirm('Excluir esta meta?');"><i class="fa-solid fa-trash"></i> Excluir</a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
</main>
</div>

<div class="modal-backdrop" id="metaModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">Nova meta</h3>
            <button type="button" class="btn-secondary" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="<?php echo BASE_URL; ?>/metas/store">
            <input type="hidden" name="acao" id="acao" value="salvar">
            <input type="hidden" name="id" id="id" value="">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="field full">
                        <label for="nome">Nome da meta</label>
                        <input type="text" id="nome" name="nome" required placeholder="Ex.: Reserva de emergência">
                    </div>
                    <div class="field full">
                        <label for="descricao">Descrição</label>
                        <textarea id="descricao" name="descricao" rows="3" placeholder="Detalhe o objetivo da meta..."></textarea>
                    </div>
                    <div class="field">
                        <label for="valor_meta">Valor meta</label>
                        <input type="number" step="0.01" id="valor_meta" name="valor_meta" required placeholder="0,00">
                    </div>
                    <div class="field">
                        <label for="valor_atual">Valor atual</label>
                        <input type="number" step="0.01" id="valor_atual" name="valor_atual" value="0" required placeholder="0,00">
                    </div>
                    <div class="field">
                        <label for="codigo_moeda">Moeda</label>
                        <select id="codigo_moeda" name="codigo_moeda">
                            <option value="BRL">BRL</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="data_limite">Data limite</label>
                        <input type="date" id="data_limite" name="data_limite">
                    </div>
                    <div class="field">
                        <label><input type="checkbox" id="ativa" name="ativa" checked> Ativa</label>
                        <label><input type="checkbox" id="compartilhada" name="compartilhada"> Compartilhada</label>
                        <label><input type="checkbox" id="reserva_emergencia" name="reserva_emergencia"> Reserva de emergência</label>
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
function openModal(){
    document.getElementById('modalTitle').textContent = 'Nova meta';
    document.getElementById('acao').value = 'salvar';
    document.getElementById('id').value = '';
    document.querySelector('#metaModal form').reset();
    document.getElementById('valor_atual').value = '0';
    document.getElementById('ativa').checked = true;
    document.getElementById('metaModal').classList.add('active');
}
function closeModal(){ document.getElementById('metaModal').classList.remove('active'); }
function openEditModal(meta){
    document.getElementById('modalTitle').textContent = 'Editar meta';
    document.getElementById('acao').value = 'editar';
    document.getElementById('id').value = meta.id || '';
    document.getElementById('nome').value = meta.nome || '';
    document.getElementById('descricao').value = meta.descricao || '';
    document.getElementById('valor_meta').value = Number(meta.valor_meta || 0).toFixed(2);
    document.getElementById('valor_atual').value = Number(meta.valor_atual || 0).toFixed(2);
    document.getElementById('codigo_moeda').value = meta.codigo_moeda || 'BRL';
    document.getElementById('data_limite').value = meta.data_limite || '';
    document.getElementById('ativa').checked = !!Number(meta.ativa || 0);
    document.getElementById('compartilhada').checked = !!Number(meta.compartilhada || 0);
    document.getElementById('reserva_emergencia').checked = !!Number(meta.reserva_emergencia || 0);
    document.getElementById('metaModal').classList.add('active');
}
window.addEventListener('keydown', e => { if(e.key === 'Escape') closeModal(); });
document.getElementById('metaModal').addEventListener('click', e => { if(e.target === e.currentTarget) closeModal(); });
</script>
</body>
</html>