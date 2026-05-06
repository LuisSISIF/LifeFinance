<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Life Finance | Contas</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/contas.css">
</head>
<body class="accounts-page">
<div class="page-shell">
    <aside class="sidebar">
        <div class="brand">
            <img src="<?php echo BASE_URL; ?>/assets/images/logoSemFundo.png" alt="Life Finance">
            <div><h1>Life Finance</h1><p>Finanças pessoais</p></div>
        </div>
        <nav class="menu">
            <a href="<?php echo BASE_URL; ?>/dashboard"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
            <a href="<?php echo BASE_URL; ?>/contas" class="active"><i class="fa-solid fa-wallet"></i> Contas</a>
            <a href="<?php echo BASE_URL; ?>/movimentacoes"><i class="fa-solid fa-right-left"></i> Movimentações</a>
            <a href="<?php echo BASE_URL; ?>/cartoes"><i class="fa-solid fa-credit-card"></i> Cartões</a>
            <a href="<?php echo BASE_URL; ?>/categorias"><i class="fa-solid fa-tags"></i> Categorias</a>
            <a href="<?php echo BASE_URL; ?>/orcamentos"><i class="fa-solid fa-chart-pie"></i> Orçamentos</a>
            <a href="<?php echo BASE_URL; ?>/metas"><i class="fa-solid fa-bullseye"></i> Metas</a>
            <a href="<?php echo BASE_URL; ?>/relatorios"><i class="fa-solid fa-chart-column"></i> Relatórios</a>
            <a href="<?php echo BASE_URL; ?>/configuracoes"><i class="fa-solid fa-gear"></i> Configurações</a>
            <a href="<?php echo BASE_URL; ?>/auth/logout"><i class="fa-solid fa-arrow-right-from-bracket"></i> Sair</a>
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
                                <td>
                                    <button class="action-btn" onclick='abrirEditModalConta(<?php echo json_encode($c, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>)'><i class="fa-solid fa-pen"></i></button> 
                                    <a class="action-btn" href="<?php echo BASE_URL; ?>/contas/delete?id=<?php echo (int)$c['id']; ?>" onclick="return confirm('Tem certeza que deseja excluir esta conta?');" style="display:inline-block; text-decoration:none; color:inherit;"><i class="fa-solid fa-trash"></i></a>
                                </td>
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

        <form id="contaForm" method="POST" action="<?php echo BASE_URL; ?>/contas/store">
            <input type="hidden" name="id" id="conta_id" value="">
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
        document.getElementById('contaForm').action = '<?php echo BASE_URL; ?>/contas/store';
        document.getElementById('novaContaModal').querySelector('.modal-header h3').innerText = 'Nova conta';
        document.getElementById('contaForm').reset();
        document.getElementById('conta_id').value = '';
        document.getElementById('saldo').value = '0,00';
        toggleCamposCartao();
        document.getElementById('novaContaModal').classList.add('active');
    }

    function abrirEditModalConta(c) {
        document.getElementById('contaForm').action = '<?php echo BASE_URL; ?>/contas/update';
        document.getElementById('novaContaModal').querySelector('.modal-header h3').innerText = 'Editar conta';
        document.getElementById('conta_id').value = c.id;
        document.getElementById('nome').value = c.nome || '';
        document.getElementById('id_tipo_conta').value = c.id_tipo_conta || '';
        
        // Format saldo for the mask (10.50 -> 10,50)
        let saldoVal = parseFloat(c.saldo || 0).toFixed(2).replace('.', ',');
        document.getElementById('saldo').value = saldoVal;
        
        document.getElementById('descricao').value = c.descricao || '';
        
        toggleCamposCartao();
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
