<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Life Finance | Login</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-layout">
    <div class="login-card">
        <header class="login-header">
            <img src="<?php echo BASE_URL; ?>/assets/images/logoSemFundo.png" alt="Logo Life Finance" class="login-header__logo" />
            <h1>Life Finance</h1>
            <p>Controle suas finanças pessoais com clareza e segurança.</p>
        </header>

        <main class="login-body">
            <form method="POST" action="<?php echo BASE_URL; ?>/auth/login">

                <?php if (isset($_GET['registered']) && $_GET['registered'] == 1): ?>
                    <div class="alert alert-success">
                        <div><i class="fas fa-check-circle"></i> Cadastro realizado com sucesso! Faça login abaixo.</div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <div><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="email">E‑mail</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="seu@email.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="password">Senha</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Sua senha" required>
                </div>

                <div class="form-row">
                    <a href="<?php echo BASE_URL; ?>/auth/recover" class="form-row-link">
                        <i class="fas fa-key"></i> Esqueceu a senha?
                    </a>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Entrar
                    </button>
                </div>

                <div class="login-footer">
                    <p class="login-footer-text">
                        Não possui conta? <a href="<?php echo BASE_URL; ?>/auth/register" class="form-row-link">Crie sua conta agora</a>.
                    </p>
                </div>

            </form>
        </main>
    </div>
</body>
</html>
