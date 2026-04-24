<?php

class DashboardService {
    
    public static function getDashboardData(PDO $pdo, int $userId, string $mesReferencia = null): array {
        if (!$mesReferencia) {
            $mesReferencia = date('Y-m'); // Ex: 2026-04
        }
        
        $dados = [
            'nomeUsuario' => 'Usuário',
            'mesExtenso' => self::getMesExtenso($mesReferencia),
            'saldoTotal' => 0.0,
            'receitasMes' => 0.0,
            'despesasMes' => 0.0,
            'qtdReceitas' => 0,
            'qtdDespesas' => 0,
            'variacaoSaldo' => 0.0,
            'orcamentoMes' => 0, // Porcentagem
            'metas' => [],
            'metaMesProgresso' => 0,
            'compromissos' => [],
            'calendario' => [],
            'gastosCategoria' => [],
            'alertas' => [],
            'grafico' => [
                'labels' => [],
                'receitas' => [],
                'despesas' => []
            ]
        ];

        // 1. Nome do Usuário
        $stmt = $pdo->prepare('SELECT nome FROM perfis_usuarios WHERE id_usuario = ? LIMIT 1');
        $stmt->execute([$userId]);
        if ($perfil = $stmt->fetch()) {
            $dados['nomeUsuario'] = $perfil['nome'];
        }

        // 2. Saldo Total
        $stmt = $pdo->prepare('SELECT SUM(saldo) as total FROM contas WHERE id_usuario = ? AND ativa = 1');
        $stmt->execute([$userId]);
        $saldo = $stmt->fetch();
        $dados['saldoTotal'] = (float) ($saldo['total'] ?? 0);

        // 3. Receitas e Despesas do Mês (Valores e Contagens)
        $stmt = $pdo->prepare("
            SELECT tipo, SUM(valor) as total, COUNT(*) as qtd
            FROM movimentacoes 
            WHERE id_usuario = ? AND DATE_FORMAT(ocorreu_em, '%Y-%m') = ? AND status IN ('PAGO', 'EFETIVADA')
            GROUP BY tipo
        ");
        $stmt->execute([$userId, $mesReferencia]);
        while ($row = $stmt->fetch()) {
            if ($row['tipo'] === 'RECEITA') {
                $dados['receitasMes'] = (float) $row['total'];
                $dados['qtdReceitas'] = (int) $row['qtd'];
            } elseif ($row['tipo'] === 'DESPESA') {
                $dados['despesasMes'] = (float) $row['total'];
                $dados['qtdDespesas'] = (int) $row['qtd'];
            }
        }

        // 3.5. Variação do Saldo no Mês
        // Estimativa do saldo no final do mês anterior = saldoAtual - receitas(mês) + despesas(mês)
        $saldoMesAnterior = $dados['saldoTotal'] - $dados['receitasMes'] + $dados['despesasMes'];
        if ($saldoMesAnterior != 0) {
            $dados['variacaoSaldo'] = (($dados['saldoTotal'] - $saldoMesAnterior) / abs($saldoMesAnterior)) * 100;
        } elseif ($dados['saldoTotal'] > 0) {
            $dados['variacaoSaldo'] = 100.0;
        } elseif ($dados['saldoTotal'] < 0) {
            $dados['variacaoSaldo'] = -100.0;
        }

        // 4. Orçamento Usado
        $stmt = $pdo->prepare('SELECT SUM(valor_total) as total FROM orcamentos WHERE id_usuario = ? AND mes_referencia = ? AND ativa = 1');
        $stmt->execute([$userId, $mesReferencia]);
        $orcamento = $stmt->fetch();
        $totalOrcamento = (float) ($orcamento['total'] ?? 0);
        if ($totalOrcamento > 0) {
            $dados['orcamentoMes'] = min(100, round(($dados['despesasMes'] / $totalOrcamento) * 100));
        }

        // 5. Metas Financeiras
        $stmt = $pdo->prepare('SELECT nome, valor_meta, valor_atual FROM metas_financeiras WHERE id_usuario = ? AND ativa = 1 LIMIT 2');
        $stmt->execute([$userId]);
        $dados['metas'] = $stmt->fetchAll();
        
        $totalAtual = 0;
        $totalMeta = 0;
        foreach ($dados['metas'] as $meta) {
            $totalAtual += $meta['valor_atual'];
            $totalMeta += $meta['valor_meta'];
        }
        if ($totalMeta > 0) {
            $dados['metaMesProgresso'] = min(100, round(($totalAtual / $totalMeta) * 100));
        }

        // 6. Próximos Compromissos
        $hoje = date('Y-m-d');
        $stmt = $pdo->prepare("
            (SELECT nome, vence_em, valor_total, status, 'PAGAR' as tipo 
             FROM contas_pagar 
             WHERE id_usuario = ? AND status IN ('PENDENTE', 'VENCIDO') AND vence_em >= ?)
            UNION ALL
            (SELECT nome, vence_em, valor_total, status, 'RECEBER' as tipo 
             FROM contas_receber 
             WHERE id_usuario = ? AND status IN ('PENDENTE', 'VENCIDO') AND vence_em >= ?)
            ORDER BY vence_em ASC LIMIT 3
        ");
        $stmt->execute([$userId, $hoje, $userId, $hoje]);
        $dados['compromissos'] = $stmt->fetchAll();

        // 7. Gastos por Categoria
        $stmt = $pdo->prepare("
            SELECT c.nome, SUM(m.valor) as total 
            FROM movimentacoes m 
            JOIN categorias c ON m.id_categoria = c.id 
            WHERE m.id_usuario = ? AND m.tipo = 'DESPESA' AND DATE_FORMAT(m.ocorreu_em, '%Y-%m') = ?
            GROUP BY c.id 
            ORDER BY total DESC LIMIT 3
        ");
        $stmt->execute([$userId, $mesReferencia]);
        $dados['gastosCategoria'] = $stmt->fetchAll();

        // 8. Gráfico Financeiro (Últimos 6 meses)
        $meses = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = date('Y-m', strtotime("-$i months"));
            $meses[$d] = ['receita' => 0, 'despesa' => 0];
            $dados['grafico']['labels'][] = self::getMesExtenso($d, true); // Nome curto
        }
        
        $dataInicio = date('Y-m-01', strtotime("-5 months"));
        $stmt = $pdo->prepare("
            SELECT DATE_FORMAT(ocorreu_em, '%Y-%m') as mes, tipo, SUM(valor) as total 
            FROM movimentacoes 
            WHERE id_usuario = ? AND ocorreu_em >= ?
            GROUP BY mes, tipo
        ");
        $stmt->execute([$userId, $dataInicio]);
        while ($row = $stmt->fetch()) {
            $m = $row['mes'];
            if (isset($meses[$m])) {
                if ($row['tipo'] === 'RECEITA') $meses[$m]['receita'] = (float) $row['total'];
                if ($row['tipo'] === 'DESPESA') $meses[$m]['despesa'] = (float) $row['total'];
            }
        }
        foreach ($meses as $m => $v) {
            $dados['grafico']['receitas'][] = $v['receita'];
            $dados['grafico']['despesas'][] = $v['despesa'];
        }

        // 9. Alertas Inteligentes
        if ($dados['saldoTotal'] < 0) {
            $dados['alertas'][] = ['titulo' => 'Saldo negativo', 'desc' => 'Seu saldo geral está abaixo de zero.', 'tipo' => 'bad'];
        } elseif (($dados['saldoTotal'] + $dados['receitasMes'] - $dados['despesasMes']) > 0) {
            $dados['alertas'][] = ['titulo' => 'Saldo projetado positivo', 'desc' => 'Sem risco de insuficiência.', 'tipo' => 'ok'];
        }
        
        if ($dados['orcamentoMes'] >= 90) {
            $dados['alertas'][] = ['titulo' => 'Orçamento no limite', 'desc' => 'Você já usou '.$dados['orcamentoMes'].'% do orçamento.', 'tipo' => 'warn'];
        }

        if (empty($dados['alertas'])) {
             $dados['alertas'][] = ['titulo' => 'Tudo em ordem', 'desc' => 'Suas finanças estão sob controle.', 'tipo' => 'ok'];
        }

        // 10. Calendário
        $diasNoMes = date('t', strtotime($mesReferencia . '-01'));
        $diaAtual = ($mesReferencia === date('Y-m')) ? (int)date('d') : 0;
        
        // Buscar dias com eventos
        $diasComEventos = [];
        $stmt = $pdo->prepare("
            SELECT DAY(vence_em) as dia FROM contas_pagar WHERE id_usuario = ? AND mes_referencia = ? AND status IN ('PENDENTE', 'VENCIDO')
            UNION 
            SELECT DAY(vence_em) as dia FROM contas_receber WHERE id_usuario = ? AND mes_referencia = ? AND status IN ('PENDENTE', 'VENCIDO')
        ");
        $stmt->execute([$userId, $mesReferencia, $userId, $mesReferencia]);
        while ($r = $stmt->fetch()) {
            $diasComEventos[] = (int)$r['dia'];
        }

        // Montar os dias. Assumimos o começo na segunda-feira para simplificar ou usamos a data real
        $primeiroDiaSemana = date('N', strtotime($mesReferencia . '-01')); // 1 (Seg) a 7 (Dom)
        
        $calendarioHtml = '';
        $diasDaSemana = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
        foreach ($diasDaSemana as $d) {
            $calendarioHtml .= '<div class="day" style="font-weight:bold; background:transparent; border:none;">'.$d.'</div>';
        }
        
        // Espaços vazios
        for ($i = 1; $i < $primeiroDiaSemana; $i++) {
            $calendarioHtml .= '<div class="day" style="background:transparent; border:none;"></div>';
        }
        
        for ($dia = 1; $dia <= $diasNoMes; $dia++) {
            $class = 'day';
            if ($dia === $diaAtual) $class .= ' today';
            if (in_array($dia, $diasComEventos)) $class .= ' event'; // Poderia adicionar um ponto vermelho via CSS
            
            $style = in_array($dia, $diasComEventos) ? 'border-bottom: 2px solid var(--danger);' : '';
            $calendarioHtml .= '<div class="'.$class.'" style="'.$style.'">'.$dia.'</div>';
        }
        $dados['calendarioHtml'] = $calendarioHtml;

        return $dados;
    }

    private static function getMesExtenso(string $ym, bool $curto = false): string {
        $meses = [
            '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
            '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
            '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
        ];
        $mesesCurtos = [
            '01' => 'Jan', '02' => 'Fev', '03' => 'Mar', '04' => 'Abr',
            '05' => 'Mai', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago',
            '09' => 'Set', '10' => 'Out', '11' => 'Nov', '12' => 'Dez'
        ];
        
        $partes = explode('-', $ym);
        if (count($partes) !== 2) return $ym;
        
        $m = $partes[1];
        $y = $partes[0];
        
        if ($curto) {
            return ($mesesCurtos[$m] ?? $m);
        }
        return ($meses[$m] ?? $m) . ' / ' . $y;
    }

    public static function getFiltrosModal(PDO $pdo, int $userId): array {
        $filtros = ['contas' => [], 'categorias' => []];

        // Buscar contas ativas
        $stmt = $pdo->prepare('SELECT id, nome, codigo_moeda FROM contas WHERE id_usuario = ? AND ativa = 1 ORDER BY nome ASC');
        $stmt->execute([$userId]);
        $filtros['contas'] = $stmt->fetchAll();

        // Buscar categorias ativas
        $stmt = $pdo->prepare('SELECT id, nome, tipo FROM categorias WHERE id_usuario = ? AND ativa = 1 ORDER BY tipo, nome ASC');
        $stmt->execute([$userId]);
        $filtros['categorias'] = $stmt->fetchAll();

        return $filtros;
    }
}
