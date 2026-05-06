<?php
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8"); }
function money($v){ return "R$ " . number_format((float)$v, 2, ",", "."); }
function fmtDate($v){
    if (empty($v)) return "-";
    try { return (new DateTime($v))->format("d/m/Y"); }
    catch(Throwable $e){ return "-"; }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Life Finance | Configurações</title>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/configuracoes.css">
</head>
</head>
<body class="dashboard-page">
<div class="dashboard-shell">
<aside class="sidebar">
<div class="brand">
<img src="<?php echo BASE_URL; ?>/assets/images/logoSemFundo.png" alt="Life Finance">
<div><h1>Life Finance</h1><p>Configurações</p></div>
</div>
<nav class="menu">
<a href="<?php echo BASE_URL; ?>/dashboard"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
<a href="<?php echo BASE_URL; ?>/movimentacoes"><i class="fa-solid fa-right-left"></i> Movimentações</a>
<a href="<?php echo BASE_URL; ?>/contas"><i class="fa-solid fa-wallet"></i> Contas</a>
<a href="<?php echo BASE_URL; ?>/categorias"><i class="fa-solid fa-tags"></i> Categorias</a>
<a href="<?php echo BASE_URL; ?>/metas"><i class="fa-solid fa-bullseye"></i> Metas</a>
<a href="<?php echo BASE_URL; ?>/relatorios"><i class="fa-solid fa-chart-column"></i> Relatórios</a>
<a href="<?php echo BASE_URL; ?>/configuracoes" class="active"><i class="fa-solid fa-gear"></i> Configurações</a>
<a href="<?php echo BASE_URL; ?>/auth/logout"><i class="fa-solid fa-arrow-right-from-bracket"></i> Sair</a>
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
        <form method="POST" action="<?php echo BASE_URL; ?>/configuracoes/updateProfile">
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
        <form method="POST" action="<?php echo BASE_URL; ?>/configuracoes/updatePassword">
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