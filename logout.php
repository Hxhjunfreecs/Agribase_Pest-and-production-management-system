<?php

require 'includes/config.php';

session_unset();
if (ini_get('session.use_cookies')) {
	$cookie = session_get_cookie_params();
	setcookie(session_name(), '', [
		'expires' => time() - 42000,
		'path' => $cookie['path'],
		'domain' => $cookie['domain'],
		'secure' => $cookie['secure'],
		'httponly' => $cookie['httponly'],
		'samesite' => $cookie['samesite'] ?? 'Lax'
	]);
}
session_destroy();

header('Location: index.php');
exit;
