<?php
session_start();

// セッション情報を破棄
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

clearUserCookie();

session_destroy();

header('Location: index.html');
exit;

function clearUserCookie(): void
{
    setcookie('user_id', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'samesite' => 'Lax'
    ]);
}
