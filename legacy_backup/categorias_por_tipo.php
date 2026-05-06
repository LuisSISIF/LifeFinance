<?php
session_start();

/*
|--------------------------------------------------------------------------
| Proteção de acesso
|--------------------------------------------------------------------------
| O arquivo só responde para usuários autenticados.
| Como ele normalmente é usado via requisição assíncrona, o retorno é vazio
| em caso de falha, evitando exposição de mensagens sensíveis.
*/
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    exit;
}

require_once __DIR__ . '/Conexao.php';

try {
    /*
    |--------------------------------------------------------------------------
    | Instância de banco e validação do usuário
    |--------------------------------------------------------------------------
    | Obtém a conexão PDO e confirma se o usuário logado é válido.
    */
    $pdo = Conexao::getInstancia();
    $uid = (int)($_SESSION['user_id'] ?? 0);

    if ($uid <= 0) {
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Tipo solicitado
    |--------------------------------------------------------------------------
    | O parâmetro GET define quais categorias devem ser carregadas.
    */
    $tipo = strtoupper(trim($_GET['tipo'] ?? ''));

    /*
    |--------------------------------------------------------------------------
    | Mapeamento permitido
    |--------------------------------------------------------------------------
    | Garante que apenas valores válidos sejam aceitos.
    */
    $map = [
        'RECEITA' => ['RECEITA'],
        'DESPESA' => ['DESPESA'],
        'AJUSTE'  => ['AJUSTE'],
    ];

    if (!isset($map[$tipo])) {
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Montagem da consulta
    |--------------------------------------------------------------------------
    | Usa placeholders posicionais para manter a query segura e flexível.
    */
    $tipos = $map[$tipo];
    $placeholders = implode(',', array_fill(0, count($tipos), '?'));

    $sql = "
        SELECT id, nome, tipo
        FROM categorias
        WHERE id_usuario = ?
          AND tipo IN ($placeholders)
        ORDER BY nome ASC
    ";

    $stmt = $pdo->prepare($sql);
    $params = array_merge([$uid], $tipos);
    $stmt->execute($params);

    /*
    |--------------------------------------------------------------------------
    | Geração das options
    |--------------------------------------------------------------------------
    | Retorna apenas as opções de categorias para preenchimento de selects.
    */
    $cats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cats as $c) {
        echo '<option value="' . (int)$c['id'] . '">' . htmlspecialchars($c['nome']) . '</option>';
    }
} catch (Throwable $e) {
    exit;
}
?>