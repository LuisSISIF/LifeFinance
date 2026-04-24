<?php
session_start();

/*
|--------------------------------------------------------------------------
| Controle de acesso
|--------------------------------------------------------------------------
| Somente usuários autenticados podem acessar esta página.
| A tela de configurações é restrita ao perfil logado.
*/
if (!isset($_SESSION["authenticated"]) || $_SESSION["authenticated"] !== true) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . "/Conexao.php";

/*
|--------------------------------------------------------------------------
| Funções auxiliares
|--------------------------------------------------------------------------
| Centralizam tarefas repetidas de formatação e escapamento.
*/
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8"); }
function money($v){ return "R$ " . number_format((float)$v, 2, ",", "."); }
function fmtDate($v){
    if (empty($v)) return "-";
    try { return (new DateTime($v))->format("d/m/Y"); }
    catch(Throwable $e){ return "-"; }
}

try {
    /*
    |--------------------------------------------------------------------------
    | Conexão com o banco
    |--------------------------------------------------------------------------
    | A instância PDO é obtida via classe centralizada de conexão.
    */
    $pdo = Conexao::getInstancia();
    $userId = (int)($_SESSION["user_id"] ?? 0);

    if ($userId <= 0) {
        throw new Exception("Usuário não identificado.");
    }

    /*
    |--------------------------------------------------------------------------
    | Dados do usuário logado
    |--------------------------------------------------------------------------
    | Busca todas as colunas para permitir flexibilidade com o banco.
    */
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $userId]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        throw new Exception("Usuário não encontrado.");
    }

    /*
    |--------------------------------------------------------------------------
    | Resumo financeiro
    |--------------------------------------------------------------------------
    | Exibe indicadores rápidos de movimentações do usuário.
    */
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) total,
            SUM(CASE WHEN tipo='RECEITA' THEN valor ELSE 0 END) receitas,
            SUM(CASE WHEN tipo='DESPESA' THEN valor ELSE 0 END) despesas
        FROM movimentacoes
        WHERE id_usuario = :uid
    ");
    $stmt->execute([':uid' => $userId]);
    $resumo = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'receitas'=>0,'despesas'=>0];

    /*
    |--------------------------------------------------------------------------
    | Processamento dos formulários
    |--------------------------------------------------------------------------
    | Trata atualização de perfil e alteração de senha.
    */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
        $acao = $_POST['acao'];

        if ($acao === 'salvar_geral') {
            $email = trim($_POST['email'] ?? '');
            $telefone = trim($_POST['telefone'] ?? '');
            $tema = trim($_POST['tema'] ?? 'claro');
            $moedaPadrao = strtoupper(trim($_POST['moeda_padrao'] ?? 'BRL'));
            $idioma = trim($_POST['idioma'] ?? 'pt-BR');
            $notifEmail = isset($_POST['notificacoes_email']) ? 1 : 0;
            $notifApp = isset($_POST['notificacoes_app']) ? 1 : 0;
            $metasAtivas = isset($_POST['metas_ativas']) ? 1 : 0;

            $stmt = $pdo->prepare("
                UPDATE usuarios
                SET
                    email = :email,
                    telefone = :telefone,
                    tema = :tema,
                    moeda_padrao = :moeda_padrao,
                    idioma = :idioma,
                    notificacoes_email = :notif_email,
                    notificacoes_app = :notif_app,
                    metas_ativas = :metas_ativas,
                    atualizado_em = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':email' => $email,
                ':telefone' => $telefone,
                ':tema' => $tema,
                ':moeda_padrao' => $moedaPadrao,
                ':idioma' => $idioma,
                ':notif_email' => $notifEmail,
                ':notif_app' => $notifApp,
                ':metas_ativas' => $metasAtivas,
                ':id' => $userId
            ]);

            header('Location: configuracao.php?salvo=1');
            exit;
        }

        if ($acao === 'alterar_senha') {
            $senhaAtual = $_POST['senha_atual'] ?? '';
            $novaSenha = $_POST['nova_senha'] ?? '';
            $confirmaSenha = $_POST['confirmar_senha'] ?? '';

            if ($novaSenha !== $confirmaSenha) {
                throw new Exception('As senhas novas não conferem.');
            }

            if (strlen($novaSenha) < 6) {
                throw new Exception('A nova senha precisa ter pelo menos 6 caracteres.');
            }

            $senhaBanco = $usuario['senha'] ?? ($usuario['senha_hash'] ?? '');
            if ($senhaBanco === '') {
                throw new Exception('Esta tabela de usuários não possui coluna de senha.');
            }

            $ok = password_verify($senhaAtual, $senhaBanco) || hash('sha256', $senhaAtual) === $senhaBanco;
            if (!$ok) {
                throw new Exception('Senha atual incorreta.');
            }

            $hash = password_hash($novaSenha, PASSWORD_DEFAULT);

            if (array_key_exists('senha', $usuario)) {
                $stmt = $pdo->prepare("UPDATE usuarios SET senha = :senha, atualizado_em = NOW() WHERE id = :id");
                $stmt->execute([':senha' => $hash, ':id' => $userId]);
            } elseif (array_key_exists('senha_hash', $usuario)) {
                $stmt = $pdo->prepare("UPDATE usuarios SET senha_hash = :senha, atualizado_em = NOW() WHERE id = :id");
                $stmt->execute([':senha' => $hash, ':id' => $userId]);
            } else {
                throw new Exception('A tabela usuarios não possui coluna de senha ou senha_hash.');
            }

            header('Location: configuracao.php?senha=1');
            exit;
        }
    }
} catch (Throwable $e) {
    die('Erro ao carregar configurações: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Life Finance | Configurações</title>
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
.header{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:20px;}
.header h2{margin:0;font-size:28px;}
.header p{margin:6px 0 0;color:#6b7280;}
.grid-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:20px;}
.card{background:#fff;border-radius:18px;box-shadow:0 10px 24px rgba(15,23,42,.06);padding:20px;border:1px solid #eef2f7;}
.kpi{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;}
.kpi .icon{width:48px;height:48px;border-radius:14px;display:grid;place-items:center;background:#eef6ff;color:#288CFA;font-size:20px;}
.kpi h3{margin:0 0 8px;font-size:14px;color:#6b7280;font-weight:600;}
.kpi strong{font-size:24px;color:#111827;display:block;}
.kpi small{color:#10b981;font-weight:600;}
.grid-main{display:grid;grid-template-columns:1.2fr .8fr;gap:16px;align-items:start;}
.section-title{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:12px;flex-wrap:wrap;}
.section-title h3{margin:0;font-size:18px;}
.muted{color:#6b7280;font-size:13px;}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.field{display:flex;flex-direction:column;gap:6px;}
.field.full{grid-column:1 / -1;}
.field label{font-size:14px;font-weight:600;color:#374151;}
.field input,.field select,.field textarea{padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;width:100%;font-family:inherit;outline:none;transition:border-color .2s ease,box-shadow .2s ease;}
.field input:focus,.field select:focus,.field textarea:focus{border-color:#288CFA;box-shadow:0 0 0 3px rgba(40,140,250,.12);}
.btn-save{background:linear-gradient(135deg,#288CFA,#1c7ad0);border:none;color:#fff;padding:12px 18px;border-radius:10px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px;}
.pill{display:inline-flex;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700;}
.pill.ok{background:#dcfce7;color:#166534;}
.pill.gray{background:#e5e7eb;color:#374151;}
.pill.blue{background:#dbeafe;color:#1d4ed8;}
.pill.warn{background:#fef3c7;color:#92400e;}
.stack{display:grid;gap:12px;}
.toggle-row{display:flex;justify-content:space-between;align-items:center;gap:14px;padding:14px;border:1px solid #eef2f7;border-radius:14px;background:#fbfdff;}
.toggle-row span{display:block;font-weight:600;color:#374151;}
.toggle-row small{display:block;color:#6b7280;margin-top:2px;}
.switch{position:relative;width:54px;height:30px;flex:0 0 auto;}
.switch input{display:none;}
.slider{position:absolute;inset:0;background:#cbd5e1;border-radius:999px;transition:.2s;cursor:pointer;}
.slider:before{content:'';position:absolute;width:22px;height:22px;left:4px;top:4px;background:#fff;border-radius:50%;transition:.2s;box-shadow:0 4px 10px rgba(0,0,0,.15);}
.switch input:checked + .slider{background:#288CFA;}
.switch input:checked + .slider:before{transform:translateX(24px);}
.note{padding:14px;border-radius:14px;background:#eff6ff;border:1px solid #dbeafe;color:#1d4ed8;font-size:14px;}
@media (max-width:1200px){.grid-kpis{grid-template-columns:repeat(2,minmax(0,1fr));}.grid-main,.dashboard-shell{grid-template-columns:1fr;}.sidebar{height:auto;position:relative;}.content{padding:16px;}}
@media (max-width:640px){.grid-kpis{grid-template-columns:1fr;}.form-grid{grid-template-columns:1fr;}.header{flex-direction:column;align-items:flex-start;}}
</style>
</head>
<body class="dashboard-page">
<div class="dashboard-shell">
<aside class="sidebar">
<div class="brand">
<img src="assets/images/logoSemFundo.png" alt="Life Finance">
<div><h1>Life Finance</h1><p>Configurações</p></div>
</div>
<nav class="menu">
<a href="dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
<a href="movimentacoes.php"><i class="fa-solid fa-right-left"></i> Movimentações</a>
<a href="contas.php"><i class="fa-solid fa-wallet"></i> Contas</a>
<a href="categorias.php"><i class="fa-solid fa-tags"></i> Categorias</a>
<a href="metas.php"><i class="fa-solid fa-bullseye"></i> Metas</a>
<a href="relatorios.php"><i class="fa-solid fa-chart-column"></i> Relatórios</a>
<a href="configuracao.php" class="active"><i class="fa-solid fa-gear"></i> Configurações</a>
<a href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Sair</a>
</nav>
</aside>

<main class="content">
<div class="header">
    <div>
        <h2>Configurações</h2>
        <p>Personalize sua conta, preferências e segurança.</p>
    </div>
    <div class="pill blue"><i class="fa-solid fa-user-gear"></i> Perfil ativo</div>
</div>

<section class="grid-kpis">
    <div class="card kpi"><div><h3>Movimentações</h3><strong><?php echo (int)$resumo['total']; ?></strong><small>Total cadastrado</small></div><div class="icon"><i class="fa-solid fa-right-left"></i></div></div>
    <div class="card kpi"><div><h3>Receitas</h3><strong><?php echo money($resumo['receitas'] ?? 0); ?></strong><small>Entradas totais</small></div><div class="icon"><i class="fa-solid fa-arrow-trend-up"></i></div></div>
    <div class="card kpi"><div><h3>Despesas</h3><strong><?php echo money($resumo['despesas'] ?? 0); ?></strong><small>Saídas totais</small></div><div class="icon"><i class="fa-solid fa-arrow-trend-down"></i></div></div>
    <div class="card kpi"><div><h3>Saldo geral</h3><strong><?php echo money((float)($resumo['receitas'] ?? 0) - (float)($resumo['despesas'] ?? 0)); ?></strong><small>Resultado acumulado</small></div><div class="icon"><i class="fa-solid fa-scale-balanced"></i></div></div>
</section>

<div class="grid-main" style="margin-top:16px;">
    <section class="card">
        <div class="section-title">
            <div><h3>Dados do perfil</h3><div class="muted">Informações básicas e preferências gerais</div></div>
            <span class="pill ok">Salvo em <?php echo fmtDate($usuario['atualizado_em'] ?? $usuario['criado_em'] ?? null); ?></span>
        </div>
        <form method="POST">
            <input type="hidden" name="acao" value="salvar_geral">
            <div class="form-grid">
                <div class="field full"><label>Nome</label><input type="text" name="nome" value="<?php echo e($usuario['nome'] ?? ''); ?>" readonly></div>
                <div class="field"><label>E-mail</label><input type="email" name="email" value="<?php echo e($usuario['email'] ?? ''); ?>" required></div>
                <div class="field"><label>Telefone</label><input type="text" name="telefone" value="<?php echo e($usuario['telefone'] ?? ''); ?>" placeholder="(35) 99999-9999"></div>
                <div class="field"><label>Tema</label><select name="tema"><option value="claro" <?php echo ($usuario['tema'] ?? 'claro') === 'claro' ? 'selected' : ''; ?>>Claro</option><option value="escuro" <?php echo ($usuario['tema'] ?? '') === 'escuro' ? 'selected' : ''; ?>>Escuro</option><option value="auto" <?php echo ($usuario['tema'] ?? '') === 'auto' ? 'selected' : ''; ?>>Automático</option></select></div>
                <div class="field"><label>Moeda padrão</label><select name="moeda_padrao"><option value="BRL" <?php echo ($usuario['moeda_padrao'] ?? 'BRL') === 'BRL' ? 'selected' : ''; ?>>BRL</option><option value="USD" <?php echo ($usuario['moeda_padrao'] ?? '') === 'USD' ? 'selected' : ''; ?>>USD</option><option value="EUR" <?php echo ($usuario['moeda_padrao'] ?? '') === 'EUR' ? 'selected' : ''; ?>>EUR</option></select></div>
                <div class="field"><label>Idioma</label><select name="idioma"><option value="pt-BR" <?php echo ($usuario['idioma'] ?? 'pt-BR') === 'pt-BR' ? 'selected' : ''; ?>>Português (BR)</option><option value="en-US" <?php echo ($usuario['idioma'] ?? '') === 'en-US' ? 'selected' : ''; ?>>English (US)</option></select></div>
                <div class="field full"><div class="note">Essas preferências podem ser usadas para ajustar o visual, moeda exibida e idioma padrão do sistema.</div></div>
                <div class="field full">
                    <div class="stack">
                        <div class="toggle-row"><div><span>Notificações por e-mail</span><small>Receber avisos de vencimentos e alertas</small></div><label class="switch"><input type="checkbox" name="notificacoes_email" <?php echo !empty($usuario['notificacoes_email']) ? 'checked' : ''; ?>><span class="slider"></span></label></div>
                        <div class="toggle-row"><div><span>Notificações no app</span><small>Exibir avisos dentro do sistema</small></div><label class="switch"><input type="checkbox" name="notificacoes_app" <?php echo !empty($usuario['notificacoes_app']) ? 'checked' : ''; ?>><span class="slider"></span></label></div>
                        <div class="toggle-row"><div><span>Metas ativas</span><small>Habilitar acompanhamento automático de metas</small></div><label class="switch"><input type="checkbox" name="metas_ativas" <?php echo !empty($usuario['metas_ativas']) ? 'checked' : ''; ?>><span class="slider"></span></label></div>
                    </div>
                </div>
                <div class="field full"><button class="btn-save" type="submit"><i class="fa-solid fa-floppy-disk"></i> Salvar configurações</button></div>
            </div>
        </form>
    </section>

    <aside class="card">
        <div class="section-title"><div><h3>Segurança</h3><div class="muted">Troca de senha da conta</div></div><span class="pill warn">Protegido</span></div>
        <form method="POST">
            <input type="hidden" name="acao" value="alterar_senha">
            <div class="form-grid">
                <div class="field full"><label>Senha atual</label><input type="password" name="senha_atual" required></div>
                <div class="field full"><label>Nova senha</label><input type="password" name="nova_senha" required></div>
                <div class="field full"><label>Confirmar nova senha</label><input type="password" name="confirmar_senha" required></div>
                <div class="field full"><button class="btn-save" type="submit"><i class="fa-solid fa-key"></i> Alterar senha</button></div>
            </div>
        </form>
        <div style="margin-top:18px;" class="note">A senha é validada antes da troca. Se o usuário ainda estiver com senha antiga em texto simples, o sistema tenta validar também por hash SHA-256 para manter compatibilidade.</div>
    </aside>
</div>
</main>
</div>
</body>
</html>