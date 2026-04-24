<?php
session_start();

/*
|--------------------------------------------------------------------------
| Controle de autenticação
|--------------------------------------------------------------------------
| A página de categorias só pode ser acessada por usuários autenticados.
| Caso contrário, o sistema redireciona para a tela de login.
*/
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/Conexao.php';

try {
    /*
    |--------------------------------------------------------------------------
    | Instância de banco
    |--------------------------------------------------------------------------
    | Conexão PDO centralizada pela classe Conexao.
    */
    $pdo = Conexao::getInstancia();
    $userId = (int)($_SESSION['user_id'] ?? 0);

    /*
    |--------------------------------------------------------------------------
    | Estatísticas das categorias
    |--------------------------------------------------------------------------
    | Contabiliza total, categorias de receita e categorias de despesa.
    */
    $statsStmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN tipo = 'RECEITA' THEN 1 ELSE 0 END) AS receitas,
            SUM(CASE WHEN tipo = 'DESPESA' THEN 1 ELSE 0 END) AS despesas
        FROM categorias
        WHERE id_usuario = :uid
    ");
    $statsStmt->execute([':uid' => $userId]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Lista de categorias do usuário
    |--------------------------------------------------------------------------
    | Ordena por tipo e nome para melhor organização visual.
    */
    $catStmt = $pdo->prepare("
        SELECT id, nome, tipo, criado_em, atualizado_em
        FROM categorias
        WHERE id_usuario = :uid
        ORDER BY tipo ASC, nome ASC
    ");
    $catStmt->execute([':uid' => $userId]);
    $categorias = $catStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    die('Erro ao carregar categorias: ' . $e->getMessage());
}

/*
|--------------------------------------------------------------------------
| Funções auxiliares
|--------------------------------------------------------------------------
| Centralizam formatação de tipo e data.
*/
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
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
.topbar .welcome h2{margin:0 0 6px;font-size:28px;}
.topbar .welcome p{margin:0;color:#6b7280;}
.actions{display:flex;gap:10px;flex-wrap:wrap;}
.chip,.btn-top{border:0;border-radius:12px;padding:12px 16px;display:inline-flex;align-items:center;gap:10px;font-weight:600;}
.btn-top{background:linear-gradient(135deg,#288CFA,#1c7ad0);color:#fff;box-shadow:0 10px 18px rgba(40,140,250,.22);cursor:pointer;transition:.2s;}
.btn-top:hover{transform:translateY(-2px);filter:brightness(1.05);}
.chip{background:#fff;color:#374151;box-shadow:0 8px 20px rgba(15,23,42,.06);}
.grid-kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-bottom:20px;}
.card{background:#fff;border-radius:18px;box-shadow:0 10px 24px rgba(15,23,42,.06);padding:20px;border:1px solid #eef2f7;transition:transform .25s ease,box-shadow .25s ease;animation:fadeInUp .5s ease backwards;}
.card:hover{transform:translateY(-6px);box-shadow:0 16px 36px rgba(15,23,42,.1);}
.kpi{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;}
.kpi .icon{width:48px;height:48px;border-radius:14px;display:grid;place-items:center;background:#eef6ff;color:#288CFA;font-size:20px;}
.kpi h3{margin:0 0 8px;font-size:14px;color:#6b7280;font-weight:600;}
.kpi strong{font-size:24px;color:#111827;display:block;}
.kpi small{color:#10b981;font-weight:600;}
.section-title{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:12px;flex-wrap:wrap;}
.section-title h3{margin:0;font-size:18px;}
.muted{color:#6b7280;font-size:13px;}
.table-head,.list-item{display:grid;grid-template-columns:1fr 170px 170px auto;gap:12px;align-items:center;}
.table-head{padding:0 14px 10px;color:#6b7280;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;}
.list{display:grid;gap:12px;}
.list-item{padding:14px;border-radius:14px;background:linear-gradient(180deg,#fbfdff 0%,#f8fbff 100%);border:1px solid #eef2f7;transition:transform .2s ease,box-shadow .2s ease,background .2s ease;}
.list-item:hover{transform:translateY(-2px);box-shadow:0 8px 18px rgba(15,23,42,.06);background:#fff;}
.pill{padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;}
.pill.ok{background:#dcfce7;color:#166534;}
.pill.warn{background:#fef3c7;color:#92400e;}
.pill.bad{background:#fee2e2;color:#991b1b;}
.pill.gray{background:#e5e7eb;color:#374151;}
.user-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;}
.btn-action{border:0;border-radius:10px;padding:9px 12px;color:#fff;text-decoration:none;font-size:13px;font-weight:700;display:inline-flex;align-items:center;gap:8px;cursor:pointer;transition:transform .2s ease,filter .2s ease;}
.btn-action:hover{transform:translateY(-1px);filter:brightness(1.05);}
.btn-edit{background:#2563eb;}
.btn-del{background:#dc2626;}
.btn-save{background:linear-gradient(135deg,#288CFA,#1c7ad0);border:none;color:#fff;padding:10px 20px;border-radius:8px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:8px;transition:.2s;}
.btn-secondary{background:#fff;border:1px solid #d1d5db;color:#374151;padding:10px 16px;border-radius:8px;font-weight:600;cursor:pointer;}
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(6px);z-index:1000;justify-content:center;align-items:center;padding:20px;opacity:0;transition:opacity .25s ease;}
.modal-backdrop.active{display:flex;opacity:1;}
.modal{background:#fff;border-radius:18px;width:100%;max-width:640px;box-shadow:0 24px 60px rgba(0,0,0,.22);max-height:90vh;display:flex;flex-direction:column;overflow:hidden;animation:modalIn .28s ease both;}
.modal-header{padding:20px 24px;border-bottom:1px solid #eef2f7;display:flex;justify-content:space-between;align-items:center;}
.modal-body{padding:24px;overflow-y:auto;flex:1;}
.modal-footer{padding:20px 24px;border-top:1px solid #eef2f7;display:flex;justify-content:flex-end;gap:12px;background:#f9fbff;}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.field{display:flex;flex-direction:column;gap:6px;}
.field.full{grid-column:1 / -1;}
.field label{font-size:14px;font-weight:600;color:#374151;}
.field input,.field select,.field textarea{padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;width:100%;font-family:inherit;outline:none;transition:border-color .2s ease,box-shadow .2s ease;}
.field input:focus,.field select:focus,.field textarea:focus{border-color:#288CFA;box-shadow:0 0 0 3px rgba(40,140,250,.12);}
@keyframes fadeInUp{from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:translateY(0)}}
@keyframes modalIn{from{opacity:0;transform:translateY(18px) scale(.96)}to{opacity:1;transform:translateY(0) scale(1)}}
@media (max-width:1200px){.grid-kpis{grid-template-columns:repeat(2,minmax(0,1fr));}.dashboard-shell{grid-template-columns:1fr;}.sidebar{height:auto;position:relative;}.table-head{display:none;}.list-item{grid-template-columns:1fr;}.user-actions{justify-content:flex-start;}}
@media (max-width:640px){.grid-kpis{grid-template-columns:1fr;}.content{padding:16px;}.topbar{flex-direction:column;align-items:flex-start;}.form-grid{grid-template-columns:1fr;}}
</style>
</head>
<body class="dashboard-page">
<div class="dashboard-shell">
<aside class="sidebar">
<div class="brand">
    <img src="assets/images/logoSemFundo.png" alt="Life Finance">
    <div>
        <h1>Life Finance</h1>
        <p>Categorias</p>
    </div>
</div>
<nav class="menu">
    <a href="dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
    <a href="movimentacoes.php"><i class="fa-solid fa-right-left"></i> Movimentações</a>
    <a href="contas.php"><i class="fa-solid fa-wallet"></i> Contas</a>
    <a href="categorias.php" class="active"><i class="fa-solid fa-tags"></i> Categorias</a>
    <a href="metas.php"><i class="fa-solid fa-bullseye"></i> Metas</a>
    <a href="relatorios.php"><i class="fa-solid fa-chart-column"></i> Relatórios</a>
    <a href="configuracoes.php"><i class="fa-solid fa-gear"></i> Configurações</a>
    <a href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Sair</a>
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
                   href="excluir_categoria.php?id=<?php echo (int)$cat['id']; ?>"
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

        <form method="POST" action="salvar_categoria.php">
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

        <form method="POST" action="editar_categoria.php">
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