<?php

/*
|--------------------------------------------------------------------------
| Serviço de Contas
|--------------------------------------------------------------------------
| Esta classe concentra consultas e cálculos relacionados à página de contas.
| O objetivo é manter a camada de apresentação limpa e centralizar a lógica
| de negócio em um único ponto.
*/
class ContasService
{
    /**
     * Retorna todos os dados necessários para montagem da página de contas.
     *
     * @param PDO $pdo
     * @param int $userId
     * @return array
     */
    public static function getDadosPaginaContas(PDO $pdo, int $userId): array
    {
        /*
        |--------------------------------------------------------------------------
        | Estrutura padrão de retorno
        |--------------------------------------------------------------------------
        | Garante que a página sempre receba a mesma estrutura de dados,
        | mesmo quando não houver registros no banco.
        */
        $dados = [
            'saldoConsolidado' => 0.0,
            'contasAtivas' => 0,
            'cartoesVinculados' => 0,
            'ultimaSincronizacao' => 'Nunca',
            'listaContas' => [],
            'painelLateral' => [
                'contaPrincipal' => null,
                'resumoContas' => [],
                'metaSaldo' => [
                    'valor_meta' => 15000,
                    'nome' => 'Objetivo mensal',
                    'pct' => 0,
                    'falta' => 15000
                ],
            ],
            'limiteCreditoUsado' => 0,
            'saldoProjetadoStatus' => 'Neutro'
        ];

        /*
        |--------------------------------------------------------------------------
        | 1. Saldo consolidado, contas ativas e última sincronização
        |--------------------------------------------------------------------------
        | Consulta os principais indicadores financeiros das contas do usuário.
        */
        $stmt = $pdo->prepare(
            'SELECT SUM(saldo) as saldo_total, COUNT(id) as qtd_ativas, MAX(ultima_sincronizacao_em) as ultima_sync
             FROM contas
             WHERE id_usuario = ? AND ativa = 1'
        );
        $stmt->execute([$userId]);

        if ($resumo = $stmt->fetch()) {
            $dados['saldoConsolidado'] = (float)($resumo['saldo_total'] ?? 0);
            $dados['contasAtivas'] = (int)($resumo['qtd_ativas'] ?? 0);

            if (!empty($resumo['ultima_sync'])) {
                $dataSync = new DateTime($resumo['ultima_sync']);
                $hoje = new DateTime('today');
                $diff = $hoje->diff(new DateTime($dataSync->format('Y-m-d')))->days;

                if ($diff === 0) {
                    $dados['ultimaSincronizacao'] = 'Hoje';
                } elseif ($diff === 1) {
                    $dados['ultimaSincronizacao'] = 'Ontem';
                } else {
                    $dados['ultimaSincronizacao'] = $dataSync->format('d/m/Y');
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Cartões vinculados
        |--------------------------------------------------------------------------
        | Conta quantos cartões estão associados às contas do usuário.
        */
        $stmt = $pdo->prepare(
            'SELECT COUNT(c.id) as qtd_cartoes
             FROM cartoes c
             JOIN contas a ON c.id_conta = a.id
             WHERE a.id_usuario = ?'
        );
        $stmt->execute([$userId]);
        $dados['cartoesVinculados'] = (int)$stmt->fetchColumn();

        /*
        |--------------------------------------------------------------------------
        | 3. Lista de contas
        |--------------------------------------------------------------------------
        | Retorna todas as contas do usuário para exibição na tabela principal.
        */
        $stmt = $pdo->prepare('
            SELECT c.id, c.nome, c.saldo, c.ativa, c.principal,
                   t.categoria as tipo_categoria, t.nome as tipo_nome
            FROM contas c
            LEFT JOIN tipos_conta t ON c.id_tipo_conta = t.id
            WHERE c.id_usuario = ?
            ORDER BY c.principal DESC, c.nome ASC
        ');
        $stmt->execute([$userId]);
        $dados['listaContas'] = $stmt->fetchAll();

        /*
        |--------------------------------------------------------------------------
        | 4. Painel lateral
        |--------------------------------------------------------------------------
        | Monta a conta principal e um resumo das demais contas mais relevantes.
        */
        $painelContas = [];

        foreach ($dados['listaContas'] as $conta) {
            $pct = $dados['saldoConsolidado'] > 0
                ? round(($conta['saldo'] / $dados['saldoConsolidado']) * 100)
                : 0;

            $contaData = [
                'nome' => $conta['nome'],
                'saldo' => $conta['saldo'],
                'pct' => $pct,
                'categoria' => $conta['tipo_categoria']
            ];

            if ($conta['principal'] == 1 && !$dados['painelLateral']['contaPrincipal']) {
                $dados['painelLateral']['contaPrincipal'] = $contaData;
            } else {
                $painelContas[] = $contaData;
            }
        }

        usort($painelContas, function ($a, $b) {
            return $b['saldo'] <=> $a['saldo'];
        });

        $dados['painelLateral']['resumoContas'] = array_slice($painelContas, 0, 2);

        /*
        |--------------------------------------------------------------------------
        | 5. Meta de saldo
        |--------------------------------------------------------------------------
        | Busca a meta ativa de maior prioridade e calcula progresso.
        */
        $stmt = $pdo->prepare(
            'SELECT nome, valor_meta
             FROM metas_financeiras
             WHERE id_usuario = ? AND ativa = 1
             ORDER BY reserva_emergencia DESC, valor_meta DESC
             LIMIT 1'
        );
        $stmt->execute([$userId]);

        if ($meta = $stmt->fetch()) {
            $dados['painelLateral']['metaSaldo']['nome'] = $meta['nome'];
            $dados['painelLateral']['metaSaldo']['valor_meta'] = (float)$meta['valor_meta'];
        }

        $metaVal = $dados['painelLateral']['metaSaldo']['valor_meta'];
        if ($metaVal > 0) {
            $dados['painelLateral']['metaSaldo']['pct'] = min(100, round(($dados['saldoConsolidado'] / $metaVal) * 100));
            $falta = $metaVal - $dados['saldoConsolidado'];
            $dados['painelLateral']['metaSaldo']['falta'] = $falta > 0 ? $falta : 0;
        }

        /*
        |--------------------------------------------------------------------------
        | 6. Limite de crédito
        |--------------------------------------------------------------------------
        | Calcula o percentual de uso do limite total dos cartões.
        */
        $stmt = $pdo->prepare(
            'SELECT SUM(limite) as limite_total
             FROM cartoes c
             JOIN contas a ON c.id_conta = a.id
             WHERE a.id_usuario = ?'
        );
        $stmt->execute([$userId]);
        $limiteTotal = (float)$stmt->fetchColumn();

        $mesAtual = date('Y-m');

        $stmt = $pdo->prepare("
            SELECT SUM(m.valor) as gasto
            FROM movimentacoes m
            JOIN cartoes c ON m.id_conta = c.id_conta
            WHERE m.id_usuario = ?
              AND m.tipo = 'DESPESA'
              AND DATE_FORMAT(m.ocorreu_em, '%Y-%m') = ?
        ");
        $stmt->execute([$userId, $mesAtual]);
        $gastoCartoes = (float)$stmt->fetchColumn();

        if ($limiteTotal > 0) {
            $dados['limiteCreditoUsado'] = min(100, round(($gastoCartoes / $limiteTotal) * 100));
        }

        /*
        |--------------------------------------------------------------------------
        | 7. Saldo projetado
        |--------------------------------------------------------------------------
        | Estima o saldo final do mês com base em contas a receber e pagar.
        */
        $stmtReceber = $pdo->prepare("
            SELECT SUM(valor_total)
            FROM contas_receber
            WHERE id_usuario = ?
              AND status IN ('PENDENTE', 'VENCIDO')
              AND DATE_FORMAT(vence_em, '%Y-%m') = ?
        ");
        $stmtReceber->execute([$userId, $mesAtual]);
        $aReceber = (float)$stmtReceber->fetchColumn();

        $stmtPagar = $pdo->prepare("
            SELECT SUM(valor_total)
            FROM contas_pagar
            WHERE id_usuario = ?
              AND status IN ('PENDENTE', 'VENCIDO')
              AND DATE_FORMAT(vence_em, '%Y-%m') = ?
        ");
        $stmtPagar->execute([$userId, $mesAtual]);
        $aPagar = (float)$stmtPagar->fetchColumn();

        $projetado = $dados['saldoConsolidado'] + $aReceber - $aPagar;

        if ($projetado > 0) {
            $dados['saldoProjetadoStatus'] = 'Positivo';
        } elseif ($projetado < 0) {
            $dados['saldoProjetadoStatus'] = 'Negativo';
        } else {
            $dados['saldoProjetadoStatus'] = 'Zerado';
        }

        return $dados;
    }

    /**
     * Retorna os tipos de conta disponíveis no sistema.
     *
     * @param PDO $pdo
     * @return array
     */
    public static function getTiposConta(PDO $pdo): array
    {
        $stmt = $pdo->query("SELECT id, nome, categoria FROM tipos_conta ORDER BY categoria ASC, nome ASC");
        return $stmt->fetchAll();
    }
}