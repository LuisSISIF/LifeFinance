<?php
/*
|--------------------------------------------------------------------------
| Página de movimentações
|--------------------------------------------------------------------------
| Esta tela lista as movimentações financeiras do usuário logado,
| exibe indicadores do mês e permite cadastrar novos lançamentos.
*/
session_start();

/*
|--------------------------------------------------------------------------
| Controle de acesso
|--------------------------------------------------------------------------
| Apenas usuários autenticados podem acessar a página.
*/
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/Conexao.php';

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

try {
    /*
    |--------------------------------------------------------------------------
    | Conexão com o banco
    |--------------------------------------------------------------------------
    | Todas as consultas são feitas via PDO com prepared statements.
    */
    $pdo = Conexao::getInstancia();
    $userId = (int)($_SESSION['user_id'] ?? 0);

    if ($userId <= 0) {
        throw new Exception('Usuário não identificado.');
    }

    /*
    |--------------------------------------------------------------------------
    | Contas e categorias do usuário
    |--------------------------------------------------------------------------
    | Dados usados no formulário de novo lançamento.
    */
    $contaStmt = $pdo->prepare("SELECT id, nome FROM contas WHERE id_usuario = :uid ORDER BY nome ASC");
    $contaStmt->execute([':uid' => $userId]);
    $contas = $contaStmt->fetchAll();

    $catStmt = $pdo->prepare("SELECT id, nome, tipo FROM categorias WHERE id_usuario = :uid ORDER BY nome ASC");
    $catStmt->execute([':uid' => $userId]);
    $categorias = $catStmt->fetchAll();

    /*
    |--------------------------------------------------------------------------
    | Indicadores do mês atual
    |--------------------------------------------------------------------------
    | Calcula totais e status das movimentações do período atual.
    */
    $stats = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN tipo = 'RECEITA' THEN valor ELSE 0 END) AS receitas,
            SUM(CASE WHEN tipo = 'DESPESA' THEN valor ELSE 0 END) AS despesas,
            SUM(CASE WHEN tipo = 'TRANSFERENCIA' THEN valor ELSE 0 END) AS transferencias,
            SUM(CASE WHEN status = 'PAGO' THEN 1 ELSE 0 END) AS pagas,
            SUM(CASE WHEN status = 'PENDENTE' THEN 1 ELSE 0 END) AS pendentes
        FROM movimentacoes
        WHERE id_usuario = :uid
          AND MONTH(ocorreu_em) = MONTH(CURDATE())
          AND YEAR(ocorreu_em) = YEAR(CURDATE())
    ");
    $stats->execute([':uid' => $userId]);
    $dados = $stats->fetch(PDO::FETCH_ASSOC) ?: [];

    /*
    |--------------------------------------------------------------------------
    | Últimas movimentações
    |--------------------------------------------------------------------------
    | Busca os registros mais recentes para exibição na lista principal.
    */
    $movStmt = $pdo->prepare("
        SELECT
            m.id, m.tipo, m.valor, m.descricao, m.status,
            m.ocorreu_em, m.vence_em, m.codigo_moeda,
            c.nome AS conta_nome,
            cat.nome AS categoria_nome
        FROM movimentacoes m
        LEFT JOIN contas c ON c.id = m.id_conta
        LEFT JOIN categorias cat ON cat.id = m.id_categoria
        WHERE m.id_usuario = :uid
        ORDER BY m.ocorreu_em DESC, m.id DESC
        LIMIT 20
    ");
    $movStmt->execute([':uid' => $userId]);
    $movimentacoes = $movStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    die('Erro ao carregar movimentações: ' . $e->getMessage());
}

/*
|--------------------------------------------------------------------------
| Saldo mensal
|--------------------------------------------------------------------------
| Diferença entre receitas e despesas no período atual.
*/
$saldo = (float)($dados['receitas'] ?? 0) - (float)($dados['despesas'] ?? 0);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Life Finance | Movimentações</title>
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
.table-head,.list-item{display:grid;grid-template-columns:110px 90px 130px 1fr 120px 110px 110px auto;gap:12px;align-items:center;}
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
.modal{background:#fff;border-radius:18px;width:100%;max-width:720px;box-shadow:0 24px 60px rgba(0,0,0,.22);max-height:90vh;display:flex;flex-direction:column;overflow:hidden;animation:modalIn .28s ease both;}
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
        <p>Movimentações</p>
    </div>
</div>
<nav class="menu">
<a href="dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
<a href="movimentacoes.php" class="active"><i class="fa-solid fa-right-left"></i> Movimentações</a>
<a href="contas.php"><i class="fa-solid fa-wallet"></i> Contas</a>
<a href="categorias.php"><i class="fa-solid fa-tags"></i> Categorias</a>
<a href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Sair</a>
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
        <button class="btn-top" onclick="openModal()"><i class="fa-solid fa-plus"></i> Nova movimentação</button>
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
                <a class="btn-action btn-edit" href="editar_movimentacao.php?id=<?php echo (int)$m['id']; ?>"><i class="fa-solid fa-pen"></i> Editar</a>
                <a class="btn-action btn-del" href="excluir_movimentacao.php?id=<?php echo (int)$m['id']; ?>" onclick="return confirm('Excluir esta movimentação?');"><i class="fa-solid fa-trash"></i> Excluir</a>
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

        <form method="POST" action="salvar_movimentacao.php">
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

    fetch('categorias_por_tipo.php?tipo=' + encodeURIComponent(t), { cache: 'no-store' })
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

window.addEventListener('keydown', e => { if(e.key === 'Escape') closeModal(); });
document.getElementById('movModal').addEventListener('click', e => {
    if(e.target === e.currentTarget) closeModal();
});
</script>
</body>
</html>