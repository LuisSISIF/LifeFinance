<?php

class Router
{
    protected $controller = 'DashboardController';
    protected $method = 'index';
    protected $params = [];

    public function dispatch($url)
    {
        $url = $this->parseUrl($url);

        // Verifica se o controller existe
        if (!empty($url[0])) {
            $controllerName = ucfirst($url[0]) . 'Controller';
            if (file_exists('../app/Controllers/' . $controllerName . '.php')) {
                $this->controller = $controllerName;
            }
            unset($url[0]);
        }

        require_once '../app/Controllers/' . $this->controller . '.php';
        $this->controller = new $this->controller;

        // Verifica se o método existe
        if (isset($url[1])) {
            if (method_exists($this->controller, $url[1])) {
                $this->method = $url[1];
            }
            unset($url[1]);
        }

        // Parâmetros restantes
        $this->params = $url ? array_values($url) : [];

        // Chama o método no controller
        call_user_func_array([$this->controller, $this->method], $this->params);
    }

    protected function parseUrl($url)
    {
        if ($url) {
            return explode('/', filter_var(rtrim($url, '/'), FILTER_SANITIZE_URL));
        }
        return [];
    }
}
