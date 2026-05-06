<?php

class Controller
{
    // Método para carregar a view e passar dados
    public function view($view, $data = [])
    {
        // Extrai as variáveis para ficarem disponíveis na view
        extract($data);

        $viewFile = '../app/Views/' . $view . '.php';

        if (file_exists($viewFile)) {
            require_once $viewFile;
        } else {
            die("View não encontrada: " . $viewFile);
        }
    }

    // Método para redirecionar de forma mais fácil
    public function redirect($url)
    {
        header('Location: ' . BASE_URL . '/' . ltrim($url, '/'));
        exit;
    }
}
