<?php
$host     = 'localhost';
$dbname   = 'perpustakaan';   // sesuaikan nama database kamu
$username = 'root';
$password = '';               // default Laragon: kosong

$conn = mysqli_connect($host, $username, $password, $dbname);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}