<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] === 'guest' || !is_numeric($_SESSION['user_id'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$uid = (int) $_SESSION['user_id'];
$riwayat_id = (int) ($_POST['riwayat_id'] ?? 0);

if ($riwayat_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
    exit();
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo json_encode(['success' => false, 'message' => 'Koneksi DB gagal']);
    exit();
}

// Pastikan riwayat milik user yang sedang login (keamanan)
$stmt = $conn->prepare("DELETE FROM riwayat_baca WHERE id = ? AND id_anggota = ?");
$stmt->bind_param('ii', $riwayat_id, $uid);
$ok = $stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

if ($ok && $affected > 0) {
    echo json_encode(['success' => true, 'message' => 'Riwayat berhasil dihapus']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus atau data tidak ditemukan']);
}