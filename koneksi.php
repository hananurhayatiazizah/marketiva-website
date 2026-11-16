<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Otomatis membaca konfigurasi dari Railway atau fallback ke localhost jika dibuka di laptop
$host = 'localhost';
$user = 'root';
$pass =  '';
$db   =  'db_marketiva';
$port = '3306';

$conn = mysqli_connect($host, $user, $pass, $db, $port);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>