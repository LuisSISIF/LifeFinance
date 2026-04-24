<?php

/*
|--------------------------------------------------------------------------
| Serviço do Dashboard
|--------------------------------------------------------------------------
| Esta classe concentra toda a lógica de negócio usada pela página inicial.
| Ela organiza consultas, cálculos e montagem dos dados exibidos no painel.
*/
class DashboardService
{
    /**
     * Retorna todos os dados necessários para o dashboard.
     *
     * @param PDO $pdo
     * @param int $userId
     * @param string|null $mesReferencia
     * @return array
     */
    public static function getDashboardData(PDO $pdo, int $userId, string $mesReferencia = null): array
    {
        /*
        |--------------------------------------------------------------------------
        | Definição do mês de referência
        |--------------------------------------------------------------------------
        | Se nenhum mês for informado, usa o mês atual.
        */
        if (!$mesReferencia) {
            $mesReferencia = date('Y-m');
        }

        /*
        |--------------------------------------------------------------------------
        | Estrutura padrão de retorno
        |--------------------------------------------------------------------------
        | Mantém uma saída consistente mesmo quando não houver dados no banco.
        */
        $dados = [
            'nomeUsuario' => 'Usuário',
            'mesExtenso' => self::getMesExtenso($mesReferencia),
            'saldoTotal' => 0.0,
            'receitasMes' => 0.0,
            'despesasMes' => 0.0,
            'qtdReceitas' => 0,
            'qtdDespesas' => 0,
            'variacaoSaldo' => 0.0,
            'orcamentoMes' => 0,
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

        /*
        |--------------------------------------------------------------------------
        | 1. Nome do usuário
        |--------------------------------------------------------------------------
        | Busca o nome do perfil vinculado ao usuário logado.
        */
        $stmt = $pdo->prepare('SELECT nome FROM perfis_usuarios WHERE id_usuario = ? LIMIT 1');
        $stmt->execute([$userId]);
        if ($perfil = $stmt->fetch()) {
            $dados['nomeUsuario'] = $perfil['nome'];
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Saldo total
        |--------------------------------------------------------------------------
        | Soma o saldo das contas ativas do usuário.
        */
        $stmt = $pdo->prepare('SELECT SUM(saldo) as total FROM contas WHERE id_usuario = ? AND ativa = 1');
        $stmt->execute([$userId]);
        $saldo = $stmt->fetch();
        $dados['saldoTotal'] = (float) ($saldo['total'] ?? 0);

        /*
        |--------------------------------------------------------------------------
        | 3. Receitas e despesas do mês
        |--------------------------------------------------------------------------
        | Consolida valores e quantidades por tipo.
        */
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

        /*
        |--------------------------------------------------------------------------
        | 3.5 Variação do saldo
        |--------------------------------------------------------------------------
        | Estima a evolução do saldo em relação ao mês anterior.
        */
        $saldoMesAnterior = $dados['saldoTotal'] - $dados['receitasMes'] + $dados['despesasMes'];
        if ($saldoMesAnterior != 0) {
            $dados['variacaoSaldo'] = (($dados['saldoTotal'] - $saldoMesAnterior) / abs($saldoMesAnterior)) * 100;
        } elseif ($dados['saldoTotal'] > 0) {
            $dados['variacaoSaldo'] = 100.0;
        } elseif ($dados['saldoTotal'] < 0) {
            $dados['variacaoSaldo'] = -100.0;
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Orçamento utilizado
        |--------------------------------------------------------------------------
        | Calcula o percentual consumido sobre o orçamento ativo do mês.
        */
        $stmt = $pdo->prepare('SELECT SUM(valor_total) as total FROM orcamentos WHERE id_usuario = ? AND mes_referencia = ? AND ativa = 1');
        $stmt->execute([$userId, $mesReferencia]);
        $orcamento = $stmt->fetch();
        $totalOrcamento = (float) ($orcamento['total'] ?? 0);

        if ($totalOrcamento > 0) {
            $dados['orcamentoMes'] = min(100, round(($dados['despesasMes'] / $totalOrcamento) * 100));
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Metas financeiras
        |--------------------------------------------------------------------------
        | Busca metas ativas e calcula progresso consolidado.
        */
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

        /*
        |--------------------------------------------------------------------------
        | 6. Próximos compromissos
        |--------------------------------------------------------------------------
        | Junta contas a pagar e a receber em uma única lista.
        */
        $hoje = date('Y-m-d');
        $stmt = $pdo->prepare("
            (SELECT nome, vence_em, valor_total, status, 'PAGAR' as tipo
             FROM contas_pagar
             WHERE id_usuario = ? AND status IN ('PENDENTE', 'VENCIDO') AND vence_em >= ?)
            UNION ALL
            (SELECT nome, vence_em, valor_total, status, 'RECEBER' as tipo
             FROM contas_receber
             WHERE id_usuario = ? AND status IN ('PENDENTE', 'VENCIDO') AND vence_em >= ?)
            ORDER BY vence_em ASC
            LIMIT 3
        ");
        $stmt->execute([$userId, $hoje, $userId, $hoje]);
        $dados['compromissos'] = $stmt->fetchAll();

        /*
        |--------------------------------------------------------------------------
        | 7. Gastos por categoria
        |--------------------------------------------------------------------------
        | Retorna as principais categorias de despesa do mês.
        */
        $stmt = $pdo->prepare("
            SELECT c.nome, SUM(m.valor) as total
            FROM movimentacoes m
            JOIN categorias c ON m.id_categoria = c.id
            WHERE m.id_usuario = ? AND m.tipo = 'DESPESA' AND DATE_FORMAT(m.ocorreu_em, '%Y-%m') = ?
            GROUP BY c.id
            ORDER BY total DESC
            LIMIT 3
        ");
        $stmt->execute([$userId, $mesReferencia]);
        $dados['gastosCategoria'] = $stmt->fetchAll();

        /*
        |--------------------------------------------------------------------------
        | 8. Gráfico financeiro
        |--------------------------------------------------------------------------
        | Monta séries dos últimos 6 meses para o Chart.js.
        */
        $meses = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = date('Y-m', strtotime("-$i months"));
            $meses[$d] = ['receita' => 0, 'despesa' => 0];
            $dados['grafico']['labels'][] = self::getMesExtenso($d, true);
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
                if ($row['tipo'] === 'RECEITA') {
                    $meses[$m]['receita'] = (float) $row['total'];
                }
                if ($row['tipo'] === 'DESPESA') {
                    $meses[$m]['despesa'] = (float) $row['total'];
                }
            }
        }

        foreach ($meses as $m => $v) {
            $dados['grafico']['receitas'][] = $v['receita'];
            $dados['grafico']['despesas'][] = $v['despesa'];
        }

        /*
        |--------------------------------------------------------------------------
        | 9. Alertas inteligentes
        |--------------------------------------------------------------------------
        | Cria mensagens rápidas com base em regras simples de análise.
        */
        if ($dados['saldoTotal'] < 0) {
            $dados['alertas'][] = [
                'titulo' => 'Saldo negativo',
                'desc' => 'Seu saldo geral está abaixo de zero.',
                'tipo' => 'bad'
            ];
        } elseif (($dados['saldoTotal'] + $dados['receitasMes'] - $dados['despesasMes']) > 0) {
            $dados['alertas'][] = [
                'titulo' => 'Saldo projetado positivo',
                'desc' => 'Sem risco de insuficiência.',
                'tipo' => 'ok'
            ];
        }

        if ($dados['orcamentoMes'] >= 90) {
            $dados['alertas'][] = [
                'titulo' => 'Orçamento no limite',
                'desc' => 'Você já usou ' . $dados['orcamentoMes'] . '% do orçamento.',
                'tipo' => 'warn'
            ];
        }

        if (empty($dados['alertas'])) {
            $dados['alertas'][] = [
                'titulo' => 'Tudo em ordem',
                'desc' => 'Suas finanças estão sob controle.',
                'tipo' => 'ok'
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | 10. Calendário financeiro
        |--------------------------------------------------------------------------
        | Gera uma grade mensal com indicação de dias com eventos.
        */
        $diasNoMes = date('t', strtotime($mesReferencia . '-01'));
        $diaAtual = ($mesReferencia === date('Y-m')) ? (int)date('d') : 0;

        $diasComEventos = [];
        $stmt = $pdo->prepare("
            SELECT DAY(vence_em) as dia FROM contas_pagar
            WHERE id_usuario = ? AND mes_referencia = ? AND status IN ('PENDENTE', 'VENCIDO')
            UNION
            SELECT DAY(vence_em) as dia FROM contas_receber
            WHERE id_usuario = ? AND mes_referencia = ? AND status IN ('PENDENTE', 'VENCIDO')
        ");
        $stmt->execute([$userId, $mesReferencia, $userId, $mesReferencia]);

        while ($r = $stmt->fetch()) {
            $diasComEventos[] = (int)$r['dia'];
        }

        $primeiroDiaSemana = date('N', strtotime($mesReferencia . '-01'));
        $calendarioHtml = '';
        $diasDaSemana = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];

        foreach ($diasDaSemana as $d) {
            $calendarioHtml .= '<div class="day" style="font-weight:bold; background:transparent; border:none;">' . $d . '</div>';
        }

        for ($i = 1; $i < $primeiroDiaSemana; $i++) {
            $calendarioHtml .= '<div class="day" style="background:transparent; border:none;"></div>';
        }

        for ($dia = 1; $dia <= $diasNoMes; $dia++) {
            $class = 'day';
            if ($dia === $diaAtual) {
                $class .= ' today';
            }
            if (in_array($dia, $diasComEventos)) {
                $class .= ' event';
            }

            $style = in_array($dia, $diasComEventos) ? 'border-bottom: 2px solid var(--danger);' : '';
            $calendarioHtml .= '<div class="' . $class . '" style="' . $style . '">' . $dia . '</div>';
        }

        $dados['calendarioHtml'] = $calendarioHtml;

        return $dados;
    }

    /**
     * Converte um mês no formato YYYY-MM para nome extenso ou curto.
     *
     * @param string $ym
     * @param bool $curto
     * @return string
     */
    private static function getMesExtenso(string $ym, bool $curto = false): string
    {
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
        if (count($partes) !== 2) {
            return $ym;
        }

        $m = $partes[1];
        $y = $partes[0];

        if ($curto) {
            return $mesesCurtos[$m] ?? $m;
        }

        return ($meses[$m] ?? $m) . ' / ' . $y;
    }

    /**
     * Retorna os filtros usados no modal de novo lançamento.
     *
     * @param PDO $pdo
     * @param int $userId
     * @return array
     */
    public static function getFiltrosModal(PDO $pdo, int $userId): array
    {
        $filtros = ['contas' => [], 'categorias' => []];

        /*
        |--------------------------------------------------------------------------
        | Contas ativas
        |--------------------------------------------------------------------------
        | Lista contas disponíveis para seleção no modal.
        */
        $stmt = $pdo->prepare('SELECT id, nome, codigo_moeda FROM contas WHERE id_usuario = ? AND ativa = 1 ORDER BY nome ASC');
        $stmt->execute([$userId]);
        $filtros['contas'] = $stmt->fetchAll();

        /*
        |--------------------------------------------------------------------------
        | Categorias ativas
        |--------------------------------------------------------------------------
        | Lista categorias disponíveis para o novo lançamento.
        */
        $stmt = $pdo->prepare('SELECT id, nome, tipo FROM categorias WHERE id_usuario = ? AND ativa = 1 ORDER BY tipo, nome ASC');
        $stmt->execute([$userId]);
        $filtros['categorias'] = $stmt->fetchAll();

        return $filtros;
    }
}