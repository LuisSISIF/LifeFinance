<?php
session_start();

// Autoloader simples
spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../app/Core/',
        __DIR__ . '/../app/Controllers/',
        __DIR__ . '/../app/Models/',
    ];

    foreach ($paths as $path) {
        $file = $path . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

require_once __DIR__ . '/../config/config.php';

// Inicia o Router
$router = new Router();
$router->dispatch($_GET['url'] ?? '');
