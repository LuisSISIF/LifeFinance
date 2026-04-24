<?php
/*
|--------------------------------------------------------------------------
| Página de metas financeiras
|--------------------------------------------------------------------------
| Este arquivo gerencia o CRUD das metas financeiras do usuário logado.
| Permite criar, editar, listar e excluir metas vinculadas à conta do usuário.
*/
session_start();

/*
|--------------------------------------------------------------------------
| Controle de acesso
|--------------------------------------------------------------------------
| Apenas usuários autenticados podem acessar esta página.
*/
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/Conexao.php';

/*
|--------------------------------------------------------------------------
| Funções auxiliares
|--------------------------------------------------------------------------
| Centralizam formatação de valores, datas e conversão de flags.
*/
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

function b($v) {
    return !empty($v) ? 1 : 0;
}

try {
    /*
    |--------------------------------------------------------------------------
    | Conexão com o banco
    |--------------------------------------------------------------------------
    | A instância PDO é obtida pela classe centralizada Conexao.
    */
    $pdo = Conexao::getInstancia();
    $userId = (int)($_SESSION['user_id'] ?? 0);

    if ($userId <= 0) {
        throw new Exception('Usuário não identificado.');
    }

    /*
    |--------------------------------------------------------------------------
    | Processamento dos formulários
    |--------------------------------------------------------------------------
    | Trata criação e edição de metas financeiras.
    */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
        $acao = $_POST['acao'];
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $valorMeta = (float)str_replace(',', '.', $_POST['valor_meta'] ?? 0);
        $valorAtual = (float)str_replace(',', '.', $_POST['valor_atual'] ?? 0);
        $codigoMoeda = strtoupper(trim($_POST['codigo_moeda'] ?? 'BRL'));
        $dataLimite = $_POST['data_limite'] ?: null;
        $ativa = isset($_POST['ativa']) ? 1 : 0;
        $compartilhada = isset($_POST['compartilhada']) ? 1 : 0;
        $reservaEmergencia = isset($_POST['reserva_emergencia']) ? 1 : 0;

        /*
        |--------------------------------------------------------------------------
        | Validação básica
        |--------------------------------------------------------------------------
        | Garante que os dados mínimos estejam corretos antes de persistir.
        */
        if ($nome === '') {
            throw new Exception('Informe o nome da meta.');
        }

        if ($valorMeta < 0 || $valorAtual < 0) {
            throw new Exception('Valores inválidos.');
        }

        /*
        |--------------------------------------------------------------------------
        | Criação de meta
        |--------------------------------------------------------------------------
        | Insere uma nova meta financeira vinculada ao usuário logado.
        */
        if ($acao === 'salvar') {
            $stmt = $pdo->prepare("
                INSERT INTO metas_financeiras
                (id_usuario, nome, descricao, valor_meta, valor_atual, codigo_moeda, data_limite, ativa, compartilhada, reserva_emergencia, criado_em, atualizado_em)
                VALUES
                (:uid, :nome, :descricao, :valor_meta, :valor_atual, :codigo_moeda, :data_limite, :ativa, :compartilhada, :reserva_emergencia, NOW(), NOW())
            ");
            $stmt->execute([
                ':uid' => $userId,
                ':nome' => $nome,
                ':descricao' => $descricao,
                ':valor_meta' => $valorMeta,
                ':valor_atual' => $valorAtual,
                ':codigo_moeda' => $codigoMoeda,
                ':data_limite' => $dataLimite,
                ':ativa' => $ativa,
                ':compartilhada' => $compartilhada,
                ':reserva_emergencia' => $reservaEmergencia
            ]);

            header('Location: metas.php?success=1');
            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | Edição de meta
        |--------------------------------------------------------------------------
        | Atualiza uma meta existente pertencente ao usuário autenticado.
        */
        if ($acao === 'editar' && $id > 0) {
            $stmt = $pdo->prepare("
                UPDATE metas_financeiras
                SET
                    nome = :nome,
                    descricao = :descricao,
                    valor_meta = :valor_meta,
                    valor_atual = :valor_atual,
                    codigo_moeda = :codigo_moeda,
                    data_limite = :data_limite,
                    ativa = :ativa,
                    compartilhada = :compartilhada,
                    reserva_emergencia = :reserva_emergencia,
                    atualizado_em = NOW()
                WHERE id = :id AND id_usuario = :uid
            ");
            $stmt->execute([
                ':nome' => $nome,
                ':descricao' => $descricao,
                ':valor_meta' => $valorMeta,
                ':valor_atual' => $valorAtual,
                ':codigo_moeda' => $codigoMoeda,
                ':data_limite' => $dataLimite,
                ':ativa' => $ativa,
                ':compartilhada' => $compartilhada,
                ':reserva_emergencia' => $reservaEmergencia,
                ':id' => $id,
                ':uid' => $userId
            ]);

            header('Location: metas.php?edited=1');
            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Exclusão de meta
    |--------------------------------------------------------------------------
    | Remove uma meta financeira do usuário logado.
    */
    if (isset($_GET['delete'])) {
        $id = (int)$_GET['delete'];

        if ($id > 0) {
            $stmt = $pdo->prepare("
                DELETE FROM metas_financeiras
                WHERE id = :id AND id_usuario = :uid
            ");
            $stmt->execute([
                ':id' => $id,
                ':uid' => $userId
            ]);

            header('Location: metas.php?deleted=1');
            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Listagem de metas
    |--------------------------------------------------------------------------
    | Retorna as metas financeiras do usuário ordenadas pelas mais recentes.
    */
    $stmt = $pdo->prepare("
        SELECT
            id, nome, descricao, valor_meta, valor_atual, codigo_moeda,
            data_limite, ativa, compartilhada, reserva_emergencia,
            criado_em, atualizado_em
        FROM metas_financeiras
        WHERE id_usuario = :uid
        ORDER BY criado_em DESC, id DESC
    ");
    $stmt->execute([':uid' => $userId]);
    $metas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    die('Erro ao carregar metas: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Life Finance | Metas</title>
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
.list{display:grid;gap:12px;}
.list-item{padding:14px;border-radius:14px;background:linear-gradient(180deg,#fbfdff 0%,#f8fbff 100%);border:1px solid #eef2f7;display:grid;grid-template-columns:1.2fr .9fr .9fr .7fr .7fr auto;gap:12px;align-items:center;}
.pill{padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;}
.pill.ok{background:#dcfce7;color:#166534;}
.pill.warn{background:#fef3c7;color:#92400e;}
.pill.bad{background:#fee2e2;color:#991b1b;}
.pill.gray{background:#e5e7eb;color:#374151;}
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
.progress{height:10px;background:#e5e7eb;border-radius:999px;overflow:hidden;}
.progress span{display:block;height:100%;background:linear-gradient(90deg,#288CFA,#2E865F);border-radius:999px;}
@keyframes fadeInUp{from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:translateY(0)}}
@keyframes modalIn{from{opacity:0;transform:translateY(18px) scale(.96)}to{opacity:1;transform:translateY(0) scale(1)}}
@media (max-width:1200px){.grid-kpis{grid-template-columns:repeat(2,minmax(0,1fr));}.dashboard-shell{grid-template-columns:1fr;}.sidebar{height:auto;position:relative;}.list-item{grid-template-columns:1fr;}.content{padding:16px;}}
@media (max-width:640px){.grid-kpis{grid-template-columns:1fr;}.form-grid{grid-template-columns:1fr;}.topbar{flex-direction:column;align-items:flex-start;}}
</style>
</head>
<body class="dashboard-page">
<div class="dashboard-shell">
<aside class="sidebar">
<div class="brand">
    <img src="assets/images/logoSemFundo.png" alt="Life Finance">
    <div><h1>Life Finance</h1><p>Metas financeiras</p></div>
</div>
<nav class="menu">
<a href="dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
<a href="movimentacoes.php"><i class="fa-solid fa-right-left"></i> Movimentações</a>
<a href="contas.php"><i class="fa-solid fa-wallet"></i> Contas</a>
<a href="categorias.php"><i class="fa-solid fa-tags"></i> Categorias</a>
<a href="metas.php" class="active"><i class="fa-solid fa-bullseye"></i> Metas</a>
<a href="relatorios.php"><i class="fa-solid fa-chart-column"></i> Relatórios</a>
<a href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Sair</a>
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
                    <a class="btn-action btn-del" href="metas.php?delete=<?php echo (int)$m['id']; ?>" onclick="return confirm('Excluir esta meta?');"><i class="fa-solid fa-trash"></i> Excluir</a>
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
        <form method="POST">
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