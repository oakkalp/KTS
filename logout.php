<?php
/**
 * Kurye Full System - Çıkış
 * Kullanıcı oturumunu sonlandırır
 */

require_once 'config/config.php';

// Çıkış yapmadan önce log kaydet
if (isLoggedIn()) {
    $username = $_SESSION['username'] ?? 'unknown';
    $user_type = $_SESSION['user_type'] ?? 'unknown';
    writeLog("User logout: {$username} ({$user_type})", 'INFO', 'auth.log');
}

// Session'ı temizle
session_destroy();

// Remember me cookie'sini sil
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Login sayfasına yönlendir
header('Location: login.php?message=logged_out');
exit;
?>
