<?php

class Database
{
    private static ?PDO $instancia = null;

    private function __construct() {}

    public static function getInstancia(): PDO
    {
        if (self::$instancia === null) {
            $host = 'srv601924.hstgr.cloud';
            $banco = 'lifeFinance';
            $usuario = 'usuario';
            $senha = 'Rik3201596,';
            $porta = 3306;
            $charset = 'utf8mb4';

            $dsn = "mysql:host={$host};port={$porta};dbname={$banco};charset={$charset}";

            $opcoes = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            self::$instancia = new PDO($dsn, $usuario, $senha, $opcoes);
        }

        return self::$instancia;
    }
}
