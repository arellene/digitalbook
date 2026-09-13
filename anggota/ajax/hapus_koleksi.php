<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

// Validasi session
if (!isset($_SESSION['role']) || $_SESSION['role'] === 'guest') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!is_numeric($_SESSION['user_id'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid session']);
    exit();
}

$uid       = (int) $_SESSION['user_id'];
$koleksiId = (int) ($_POST['koleksi_id'] ?? 0);

if ($koleksiId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
    exit();
}

// Pastikan koleksi milik user yang sedang login (bukan milik orang lain)
$check = mysqli_query($conn, "SELECT id FROM koleksi WHERE id = $koleksiId AND id_anggota = $uid LIMIT 1");
if (!$check || mysqli_num_rows($check) === 0) {
    echo json_encode(['success' => false, 'message' => 'Koleksi tidak ditemukan']);
    exit();
}

// Hapus dari tabel koleksi
$del = mysqli_query($conn, "DELETE FROM koleksi WHERE id = $koleksiId AND id_anggota = $uid");

if ($del) {
    echo json_encode(['success' => true, 'message' => 'Berhasil dihapus']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus: ' . mysqli_error($conn)]);
}