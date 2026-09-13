<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] === 'guest' || !is_numeric($_SESSION['user_id'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$uid = (int) $_SESSION['user_id'];
$koleksi_id = (int) ($_POST['koleksi_id'] ?? 0);
$status = $_POST['status'] ?? '';
$allowed = ['belum_dibaca', 'sedang_dibaca', 'selesai'];

if ($koleksi_id <= 0 || !in_array($status, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
    exit();
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo json_encode(['success' => false, 'message' => 'Koneksi DB gagal']);
    exit();
}

$stmt = $conn->prepare("UPDATE koleksi SET status_baca = ? WHERE id = ? AND id_anggota = ?");
$stmt->bind_param('sii', $status, $koleksi_id, $uid);
$ok = $stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

if ($ok && $affected > 0) {
    echo json_encode(['success' => true, 'status' => $status]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal mengupdate status']);
}