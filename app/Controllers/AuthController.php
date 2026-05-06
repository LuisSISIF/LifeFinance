<?php

class AuthController extends Controller
{
    public function login()
    {
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($email)) {
                $errors[] = 'Informe seu e-mail.';
            }

            if (empty($password)) {
                $errors[] = 'Informe sua senha.';
            }

            if (empty($errors)) {
                $usuarioModel = new Usuario();
                $user = $usuarioModel->findByEmail($email);

                if ($user && password_verify($password, $user['senha_hash'])) {
                    if ($user['status'] !== 'ATIVO') {
                        $errors[] = 'Sua conta não está ativa.';
                    } else {
                        session_regenerate_id(true);

                        $_SESSION['authenticated'] = true;
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['email'] = $email;
                        $_SESSION['role'] = $user['role'] ?? 'USER';

                        if ($_SESSION['role'] === 'ADMIN') {
                            $this->redirect('/admin_usuarios');
                        } else {
                            $this->redirect('/dashboard');
                        }
                    }
                } else {
                    $errors[] = 'E-mail ou senha incorretos.';
                }
            }
        }

        $this->view('auth/login', ['errors' => $errors]);
    }

    public function logout()
    {
        session_unset();
        session_destroy();
        $this->redirect('/auth/login');
    }

    public function register()
    {
        // Falta implementar se houver tempo
        $this->view('auth/register');
    }
}
