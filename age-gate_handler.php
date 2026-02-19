<?php
// ==================================================
// AGE GATE HANDLER – Stocke la validation via cookie
// ==================================================
$cookieName = 'age_verified';
$cookieDuration = 365 * 24 * 3600; // 1 an

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_age'])) {

// Cookie sécurisé si HTTPS
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

// Définir le domaine principal pour sous-domaines
$domain = '.diechampagnerkoenige.com';

setcookie(
$cookieName,
'1',
[
'expires' => time() + $cookieDuration,
'path' => '/',
'domain' => $domain,
'secure' => $secure,
'httponly' => true,
'samesite' => 'Lax'
]
);

// Redirection pour recharger la page
header('Location: ' . $_SERVER['REQUEST_URI']);
exit;
}