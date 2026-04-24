<?php
session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/Conexao.php';
require_once __DIR__ . '/ContasService.php';

try {
    $pdo = Conexao::getInstancia();
    $userId = $_SESSION['user_id'] ?? 1; // Fallback
    $dados = ContasService::getDadosPaginaContas($pdo, $userId);
    $tiposConta = ContasService::getTiposConta($pdo);
} catch (Throwable $e) {
    die("Erro ao carregar contas: " . $e->getMessage());
}

$saldoTotalStr = 'R$ ' . number_format($dados['saldoConsolidado'], 2, ',', '.');
$metaVal = $dados['painelLateral']['metaSaldo']['valor_meta'];
$metaFalta = 'R$ ' . number_format($dados['painelLateral']['metaSaldo']['falta'], 2, ',', '.');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Life Finance | Contas</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body.accounts-page{background:#f4f7fb;color:#1f2937;}
        .page-shell{display:grid;grid-template-columns:260px 1fr;min-height:100vh;}
        .sidebar{background:linear-gradient(180deg,#0f172a 0%,#111827 100%);color:#fff;padding:24px;position:sticky;top:0;height:100vh;}
        .brand{display:flex;align-items:center;gap:14px;margin-bottom:28px;}
        .brand img{width:56px;height:56px;border-radius:16px;object-fit:cover;border:2px solid rgba(255,255,255,.15);}
        .brand h1{font-size:20px;margin:0;}
        .brand p{margin:2px 0 0;color:#94a3b8;font-size:13px;}
        .menu{display:grid;gap:8px;margin-top:20px;}
        .menu a{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:12px;color:#e5e7eb;text-decoration:none;transition:.2s;}
        .menu a:hover,.menu a.active{background:rgba(40,140,250,.18);color:#fff;}
        .content{padding:24px;}
        .header-row{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:22px;}
        .header-row h2{margin:0;font-size:28px;}
        .header-row p{margin:6px 0 0;color:#6b7280;}
        
        .btn-primary{background:#288CFA;color:#fff;border:0;border-radius:12px;padding:12px 16px;font-weight:700;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:10px;box-shadow:0 10px 18px rgba(40,140,250,.18); transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;}
        .btn-primary:hover{transform: translateY(-2px); filter: brightness(1.1); box-shadow: 0 14px 28px rgba(40,140,250,.3);}
        
        .stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:18px;}
        .card{background:#fff;border:1px solid #eef2f7;border-radius:18px;box-shadow:0 10px 24px rgba(15,23,42,.06);padding:20px; transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.3s ease; animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) backwards;}
        .card:hover{transform: translateY(-6px); box-shadow: 0 16px 36px rgba(15,23,42,.1);}
        
        .stats .card:nth-child(1){animation-delay: 0.1s;}
        .stats .card:nth-child(2){animation-delay: 0.2s;}
        .stats .card:nth-child(3){animation-delay: 0.3s;}
        .stats .card:nth-child(4){animation-delay: 0.4s;}
        .main-grid .card:nth-child(1){animation-delay: 0.5s;}
        .main-grid .card:nth-child(2){animation-delay: 0.6s;}
        .grid-3 .card:nth-child(1){animation-delay: 0.7s;}
        .grid-3 .card:nth-child(2){animation-delay: 0.8s;}
        .grid-3 .card:nth-child(3){animation-delay: 0.9s;}

        .stat-top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;}
        .stat-top .icon{width:46px;height:46px;border-radius:14px;display:grid;place-items:center;background:#eef6ff;color:#288CFA;font-size:20px;}
        .stat-top h3{margin:0 0 8px;font-size:14px;color:#6b7280;}
        .stat-top strong{display:block;font-size:24px;color:#111827;}
        .muted{color:#6b7280;font-size:13px;}
        .main-grid{display:grid;grid-template-columns:1.2fr .8fr;gap:16px;align-items:start;}
        .section-title{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}
        .section-title h3{margin:0;font-size:18px;}
        
        .table{width:100%;border-collapse:separate;border-spacing:0 10px;}
        .table th{text-align:left;color:#6b7280;font-size:12px;font-weight:700;padding:0 12px 6px;}
        .table tr.data-row { transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease; }
        .table tr.data-row:hover { transform: translateX(4px); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .table td{background:#f9fbff;padding:14px 12px;border-top:1px solid #eef2f7;border-bottom:1px solid #eef2f7;}
        .table td:first-child{border-left:1px solid #eef2f7;border-top-left-radius:14px;border-bottom-left-radius:14px;}
        .table td:last-child{border-right:1px solid #eef2f7;border-top-right-radius:14px;border-bottom-right-radius:14px;}
        
        .account-badge{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700;}
        .bank{background:#dbeafe;color:#1d4ed8;}
        .cash{background:#dcfce7;color:#166534;}
        .card-badge{background:#fef3c7;color:#92400e;}
        .action-btn{border:0;background:transparent;cursor:pointer;color:#64748b;font-size:16px; transition: color 0.2s;}
        .action-btn:hover{color: #288CFA;}
        
        .right-list{display:grid;gap:12px;}
        .mini{display:flex;justify-content:space-between;gap:12px;padding:14px;border-radius:14px;background:#f9fbff;border:1px solid #eef2f7; transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;}
        .mini:hover{transform: translateX(4px); background:#fff; box-shadow: 0 4px 12px rgba(0,0,0,0.05);}
        .mini strong{display:block;margin-bottom:4px;}
        
        .progress{height:10px;background:#e5e7eb;border-radius:999px;overflow:hidden;margin-top:10px;}
        .progress span{display:block;height:100%;background:linear-gradient(90deg,#288CFA,#2E865F);border-radius:999px; animation: slideRight 1.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;}
        
        .grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-top:16px;}
        .small-note{font-size:12px;color:#6b7280;margin-top:8px;}

        /* Animations */
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideRight { from { width: 0%; opacity: 0; } to { opacity: 1; } }
        @keyframes modalFadeIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }

        /* Modal Styles */
        .modal-backdrop { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 1000; justify-content: center; align-items: center; padding: 20px; opacity: 0; transition: opacity 0.3s ease;}
        .modal-backdrop.active { display: flex; opacity: 1; }
        .modal-backdrop.active .modal { animation: modalFadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .modal { background: #fff; border-radius: 16px; width: 100%; max-width: 600px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; }
        .modal-header { padding: 20px 24px; border-bottom: 1px solid #eef2f7; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { margin: 0; font-size: 20px; color: #111827; }
        .modal-close { background: none; border: none; font-size: 20px; color: #6b7280; cursor: pointer; padding: 4px; }
        .modal-body { padding: 24px; overflow-y: auto; flex: 1; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .field { display: flex; flex-direction: column; gap: 6px; }
        .field.full { grid-column: 1 / -1; }
        .field label { font-size: 14px; font-weight: 600; color: #374151; }
        .field input, .field select, .field textarea { padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; width: 100%; font-family: inherit; }
        .modal-footer { padding: 20px 24px; border-top: 1px solid #eef2f7; display: flex; justify-content: flex-end; gap: 12px; background: #f9fbff; }
        .btn-secondary { background: #fff; border: 1px solid #d1d5db; color: #374151; padding: 10px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: .2s; }
        .btn-secondary:hover { background: #f3f4f6; }
        .btn-save { background: #288CFA; border: none; color: #fff; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: .2s; }
        .btn-save:hover { background: #1c7ad0; }

        @media (max-width: 1200px){.stats,.grid-3{grid-template-columns:repeat(2,minmax(0,1fr));}.main-grid,.page-shell{grid-template-columns:1fr;}.sidebar{height:auto;position:relative;}}
        @media (max-width: 640px){.stats,.grid-3{grid-template-columns:1fr;}.header-row{flex-direction:column;align-items:flex-start;}.content{padding:16px;}.table{display:block;overflow-x:auto;}}
    </style>
</head>
<body class="accounts-page">
<div class="page-shell">
    <aside class="sidebar">
        <div class="brand">
            <img src="assets/images/logoSemFundo.png" alt="Life Finance">
            <div><h1>Life Finance</h1><p>Finanças pessoais</p></div>
        </div>
        <nav class="menu">
            <a href="dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
            <a href="#" class="active"><i class="fa-solid fa-wallet"></i> Contas</a>
            <a href="movimentacoes.php"><i class="fa-solid fa-right-left"></i> Movimentações</a>
            <a href="cartoes.php"><i class="fa-solid fa-credit-card"></i> Cartões</a>
            <a href="categorias.php"><i class="fa-solid fa-tags"></i> Categorias</a>
            <a href="orcamentos.php"><i class="fa-solid fa-chart-pie"></i> Orçamentos</a>
            <a href="metas.php"><i class="fa-solid fa-bullseye"></i> Metas</a>
            <a href="relatorios.php"><i class="fa-solid fa-chart-column"></i> Relatórios</a>
            <a href="configuracoes.php"><i class="fa-solid fa-gear"></i> Configurações</a>
            <a href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Sair</a>
        </nav>
    </aside>

    <main class="content">
        <div class="header-row">
            <div>
                <h2>Contas</h2>
                <p>Gerencie contas bancárias, carteiras, dinheiro físico e cartões vinculados.</p>
            </div>
            <a href="#" class="btn-primary" onclick="abrirModalConta(event)"><i class="fa-solid fa-plus"></i> Nova conta</a>
        </div>

        <section class="stats">
            <div class="card stat-top"><div><h3>Saldo consolidado</h3><strong><?php echo $saldoTotalStr; ?></strong><div class="muted">Soma de todas as contas</div></div><div class="icon"><i class="fa-solid fa-sack-dollar"></i></div></div>
            <div class="card stat-top"><div><h3>Contas ativas</h3><strong><?php echo $dados['contasAtivas']; ?></strong><div class="muted">Bancos, carteira e dinheiro</div></div><div class="icon"><i class="fa-solid fa-university"></i></div></div>
            <div class="card stat-top"><div><h3>Cartões vinculados</h3><strong><?php echo $dados['cartoesVinculados']; ?></strong><div class="muted">Crédito e débito</div></div><div class="icon"><i class="fa-solid fa-credit-card"></i></div></div>
            <div class="card stat-top"><div><h3>Última sincronização</h3><strong><?php echo htmlspecialchars($dados['ultimaSincronizacao']); ?></strong><div class="muted">Atualização manual/automática</div></div><div class="icon"><i class="fa-solid fa-arrows-rotate"></i></div></div>
        </section>

        <section class="main-grid">
            <div class="card">
                <div class="section-title">
                    <div><h3>Lista de contas</h3><div class="muted">Visão detalhada do saldo e tipo de conta</div></div>
                    <span class="account-badge bank"><i class="fa-solid fa-circle-info"></i> <?php echo count($dados['listaContas']); ?> registros</span>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Conta</th>
                            <th>Tipo</th>
                            <th>Saldo</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dados['listaContas'])): ?>
                        <tr><td colspan="5" style="text-align: center;" class="muted">Nenhuma conta cadastrada.</td></tr>
                        <?php else: ?>
                            <?php foreach ($dados['listaContas'] as $c): 
                                $isCard = ($c['tipo_categoria'] === 'CARTAO');
                                $isBank = ($c['tipo_categoria'] === 'BANCO');
                                $isCash = ($c['tipo_categoria'] === 'DINHEIRO');
                                $badgeClass = $isBank ? 'bank' : ($isCard ? 'card-badge' : 'cash');
                                $saldoFormat = 'R$ ' . number_format($c['saldo'], 2, ',', '.');
                            ?>
                            <tr class="data-row">
                                <td><?php echo htmlspecialchars($c['nome']); ?> <?php if ($c['principal']) echo '<span style="font-size:10px; color:#f59e0b; margin-left:4px;" title="Conta Principal"><i class="fa-solid fa-star"></i></span>'; ?></td>
                                <td><span class="account-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($c['tipo_nome'] ?? 'Outros'); ?></span></td>
                                <td><?php echo $saldoFormat; ?></td>
                                <td>
                                    <?php if ($c['ativa']): ?>
                                        <span class="account-badge cash">Ativa</span>
                                    <?php else: ?>
                                        <span class="account-badge" style="background:#f1f5f9; color:#64748b;">Inativa</span>
                                    <?php endif; ?>
                                </td>
                                <td><button class="action-btn"><i class="fa-solid fa-pen"></i></button> <button class="action-btn"><i class="fa-solid fa-trash"></i></button></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <div class="section-title">
                    <div><h3>Resumo rápido</h3><div class="muted">Distribuição do patrimônio por conta</div></div>
                    <span class="account-badge bank"><i class="fa-solid fa-chart-pie"></i> visão geral</span>
                </div>
                <div class="right-list">
                    <?php if ($dados['painelLateral']['contaPrincipal']): 
                        $cp = $dados['painelLateral']['contaPrincipal'];
                        $bClass = ($cp['categoria'] === 'BANCO') ? 'bank' : (($cp['categoria'] === 'CARTAO') ? 'card-badge' : 'cash');
                    ?>
                        <div class="mini"><div><strong><?php echo htmlspecialchars($cp['nome']); ?> <i class="fa-solid fa-star" style="color:#f59e0b; font-size:10px;"></i></strong><div class="muted"><?php echo $cp['pct']; ?>% do saldo total</div></div><span class="account-badge <?php echo $bClass; ?>">R$ <?php echo number_format($cp['saldo'], 2, ',', '.'); ?></span></div>
                    <?php endif; ?>
                    
                    <?php foreach ($dados['painelLateral']['resumoContas'] as $r): 
                        $bClass = ($r['categoria'] === 'BANCO') ? 'bank' : (($r['categoria'] === 'CARTAO') ? 'card-badge' : 'cash');
                    ?>
                        <div class="mini"><div><strong><?php echo htmlspecialchars($r['nome']); ?></strong><div class="muted"><?php echo $r['pct']; ?>% do saldo total</div></div><span class="account-badge <?php echo $bClass; ?>">R$ <?php echo number_format($r['saldo'], 2, ',', '.'); ?></span></div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:18px;">
                    <strong><?php echo htmlspecialchars($dados['painelLateral']['metaSaldo']['nome']); ?></strong>
                    <div class="muted">Objetivo: R$ <?php echo number_format($metaVal, 2, ',', '.'); ?></div>
                    <div class="progress"><span style="width:<?php echo $dados['painelLateral']['metaSaldo']['pct']; ?>%"></span></div>
                    <div class="small-note"><?php echo $dados['painelLateral']['metaSaldo']['pct'] >= 100 ? 'Meta atingida!' : 'Faltam ' . $metaFalta . ' para atingir a meta.'; ?></div>
                </div>
            </div>
        </section>

        <section class="grid-3">
            <div class="card">
                <div class="section-title"><div><h3>Contas ativas</h3><div class="muted">Status operacional</div></div></div>
                <strong style="font-size:28px;"><?php echo $dados['contasAtivas']; ?></strong>
                <div class="small-note">Todas as contas listadas estão habilitadas.</div>
            </div>
            <div class="card">
                <div class="section-title"><div><h3>Limite de crédito</h3><div class="muted">Cartões vinculados</div></div></div>
                <strong style="font-size:28px;"><?php echo $dados['limiteCreditoUsado']; ?>%</strong>
                <div class="small-note">Uso médio do limite (fatura vs total limite).</div>
            </div>
            <div class="card">
                <div class="section-title"><div><h3>Saldo projetado</h3><div class="muted">Fim do mês</div></div></div>
                <?php 
                    $colorProjetado = $dados['saldoProjetadoStatus'] === 'Positivo' ? '#2E865F' : ($dados['saldoProjetadoStatus'] === 'Negativo' ? '#991b1b' : '#6b7280'); 
                ?>
                <strong style="font-size:28px; color:<?php echo $colorProjetado; ?>;"><?php echo $dados['saldoProjetadoStatus']; ?></strong>
                <div class="small-note">O saldo projetado para as faturas e contas a pagar.</div>
            </div>
        </section>
    </main>
</div>

<!-- Modal Nova Conta -->
<div class="modal-backdrop" id="novaContaModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Nova conta</h3>
            <button class="modal-close" type="button" onclick="fecharModalConta()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="salvar_conta.php">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="field full">
                        <label for="nome">Nome da conta</label>
                        <input type="text" id="nome" name="nome" required placeholder="Ex: Nubank, Carteira Física...">
                    </div>
                    
                    <div class="field">
                        <label for="id_tipo_conta">Tipo de conta</label>
                        <select id="id_tipo_conta" name="id_tipo_conta" required onchange="toggleCamposCartao()">
                            <option value="">Selecione</option>
                            <?php foreach ($tiposConta as $t): ?>
                                <option value="<?php echo $t['id']; ?>" data-categoria="<?php echo htmlspecialchars($t['categoria']); ?>">
                                    <?php echo htmlspecialchars($t['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="saldo">Saldo Inicial</label>
                        <input type="text" id="saldo" name="saldo" required placeholder="0,00" value="0,00">
                    </div>
                    
                    <!-- Campos específicos de Cartão de Crédito -->
                    <div class="field campos-cartao" style="display: none;">
                        <label for="limite">Limite do Cartão</label>
                        <input type="text" id="limite" name="limite" placeholder="0,00">
                    </div>
                    
                    <div class="field campos-cartao" style="display: none;">
                        <label for="bandeira">Bandeira</label>
                        <select id="bandeira" name="bandeira">
                            <option value="">Selecione</option>
                            <option value="Mastercard">Mastercard</option>
                            <option value="Visa">Visa</option>
                            <option value="Elo">Elo</option>
                            <option value="American Express">American Express</option>
                            <option value="Outros">Outros</option>
                        </select>
                    </div>

                    <div class="field campos-cartao" style="display: none;">
                        <label for="dia_vencimento">Dia de Vencimento</label>
                        <input type="number" id="dia_vencimento" name="dia_vencimento" min="1" max="31" placeholder="Ex: 10">
                    </div>

                    <div class="field campos-cartao" style="display: none;">
                        <label for="dia_fechamento">Dia de Fechamento</label>
                        <input type="number" id="dia_fechamento" name="dia_fechamento" min="1" max="31" placeholder="Ex: 3">
                    </div>

                    <div class="field full">
                        <label for="descricao">Descrição (Opcional)</label>
                        <textarea id="descricao" name="descricao" placeholder="Detalhes sobre a conta..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="fecharModalConta()">Cancelar</button>
                <button type="submit" class="btn-save"><i class="fa-solid fa-check"></i> Salvar conta</button>
            </div>
        </form>
    </div>
</div>

<script>
    function abrirModalConta(e) {
        if(e) e.preventDefault();
        document.getElementById('novaContaModal').classList.add('active');
    }

    function fecharModalConta() {
        document.getElementById('novaContaModal').classList.remove('active');
    }

    function toggleCamposCartao() {
        const select = document.getElementById('id_tipo_conta');
        const selectedOption = select.options[select.selectedIndex];
        const categoria = selectedOption.getAttribute('data-categoria');
        const camposCartao = document.querySelectorAll('.campos-cartao');
        
        const isCartao = categoria === 'CARTAO';
        camposCartao.forEach(campo => {
            campo.style.display = isCartao ? 'flex' : 'none';
            const input = campo.querySelector('input, select');
            if (input) {
                if (isCartao) {
                    input.setAttribute('required', 'required');
                } else {
                    input.removeAttribute('required');
                }
            }
        });
    }

    const formataMoeda = (e) => {
        let value = e.target.value.replace(/\D/g,"");
        if(value === "") value = "0";
        value = (parseInt(value)/100).toFixed(2) + "";
        value = value.replace(".", ",");
        value = value.replace(/(\d)(\d{3})(\d{3}),/g, "$1.$2.$3,");
        value = value.replace(/(\d)(\d{3}),/g, "$1.$2,");
        e.target.value = value;
    }
    
    document.getElementById('saldo').addEventListener('keyup', formataMoeda);
    document.getElementById('limite').addEventListener('keyup', formataMoeda);
</script>
</body>
</html>
