<?php
function badgeTipo($tipo){
    return $tipo === 'RECEITA' ? 'ok' : ($tipo === 'DESPESA' ? 'bad' : 'warn');
}

function fmtDate($v){
    if (empty($v)) return '-';
    try {
        return (new DateTime($v))->format('d/m/Y H:i');
    } catch(Throwable $e){
        return '-';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Life Finance | Categorias</title>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/categorias.css">
</head>
<body class="dashboard-page">
<div class="dashboard-shell">
<aside class="sidebar">
<div class="brand">
    <img src="<?php echo BASE_URL; ?>/assets/images/logoSemFundo.png" alt="Life Finance">
    <div>
        <h1>Life Finance</h1>
        <p>Categorias</p>
    </div>
</div>
<nav class="menu">
    <a href="<?php echo BASE_URL; ?>/dashboard"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
    <a href="<?php echo BASE_URL; ?>/movimentacoes"><i class="fa-solid fa-right-left"></i> Movimentações</a>
    <a href="<?php echo BASE_URL; ?>/contas"><i class="fa-solid fa-wallet"></i> Contas</a>
    <a href="<?php echo BASE_URL; ?>/categorias" class="active"><i class="fa-solid fa-tags"></i> Categorias</a>
    <a href="<?php echo BASE_URL; ?>/metas"><i class="fa-solid fa-bullseye"></i> Metas</a>
    <a href="<?php echo BASE_URL; ?>/relatorios"><i class="fa-solid fa-chart-column"></i> Relatórios</a>
    <a href="<?php echo BASE_URL; ?>/configuracoes"><i class="fa-solid fa-gear"></i> Configurações</a>
    <a href="<?php echo BASE_URL; ?>/auth/logout"><i class="fa-solid fa-arrow-right-from-bracket"></i> Sair</a>
</nav>
</aside>

<main class="content">
<div class="topbar">
    <div class="welcome">
        <h2>Gerenciar categorias</h2>
        <p>Crie e organize categorias para receitas e despesas.</p>
    </div>
    <div class="actions">
        <div class="chip"><i class="fa-solid fa-layer-group"></i> <?php echo (int)($stats['total'] ?? 0); ?> categorias</div>
        <button class="btn-top" onclick="openModal()"><i class="fa-solid fa-plus"></i> Nova categoria</button>
    </div>
</div>

<section class="grid-kpis">
    <div class="card kpi">
        <div><h3>Total</h3><strong><?php echo (int)($stats['total'] ?? 0); ?></strong><small>Cadastradas no sistema</small></div>
        <div class="icon"><i class="fa-solid fa-tags"></i></div>
    </div>
    <div class="card kpi">
        <div><h3>Receitas</h3><strong><?php echo (int)($stats['receitas'] ?? 0); ?></strong><small>Tipos de entrada</small></div>
        <div class="icon"><i class="fa-solid fa-arrow-trend-up"></i></div>
    </div>
    <div class="card kpi">
        <div><h3>Despesas</h3><strong><?php echo (int)($stats['despesas'] ?? 0); ?></strong><small>Tipos de saída</small></div>
        <div class="icon"><i class="fa-solid fa-arrow-trend-down"></i></div>
    </div>
</section>

<section class="card" style="margin-top:16px;">
    <div class="section-title">
        <div>
            <h3>Lista de categorias</h3>
            <div class="muted">Edite ou remova categorias conforme necessário</div>
        </div>
        <span class="pill gray"><?php echo count($categorias); ?> registros</span>
    </div>

    <div class="table-head">
        <div>Categoria</div>
        <div>Tipo</div>
        <div>Criado</div>
        <div>Ações</div>
    </div>

    <div class="list">
        <?php foreach ($categorias as $cat): ?>
        <div class="list-item">
            <div>
                <strong><?php echo htmlspecialchars($cat['nome']); ?></strong>
                <div class="muted">ID #<?php echo (int)$cat['id']; ?></div>
            </div>
            <div><span class="pill <?php echo badgeTipo($cat['tipo']); ?>"><?php echo htmlspecialchars($cat['tipo']); ?></span></div>
            <div class="muted"><?php echo fmtDate($cat['criado_em']); ?></div>
            <div class="user-actions">
                <button
                    type="button"
                    class="btn-action btn-edit"
                    onclick="openEditModal(this)"
                    data-id="<?php echo (int)$cat['id']; ?>"
                    data-nome="<?php echo htmlspecialchars($cat['nome'], ENT_QUOTES); ?>"
                    data-tipo="<?php echo htmlspecialchars($cat['tipo'], ENT_QUOTES); ?>"
                >
                    <i class="fa-solid fa-pen"></i> Editar
                </button>

                <a class="btn-action btn-del"
                   href="<?php echo BASE_URL; ?>/categorias/delete?id=<?php echo (int)$cat['id']; ?>"
                   onclick="return confirm('Excluir esta categoria?');">
                    <i class="fa-solid fa-trash"></i> Excluir
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
</main>
</div>

<div class="modal-backdrop" id="catModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Nova categoria</h3>
            <button class="modal-close" type="button" onclick="closeModal()" style="background:none;border:none;font-size:20px;color:#6b7280;cursor:pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="<?php echo BASE_URL; ?>/categorias/store">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="field full">
                        <label for="nome">Nome</label>
                        <input type="text" id="nome" name="nome" required placeholder="Ex.: Alimentação">
                    </div>
                    <div class="field">
                        <label for="tipo">Tipo</label>
                        <select id="tipo" name="tipo" required>
                            <option value="">Selecione</option>
                            <option value="RECEITA">Receita</option>
                            <option value="DESPESA">Despesa</option>
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

<div class="modal-backdrop" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Editar categoria</h3>
            <button class="modal-close" type="button" onclick="closeEditModal()" style="background:none;border:none;font-size:20px;color:#6b7280;cursor:pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="<?php echo BASE_URL; ?>/categorias/update">
            <div class="modal-body">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-grid">
                    <div class="field full">
                        <label for="edit_nome">Nome</label>
                        <input type="text" id="edit_nome" name="nome" required>
                    </div>
                    <div class="field">
                        <label for="edit_tipo">Tipo</label>
                        <select id="edit_tipo" name="tipo" required>
                            <option value="RECEITA">Receita</option>
                            <option value="DESPESA">Despesa</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeEditModal()">Cancelar</button>
                <button type="submit" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> Salvar alterações</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(){ document.getElementById('catModal').classList.add('active'); }
function closeModal(){ document.getElementById('catModal').classList.remove('active'); }

function openEditModal(btn){
    const d = btn.dataset;
    document.getElementById('edit_id').value = d.id || '';
    document.getElementById('edit_nome').value = d.nome || '';
    document.getElementById('edit_tipo').value = d.tipo || 'DESPESA';
    document.getElementById('editModal').classList.add('active');
}
function closeEditModal(){ document.getElementById('editModal').classList.remove('active'); }

window.addEventListener('keydown', e => {
    if(e.key === 'Escape'){
        closeModal();
        closeEditModal();
    }
});

document.getElementById('catModal').addEventListener('click', e => {
    if(e.target === e.currentTarget) closeModal();
});
document.getElementById('editModal').addEventListener('click', e => {
    if(e.target === e.currentTarget) closeEditModal();
});
</script>
</body>
</html>