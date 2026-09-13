<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

// Tangkap fatal error & pastikan selalu output JSON
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        while (ob_get_level()) ob_end_clean();
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $err['message']]);
    }
});

session_start();
require_once '../config/database.php';

while (ob_get_level() > 0) ob_end_clean(); // buang semua output sebelum JSON
ob_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$uid        = (int) $_SESSION['user_id'];
$riwayat_id = (int) ($_POST['riwayat_id'] ?? 0);
$status     = $_POST['status'] ?? '';
$progress   = max(0, min(100, (int) ($_POST['progress'] ?? 0)));

$allowed_status = ['sedang_dibaca', 'selesai', 'tidak_aktif'];
if ($riwayat_id <= 0 || !in_array($status, $allowed_status)) {
    echo json_encode(['success' => false, 'message' => "Data tidak valid: id=$riwayat_id status=$status"]);
    exit();
}

// Pastikan riwayat milik user ini
$cek = mysqli_prepare($conn, "SELECT id FROM riwayat_baca WHERE id = ? AND id_anggota = ? LIMIT 1");
mysqli_stmt_bind_param($cek, "ii", $riwayat_id, $uid);
mysqli_stmt_execute($cek);
$res = mysqli_stmt_get_result($cek);
if (!mysqli_fetch_assoc($res)) {
    echo json_encode(['success' => false, 'message' => "Riwayat id=$riwayat_id tidak ditemukan untuk user $uid"]);
    exit();
}
mysqli_stmt_close($cek);

// Cek & tambah kolom progress jika belum ada
$kolom_ada = false;
$desc = mysqli_query($conn, "DESCRIBE riwayat_baca");
while ($col = mysqli_fetch_assoc($desc)) {
    if ($col['Field'] === 'progress') { $kolom_ada = true; break; }
}
if (!$kolom_ada) {
    mysqli_query($conn, "ALTER TABLE riwayat_baca ADD COLUMN progress TINYINT UNSIGNED NOT NULL DEFAULT 0");
}

// Update status + progress + tanggal_kembali
$stmt = mysqli_prepare($conn,
    "UPDATE riwayat_baca SET status = ?, progress = ?, tanggal_kembali = NOW() WHERE id = ? AND id_anggota = ?"
);
mysqli_stmt_bind_param($stmt, "siii", $status, $progress, $riwayat_id, $uid);
$ok = mysqli_stmt_execute($stmt);
$err = mysqli_stmt_error($stmt);
mysqli_stmt_close($stmt);

// Notifikasi selesai
if ($ok && $status === 'selesai') {
    $qb = mysqli_query($conn,
        "SELECT b.judul FROM riwayat_baca r JOIN buku b ON r.id_buku = b.id WHERE r.id = $riwayat_id LIMIT 1"
    );
    $bRow = $qb ? mysqli_fetch_assoc($qb) : null;
    if ($bRow && file_exists(__DIR__ . '/../includes/anggota/notif_helper.php')) {
        require_once __DIR__ . '/../includes/anggota/notif_helper.php';
        kirimNotif($conn, $uid, 'success', 'Buku Selesai Dibaca',
            "Selamat! Kamu telah menyelesaikan \"{$bRow['judul']}\". 🎉");
    }
}

echo json_encode([
    'success' => $ok,
    'message' => $ok ? 'Berhasil disimpan' : "Gagal: $err",
]);