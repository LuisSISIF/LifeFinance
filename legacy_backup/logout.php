<?php
/*
|--------------------------------------------------------------------------
| Logout do usuário
|--------------------------------------------------------------------------
| Este arquivo encerra completamente a sessão atual e redireciona
| o usuário para a página de login.
*/
session_start();

/*
|--------------------------------------------------------------------------
| Limpeza da sessão
|--------------------------------------------------------------------------
| Remove os dados armazenados em memória e destrói a sessão no servidor.
*/
session_unset();
session_destroy();

/*
|--------------------------------------------------------------------------
| Redirecionamento
|--------------------------------------------------------------------------
| Após o logout, o usuário é enviado de volta à tela de autenticação.
*/
header('Location: login.php');
exit;
?>