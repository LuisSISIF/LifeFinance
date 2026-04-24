<?php

/*
|--------------------------------------------------------------------------
| Classe de conexão com o banco de dados
|--------------------------------------------------------------------------
| Responsável por centralizar a criação e reutilização da conexão PDO.
| O padrão Singleton evita múltiplas conexões desnecessárias durante a execução.
*/
class Conexao
{
    /**
     * Instância única da conexão PDO.
     */
    private static ?PDO $instancia = null;

    /**
     * Construtor privado para impedir criação externa da classe.
     */
    private function __construct() {}

    /**
     * Retorna a instância única de conexão com o banco de dados.
     *
     * @return PDO
     */
    public static function getInstancia(): PDO
    {
        if (self::$instancia === null) {
            /*
            |--------------------------------------------------------------------------
            | Configurações do banco
            |--------------------------------------------------------------------------
            | Idealmente, estes dados devem vir de variáveis de ambiente
            | em um projeto público no GitHub.
            */
            $host = 'srv601924.hstgr.cloud';
            $banco = 'lifeFinance';
            $usuario = 'usuario';
            $senha = 'Rik3201596,';
            $porta = 3306;
            $charset = 'utf8mb4';

            /*
            |--------------------------------------------------------------------------
            | DSN de conexão
            |--------------------------------------------------------------------------
            | Define host, porta, banco e charset.
            */
            $dsn = "mysql:host={$host};port={$porta};dbname={$banco};charset={$charset}";

            /*
            |--------------------------------------------------------------------------
            | Opções do PDO
            |--------------------------------------------------------------------------
            | - ERRMODE_EXCEPTION: lança exceções em caso de erro.
            | - DEFAULT_FETCH_MODE: retorna resultados como array associativo.
            | - EMULATE_PREPARES: desativado para maior segurança e precisão.
            */
            $opcoes = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            /*
            |--------------------------------------------------------------------------
            | Criação da conexão
            |--------------------------------------------------------------------------
            | A conexão é criada apenas uma vez e reutilizada nas próximas chamadas.
            */
            self::$instancia = new PDO($dsn, $usuario, $senha, $opcoes);
        }

        return self::$instancia;
    }
}