<?php
require_once __DIR__ . '/Conexao.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $sobrenome = trim($_POST['sobrenome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $senha_confirmar = $_POST['senha_confirmar'] ?? '';
    $data_nascimento = $_POST['data_nascimento'] ?? null;
    $sexo = $_POST['sexo'] ?? null;

    if ($nome === '') $errors[] = 'Informe seu nome.';
    if ($sobrenome === '') $errors[] = 'Informe seu sobrenome.';
    if ($email === '') $errors[] = 'Informe seu e-mail.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail inválido.';
    if ($senha === '') $errors[] = 'Informe sua senha.';
    elseif (strlen($senha) < 8) $errors[] = 'A senha deve ter no mínimo 8 caracteres.';
    if ($senha !== $senha_confirmar) $errors[] = 'As senhas não conferem.';
    if ($sexo === '' || !in_array($sexo, ['M','F','O'], true)) $errors[] = 'Selecione um sexo válido.';

    if ($data_nascimento !== '') {
        $dt = DateTime::createFromFormat('Y-m-d', $data_nascimento);
        if (!$dt || $dt->format('Y-m-d') !== $data_nascimento) $errors[] = 'Data de nascimento inválida.';
    } else {
        $data_nascimento = null;
    }

    try {
        $pdo = Conexao::getInstancia();

        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $errors[] = 'Este e-mail já está cadastrado.';
        }

        if (!$errors) {
            $pdo->beginTransaction();

            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare('INSERT INTO usuarios (email, senha_hash, email_verificado_em, forcar_2fa, status, criado_em, atualizado_em) VALUES (:email, :senha_hash, NULL, 0, \'ATIVO\', NOW(), NOW())');
            $stmt->execute([
                ':email' => $email,
                ':senha_hash' => $senhaHash,
            ]);

            $idUsuario = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare('INSERT INTO perfis_usuarios (id_usuario, nome, sobrenome, data_nascimento, sexo, idioma, moeda_exibicao, criado_em, atualizado_em) VALUES (:id_usuario, :nome, :sobrenome, :data_nascimento, :sexo, :idioma, :moeda_exibicao, NOW(), NOW())');
            $stmt->execute([
                ':id_usuario' => $idUsuario,
                ':nome' => $nome,
                ':sobrenome' => $sobrenome,
                ':data_nascimento' => $data_nascimento,
                ':sexo' => $sexo,
                ':idioma' => 'pt-BR',
                ':moeda_exibicao' => 'BRL',
            ]);

            $pdo->commit();
            header('Location: login.php?registered=1');
            exit;
        }
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        $errors[] = 'Erro ao cadastrar: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Life Finance | Cadastro</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="assets/js/password-toggle.js" defer></script>
    <style>
        .register-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
        .register-grid .full{grid-column:1 / -1;}
        .hint{font-size:12px;color:#6b7280;margin-top:6px;}
        @media (max-width: 640px){.register-grid{grid-template-columns:1fr;}.register-grid .full{grid-column:auto;}}
    </style>
</head>
<body class="login-layout">
    <div class="login-card">
        <header class="login-header">
            <img src="assets/images/logoSemFundo.png" alt="Logo Life Finance" class="login-header__logo" />
            <h1>Life Finance</h1>
            <p>Crie sua conta e comece a organizar suas finanças.</p>
        </header>
        <main class="login-body">
            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> Cadastro realizado com sucesso! Agora você pode fazer login.</div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <div><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php" autocomplete="off">
                <div class="register-grid">
                    <div class="form-group">
                        <label for="nome">Nome</label>
                        <input type="text" id="nome" name="nome" class="form-control" value="<?php echo htmlspecialchars($_POST['nome'] ?? ''); ?>" placeholder="Seu nome">
                    </div>
                    <div class="form-group">
                        <label for="sobrenome">Sobrenome</label>
                        <input type="text" id="sobrenome" name="sobrenome" class="form-control" value="<?php echo htmlspecialchars($_POST['sobrenome'] ?? ''); ?>" placeholder="Seu sobrenome">
                    </div>
                    <div class="form-group full">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder="seu@email.com">
                    </div>
                    <div class="form-group">
                        <label for="senha">Senha</label>
                        <div class="password-input">
                            <input type="password" id="senha" name="senha" class="form-control" placeholder="••••••••">
                            <button type="button" class="password-toggle" onclick="togglePassword('senha')"><i class="fas fa-eye"></i></button>
                        </div>
                        <div class="hint">Use no mínimo 8 caracteres.</div>
                    </div>
                    <div class="form-group">
                        <label for="senha_confirmar">Confirmar senha</label>
                        <div class="password-input">
                            <input type="password" id="senha_confirmar" name="senha_confirmar" class="form-control" placeholder="••••••••">
                            <button type="button" class="password-toggle" onclick="togglePassword('senha_confirmar')"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="data_nascimento">Data de nascimento</label>
                        <input type="date" id="data_nascimento" name="data_nascimento" class="form-control" value="<?php echo htmlspecialchars($_POST['data_nascimento'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="sexo">Sexo</label>
                        <select id="sexo" name="sexo" class="form-control">
                            <option value="">Selecione</option>
                            <option value="M" <?php echo (($_POST['sexo'] ?? '') === 'M') ? 'selected' : ''; ?>>Masculino</option>
                            <option value="F" <?php echo (($_POST['sexo'] ?? '') === 'F') ? 'selected' : ''; ?>>Feminino</option>
                            <option value="O" <?php echo (($_POST['sexo'] ?? '') === 'O') ? 'selected' : ''; ?>>Outro</option>
                        </select>
                    </div>
                </div>

                <div class="form-actions" style="margin-top:18px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Criar conta</button>
                </div>

                <div class="login-footer">
                    <p class="login-footer-text">Já possui conta? <a href="login.php" class="form-row-link">Faça login</a>.</p>
                </div>
            </form>
        </main>
    </div>
</body>
</html>