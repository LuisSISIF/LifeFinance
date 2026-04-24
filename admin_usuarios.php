<?php
session_start();

/*
|--------------------------------------------------------------------------
| Proteção de acesso
|--------------------------------------------------------------------------
| Esta página é exclusiva para administradores autenticados.
| Se o usuário não estiver logado ou não possuir perfil ADMIN,
| ele é redirecionado para o dashboard.
*/
if (
    !isset($_SESSION['authenticated']) ||
    $_SESSION['authenticated'] !== true ||
    (($_SESSION['role'] ?? 'USER') !== 'ADMIN')
) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/Conexao.php';

try {
    /*
    |--------------------------------------------------------------------------
    | Conexão com o banco de dados
    |--------------------------------------------------------------------------
    | A instância PDO é obtida através da classe Conexao.
    */
    $pdo = Conexao::getInstancia();

    /*
    |--------------------------------------------------------------------------
    | Estatísticas gerais do painel
    |--------------------------------------------------------------------------
    | Agrupa os principais indicadores da tabela usuarios.
    */
    $stats = $pdo->query("
        SELECT
            COUNT(*) AS total_usuarios,
            SUM(CASE WHEN status = 'ATIVO' THEN 1 ELSE 0 END) AS ativos,
            SUM(CASE WHEN status = 'INATIVO' THEN 1 ELSE 0 END) AS inativos,
            SUM(CASE WHEN status = 'EXCLUIDO' THEN 1 ELSE 0 END) AS excluidos,
            SUM(CASE WHEN email_verificado_em IS NOT NULL THEN 1 ELSE 0 END) AS verificados,
            SUM(CASE WHEN forcar_2fa = 1 THEN 1 ELSE 0 END) AS com_2fa
        FROM usuarios
    ")->fetch();

    /*
    |--------------------------------------------------------------------------
    | Listagem de usuários
    |--------------------------------------------------------------------------
    | Retorna os dados necessários para exibição no painel administrativo.
    */
    $usuarios = $pdo->query("
        SELECT
            id,
            email,
            status,
            email_verificado_em,
            forcar_2fa,
            criado_em,
            atualizado_em,
            COALESCE(role,'USER') AS role
        FROM usuarios
        ORDER BY criado_em DESC
    ")->fetchAll();
} catch (Throwable $e) {
    die('Erro ao carregar admin: ' . $e->getMessage());
}

/*
|--------------------------------------------------------------------------
| Funções auxiliares de apresentação
|--------------------------------------------------------------------------
| Centralizam a formatação de status e datas para manter a view limpa.
*/
function badgeStatus($status) {
    return match ($status) {
        'ATIVO' => 'ok',
        'INATIVO' => 'warn',
        'EXCLUIDO' => 'bad',
        default => 'warn'
    };
}

function fmtDate($v) {
    if (empty($v)) return '-';

    try {
        return (new DateTime($v))->format('d/m/Y H:i');
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
<title>Life Finance | Admin</title>
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
.menu a{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:12px;color:#e5e7eb;text-decoration:none;transition:.2s;background:transparent;}
.menu a:hover,.menu a.active{background:rgba(40,140,250,.18);color:#fff;transform:translateX(4px);}
.content{padding:24px;}
.topbar{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:24px;flex-wrap:wrap;}
.topbar .welcome h2{margin:0 0 6px;font-size:28px;}
.topbar .welcome p{margin:0;color:#6b7280;}
.actions{display:flex;gap:10px;flex-wrap:wrap;}
.chip,.btn-top{border:0;border-radius:12px;padding:12px 16px;display:inline-flex;align-items:center;gap:10px;font-weight:600;}
.btn-top{background:linear-gradient(135deg,#288CFA,#1c7ad0);color:#fff;box-shadow:0 10px 18px rgba(40,140,250,.22);cursor:pointer;transition:transform .2s ease,box-shadow .2s ease,filter .2s ease;}
.btn-top:hover{transform:translateY(-2px);filter:brightness(1.05);box-shadow:0 14px 28px rgba(40,140,250,.3);}
.chip{background:#fff;color:#374151;box-shadow:0 8px 20px rgba(15,23,42,.06);}
.grid-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:20px;}
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
.list{display:grid;gap:12px;}
.table-head,.list-item{display:grid;grid-template-columns:minmax(220px,1.5fr) .9fr .7fr .9fr .9fr .9fr auto;gap:12px;align-items:center;}
.table-head{padding:0 14px 10px;color:#6b7280;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;}
.list-item{padding:14px;border-radius:14px;background:linear-gradient(180deg,#fbfdff 0%,#f8fbff 100%);border:1px solid #eef2f7;transition:transform .2s ease,box-shadow .2s ease,background .2s ease;}
.list-item:hover{transform:translateY(-2px);box-shadow:0 8px 18px rgba(15,23,42,.06);background:#fff;}
.list-item strong{display:block;margin-bottom:4px;}
.pill{padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;}
.pill.ok{background:#dcfce7;color:#166534;}
.pill.warn{background:#fef3c7;color:#92400e;}
.pill.bad{background:#fee2e2;color:#991b1b;}
.pill.gray{background:#e5e7eb;color:#374151;}
.user-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;}
.btn-action{border:0;border-radius:10px;padding:9px 12px;color:#fff;text-decoration:none;font-size:13px;font-weight:700;display:inline-flex;align-items:center;gap:8px;cursor:pointer;transition:transform .2s ease,filter .2s ease,box-shadow .2s ease;}
.btn-action:hover{transform:translateY(-1px);filter:brightness(1.05);box-shadow:0 8px 16px rgba(0,0,0,.08);}
.btn-edit{background:#2563eb;}
.btn-block{background:#d97706;}
.btn-del{background:#dc2626;}
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(6px);z-index:1000;justify-content:center;align-items:center;padding:20px;opacity:0;transition:opacity .25s ease;}
.modal-backdrop.active{display:flex;opacity:1;}
.modal{background:#fff;border-radius:18px;width:100%;max-width:640px;box-shadow:0 24px 60px rgba(0,0,0,.22);max-height:90vh;display:flex;flex-direction:column;overflow:hidden;animation:modalIn .28s ease both;}
.modal-header{padding:20px 24px;border-bottom:1px solid #eef2f7;display:flex;justify-content:space-between;align-items:center;}
.modal-header h3{margin:0;font-size:20px;color:#111827;}
.modal-close{background:none;border:none;font-size:20px;color:#6b7280;cursor:pointer;padding:4px;transition:transform .2s ease,color .2s ease;}
.modal-close:hover{transform:rotate(90deg);color:#111827;}
.modal-body{padding:24px;overflow-y:auto;flex:1;}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.field{display:flex;flex-direction:column;gap:6px;}
.field.full{grid-column:1 / -1;}
.field label{font-size:14px;font-weight:600;color:#374151;}
.field input,.field select,.field textarea{padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;width:100%;font-family:inherit;outline:none;transition:border-color .2s ease,box-shadow .2s ease;}
.field input:focus,.field select:focus,.field textarea:focus{border-color:#288CFA;box-shadow:0 0 0 3px rgba(40,140,250,.12);}
.modal-footer{padding:20px 24px;border-top:1px solid #eef2f7;display:flex;justify-content:flex-end;gap:12px;background:#f9fbff;}
.btn-secondary{background:#fff;border:1px solid #d1d5db;color:#374151;padding:10px 16px;border-radius:8px;font-weight:600;cursor:pointer;transition:.2s;}
.btn-secondary:hover{background:#f3f4f6;}
.btn-save{background:linear-gradient(135deg,#288CFA,#1c7ad0);border:none;color:#fff;padding:10px 20px;border-radius:8px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:8px;transition:.2s;}
.btn-save:hover{filter:brightness(1.05);transform:translateY(-1px);}
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
        <p>Administração</p>
    </div>
</div>
<nav class="menu">
    <a href="admin_usuarios.php" class="active"><i class="fa-solid fa-user-shield"></i> Usuários</a>
    <a href="dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
    <a href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Sair</a>
</nav>
</aside>

<main class="content">
<div class="topbar">
    <div class="welcome">
        <h2>Painel administrativo</h2>
        <p>Gerencie usuários, acessos e status do sistema.</p>
    </div>
    <div class="actions">
        <div class="chip"><i class="fa-solid fa-users"></i> <?php echo (int)$stats['total_usuarios']; ?> usuários</div>
        <div class="chip"><i class="fa-solid fa-shield-halved"></i> Admin</div>
    </div>
</div>

<section class="grid-kpis">
    <div class="card kpi"><div><h3>Total</h3><strong><?php echo (int)$stats['total_usuarios']; ?></strong><small>Usuários cadastrados</small></div><div class="icon"><i class="fa-solid fa-users"></i></div></div>
    <div class="card kpi"><div><h3>Ativos</h3><strong><?php echo (int)$stats['ativos']; ?></strong><small>Com acesso liberado</small></div><div class="icon"><i class="fa-solid fa-circle-check"></i></div></div>
    <div class="card kpi"><div><h3>Bloqueados</h3><strong><?php echo (int)$stats['inativos']; ?></strong><small>Sem acesso ao sistema</small></div><div class="icon"><i class="fa-solid fa-lock"></i></div></div>
    <div class="card kpi"><div><h3>Verificados</h3><strong><?php echo (int)$stats['verificados']; ?></strong><small>E-mails validados</small></div><div class="icon"><i class="fa-solid fa-envelope-circle-check"></i></div></div>
</section>

<section class="card" style="margin-top:16px;">
    <div class="section-title">
        <div>
            <h3>Lista de usuários</h3>
            <div class="muted">Edite, bloqueie, ative ou exclua contas</div>
        </div>
        <span class="pill gray"><?php echo count($usuarios); ?> registros</span>
    </div>

    <div class="table-head">
        <div>Usuário</div>
        <div>Status</div>
        <div>2FA</div>
        <div>Verificado</div>
        <div>Criado</div>
        <div>Atualizado</div>
        <div>Ações</div>
    </div>

    <div class="list">
        <?php foreach ($usuarios as $u): ?>
        <div class="list-item">
            <div>
                <strong><?php echo htmlspecialchars($u['email']); ?></strong>
                <span class="muted">ID #<?php echo (int)$u['id']; ?> · <?php echo htmlspecialchars($u['role'] ?? 'USER'); ?></span>
            </div>
            <div><span class="pill <?php echo badgeStatus($u['status']); ?>"><?php echo htmlspecialchars($u['status']); ?></span></div>
            <div><span class="pill <?php echo ((int)$u['forcar_2fa'] === 1) ? 'ok' : 'gray'; ?>"><?php echo ((int)$u['forcar_2fa'] === 1) ? 'SIM' : 'NÃO'; ?></span></div>
            <div><span class="pill <?php echo !empty($u['email_verificado_em']) ? 'ok' : 'warn'; ?>"><?php echo !empty($u['email_verificado_em']) ? 'SIM' : 'NÃO'; ?></span></div>
            <div class="muted"><?php echo fmtDate($u['criado_em']); ?></div>
            <div class="muted"><?php echo fmtDate($u['atualizado_em']); ?></div>
            <div class="user-actions">
                <button type="button" class="btn-action btn-edit" onclick="openEditModal(this)" data-id="<?php echo (int)$u['id']; ?>" data-email="<?php echo htmlspecialchars($u['email'], ENT_QUOTES); ?>" data-status="<?php echo htmlspecialchars($u['status'], ENT_QUOTES); ?>" data-twofa="<?php echo (int)$u['forcar_2fa']; ?>" data-role="<?php echo htmlspecialchars($u['role'] ?? 'USER', ENT_QUOTES); ?>">
                    <i class="fa-solid fa-pen"></i> Editar
                </button>

                <?php if ($u['status'] === 'ATIVO'): ?>
                    <a class="btn-action btn-block" href="bloquear_usuario.php?id=<?php echo (int)$u['id']; ?>"><i class="fa-solid fa-ban"></i> Bloquear</a>
                <?php else: ?>
                    <a class="btn-action btn-block" href="bloquear_usuario.php?id=<?php echo (int)$u['id']; ?>&acao=ativar"><i class="fa-solid fa-unlock"></i> Ativar</a>
                <?php endif; ?>

                <?php if ((int)$u['id'] !== (int)($_SESSION['user_id'] ?? 0)): ?>
                    <a class="btn-action btn-del" href="excluir_usuario.php?id=<?php echo (int)$u['id']; ?>" onclick="return confirm('Tem certeza que deseja excluir este usuário?');"><i class="fa-solid fa-trash"></i> Excluir</a>
                <?php else: ?>
                    <span class="pill gray" title="Você não pode excluir o usuário logado"><i class="fa-solid fa-user-lock"></i> Você</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
</main>
</div>

<div class="modal-backdrop" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Editar usuário</h3>
            <button class="modal-close" type="button" onclick="closeEditModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="editar_usuario.php">
            <div class="modal-body">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-grid">
                    <div class="field full">
                        <label for="edit_email">E-mail</label>
                        <input type="email" id="edit_email" name="email" required>
                    </div>
                    <div class="field">
                        <label for="edit_status">Status</label>
                        <select id="edit_status" name="status">
                            <option value="ATIVO">ATIVO</option>
                            <option value="INATIVO">INATIVO</option>
                            <option value="EXCLUIDO">EXCLUIDO</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="edit_forcar_2fa">Forçar 2FA</label>
                        <select id="edit_forcar_2fa" name="forcar_2fa">
                            <option value="0">Não</option>
                            <option value="1">Sim</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="edit_role">Função</label>
                        <select id="edit_role" name="role">
                            <option value="USER">USER</option>
                            <option value="ADMIN">ADMIN</option>
                        </select>
                    </div>
                    <div class="field full">
                        <label for="edit_senha">Nova senha (opcional)</label>
                        <input type="password" id="edit_senha" name="senha" placeholder="Deixe em branco para manter a atual">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeEditModal()">Cancelar</button>
                <button type="submit" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(btn){
    const d = btn.dataset;
    document.getElementById('edit_id').value = d.id || '';
    document.getElementById('edit_email').value = d.email || '';
    document.getElementById('edit_status').value = d.status || 'ATIVO';
    document.getElementById('edit_forcar_2fa').value = d.twofa || '0';
    document.getElementById('edit_role').value = d.role || 'USER';
    document.getElementById('edit_senha').value = '';
    document.getElementById('editModal').classList.add('active');
}
function closeEditModal(){
    document.getElementById('editModal').classList.remove('active');
}
window.addEventListener('keydown', function(e){ if(e.key === 'Escape') closeEditModal(); });
document.getElementById('editModal').addEventListener('click', function(e){ if(e.target === this) closeEditModal(); });
</script>
</body>
</html>