<?php
/*
|--------------------------------------------------------------------------
| Página: Esqueci minha senha
|--------------------------------------------------------------------------
| Objetivo:
| - Receber o e-mail do usuário.
| - Verificar se existe uma conta ativa com esse e-mail.
| - Gerar um token temporário de recuperação.
| - Salvar o token no banco de forma segura.
| - Preparar o link que será enviado por e-mail.
*/

require_once __DIR__ . '/Conexao.php';

// Variáveis usadas para mostrar mensagens na tela
$mensagem = '';
$erro = '';

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Limpa espaços em branco e pega o valor digitado no campo e-mail
    $email = trim($_POST['email'] ?? '');

    // Valida se o e-mail informado tem formato válido
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } else {
        try {
            // Abre a conexão com o banco usando a classe centralizada
            $pdo = Conexao::getInstancia();

            // Procura um usuário ativo com o e-mail informado
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email AND status = 'ATIVO' LIMIT 1");
            $stmt->execute([':email' => $email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            /*
            |--------------------------------------------------------------
            | Se o usuário existir, criamos um token de recuperação
            |--------------------------------------------------------------
            | Importante:
            | - O token puro não deve ser salvo no banco.
            | - Salvamos apenas o hash dele.
            | - Assim, mesmo que alguém veja o banco, não terá o token real.
            */
            if ($usuario) {
                // Gera um token aleatório forte e imprevisível
                $token = bin2hex(random_bytes(32));

                // Gera o hash do token para salvar no banco
                $tokenHash = hash('sha256', $token);

                // Define quando esse token vai expirar
                $expiraEm = date('Y-m-d H:i:s', strtotime('+30 minutes'));

                /*
                |--------------------------------------------------------------
                | Invalida tokens antigos desse mesmo usuário
                |--------------------------------------------------------------
                | Isso evita que vários links fiquem válidos ao mesmo tempo.
                */
                $pdo->prepare("UPDATE password_resets SET usado = 1 WHERE user_id = :user_id AND usado = 0")
                    ->execute([':user_id' => $usuario['id']]);

                // Salva o novo token na tabela de recuperação
                $stmt = $pdo->prepare("\
                    INSERT INTO password_resets (user_id, token_hash, expira_em, usado, criado_em)\
                    VALUES (:user_id, :token_hash, :expira_em, 0, NOW())\
                ");
                $stmt->execute([
                    ':user_id' => $usuario['id'],
                    ':token_hash' => $tokenHash,
                    ':expira_em' => $expiraEm
                ]);

                /*
                |--------------------------------------------------------------
                | Link de redefinição
                |--------------------------------------------------------------
                | Aqui você troca o domínio pelo endereço real do projeto.
                | Esse link será enviado por e-mail para o usuário.
                */
                $link = 'https://seu-dominio.com/redefinir_senha.php?token=' . $token;

                // Mensagem genérica para não expor se o e-mail existe ou não
                $mensagem = 'Se o e-mail existir na base, você receberá um link para redefinir a senha.';

                // Aqui entra o envio de e-mail via PHPMailer ou outro serviço
                // Exemplo: enviarEmailRecuperacao($email, $link);
            } else {
                // Mesmo retorno para manter a segurança contra enumeração de contas
                $mensagem = 'Se o e-mail existir na base, você receberá um link para redefinir a senha.';
            }
        } catch (Throwable $e) {
            // Em produção, o ideal é registrar o erro em log e não exibir detalhes técnicos
            $erro = 'Ocorreu um erro ao processar sua solicitação.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Esqueci minha senha</title>
    <style>
        /* Estilo simples e limpo para a tela */
        body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;color:#1f2937}
        .card{background:#fff;width:100%;max-width:420px;padding:32px;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.08)}
        h2{margin:0 0 8px}
        p{line-height:1.5}
        label{display:block;margin:18px 0 8px;font-weight:600}
        input{width:100%;padding:12px 14px;border:1px solid #d1d5db;border-radius:10px;box-sizing:border-box;font-size:15px}
        button{width:100%;margin-top:18px;padding:12px 14px;border:0;border-radius:10px;background:#2563eb;color:#fff;font-weight:700;cursor:pointer}
        .msg{background:#ecfdf5;color:#065f46;padding:12px 14px;border-radius:10px;margin:16px 0 0}
        .err{background:#fef2f2;color:#991b1b;padding:12px 14px;border-radius:10px;margin:16px 0 0}
        a{color:#2563eb;text-decoration:none}
    </style>
</head>
<body>
    <div class="card">
        <h2>Esqueci minha senha</h2>
        <p>Digite seu e-mail cadastrado para receber um link de redefinição.</p>

        <?php if ($mensagem): ?>
            <div class="msg"><?= htmlspecialchars($mensagem) ?></div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="err"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <label for="email">E-mail</label>
            <input type="email" name="email" id="email" required>
            <button type="submit">Enviar link de recuperação</button>
        </form>

        <p style="margin-top:16px;"><a href="login.php">Voltar para o login</a></p>
    </div>
</body>
</html>