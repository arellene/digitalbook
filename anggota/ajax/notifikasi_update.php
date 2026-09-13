<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] === 'guest' || !is_numeric($_SESSION['user_id'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$uid  = (int) $_SESSION['user_id'];
$dbOk = isset($conn) && $conn instanceof mysqli;

if (!$dbOk) {
    echo json_encode(['success' => false, 'message' => 'Koneksi DB gagal']);
    exit();
}

$body   = json_decode(file_get_contents('php://input'), true);
$action = $body['action'] ?? '';
$id     = (int) ($body['id'] ?? 0);

switch ($action) {
    case 'read':
        $ok = mysqli_query($conn, "UPDATE notifikasi SET is_read = 1 WHERE id = $id AND user_id = $uid");
        echo json_encode(['success' => (bool) $ok]);
        break;

    case 'read_all':
        $ok = mysqli_query($conn, "UPDATE notifikasi SET is_read = 1 WHERE user_id = $uid");
        echo json_encode(['success' => (bool) $ok]);
        break;

    case 'delete':
        $ok = mysqli_query($conn, "DELETE FROM notifikasi WHERE id = $id AND user_id = $uid");
        echo json_encode(['success' => (bool) $ok]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal']);
        break;
}