<?php
require_once 'custom_mvc/app/Core/Database.php';

try {
    $db = Database::getInstancia();
    
    // Find all foreign key constraints pointing to `contas`
    $query = "
        SELECT 
            TABLE_NAME, 
            COLUMN_NAME, 
            CONSTRAINT_NAME, 
            REFERENCED_TABLE_NAME, 
            REFERENCED_COLUMN_NAME 
        FROM 
            INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE 
            REFERENCED_TABLE_NAME = 'contas' 
            AND TABLE_SCHEMA = 'lifeFinance'
    ";
    
    $stmt = $db->query($query);
    $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "=== RESTRIÇÕES DE CHAVE ESTRANGEIRA (FK) PARA A TABELA 'contas' ===\n";
    foreach ($constraints as $c) {
        echo "Tabela Origem: {$c['TABLE_NAME']} | Coluna Origem: {$c['COLUMN_NAME']} | FK Name: {$c['CONSTRAINT_NAME']}\n";
    }
    echo "\n";
    
    // Let's check if there are dependents for a sample account
    // Let's get the accounts list
    $stmt = $db->query("SELECT id, nome, id_usuario FROM contas");
    $contas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "=== DEPENDÊNCIAS POR CONTA ===\n";
    foreach ($contas as $conta) {
        echo "Conta: [ID {$conta['id']}] {$conta['nome']} (User: {$conta['id_usuario']})\n";
        
        // Count in movimentacoes (id_conta or id_conta_destino)
        $sMovOrigem = $db->prepare("SELECT COUNT(*) FROM movimentacoes WHERE id_conta = ?");
        $sMovOrigem->execute([$conta['id']]);
        $movOrigem = $sMovOrigem->fetchColumn();
        
        $sMovDest = $db->prepare("SELECT COUNT(*) FROM movimentacoes WHERE id_conta_destino = ?");
        $sMovDest->execute([$conta['id']]);
        $movDest = $sMovDest->fetchColumn();
        
        // Count in cartoes
        $sCartoes = $db->prepare("SELECT COUNT(*) FROM cartoes WHERE id_conta = ?");
        $sCartoes->execute([$conta['id']]);
        $cartoes = $sCartoes->fetchColumn();
        
        // Count in contas_pagar
        $sPagar = $db->prepare("SELECT COUNT(*) FROM contas_pagar WHERE id_conta = ?");
        $sPagar->execute([$conta['id']]);
        $pagar = $sPagar->fetchColumn();
        
        // Count in contas_receber
        $sReceber = $db->prepare("SELECT COUNT(*) FROM contas_receber WHERE id_conta = ?");
        $sReceber->execute([$conta['id']]);
        $receber = $sReceber->fetchColumn();
        
        echo "  - Movimentações (como Origem): {$movOrigem}\n";
        echo "  - Movimentações (como Destino): {$movDest}\n";
        echo "  - Cartões Vinculados: {$cartoes}\n";
        echo "  - Contas a Pagar: {$pagar}\n";
        echo "  - Contas a Receber: {$receber}\n";
    }
} catch (Throwable $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
