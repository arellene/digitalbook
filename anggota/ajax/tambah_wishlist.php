<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/anggota/notif_helper.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] === 'guest' || !is_numeric($_SESSION['user_id'] ?? '')) {
    header('Location: ../../index.php');
    exit();
}

$uid      = (int) $_SESSION['user_id'];
$bid      = (int) ($_GET['id'] ?? 0);
$redirect = trim($_GET['redirect'] ?? 'katalog_ebook.php');

if (!preg_match('/^[a-zA-Z0-9_\-\.\/\?=&%]+$/', $redirect)) {
    $redirect = 'katalog_ebook.php';
}

$dbOk  = isset($conn) && $conn instanceof mysqli;
$pesan = 'error';
$tipe  = 'error';

if ($bid > 0 && $dbOk) {
    $cekBuku = mysqli_query($conn, "SELECT judul FROM buku WHERE id = $bid LIMIT 1");
    if (!$cekBuku || mysqli_num_rows($cekBuku) === 0) {
        $pesan = 'Buku tidak ditemukan.';
    } else {
        $judulBuku = mysqli_fetch_assoc($cekBuku)['judul'];
        $cek = mysqli_query($conn, "SELECT id FROM wishlist WHERE id_anggota = $uid AND id_buku = $bid LIMIT 1");
        if ($cek && mysqli_num_rows($cek) > 0) {
            $pesan = 'already_wishlist';
            $tipe  = 'info';
        } else {
            $ins = mysqli_query($conn, "INSERT INTO wishlist (id_anggota, id_buku, created_at) VALUES ($uid, $bid, NOW())");
            if ($ins) {
                $pesan = 'success_wishlist';
                $tipe  = 'success';
                kirimNotif($conn, $uid, 'success', 'Ditambahkan ke Wishlist',
                    "Buku \"$judulBuku\" berhasil ditambahkan ke wishlist kamu.");
            } else {
                $pesan = 'Gagal menambahkan ke wishlist.';
            }
        }
    }
} else {
    $pesan = 'ID buku tidak valid.';
}

$_SESSION['flash_wishlist'] = ['pesan' => $pesan, 'tipe' => $tipe, 'buku_id' => $bid];
header("Location: ../$redirect");
exit();