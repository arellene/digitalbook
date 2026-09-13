<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] === 'guest') {
    header('Location: ../index.php');
    exit();
}

if (!is_numeric($_SESSION['user_id'] ?? '')) {
    header('Location: ../index.php');
    exit();
}

$isGuest = false;
$active_menu = 'katalog';

function icon($name, $size = 16, $style = '') {
    $s = $style ? " style=\"$style\"" : '';
    $icons = [
        'arrow-left'   => '<path d="M14 7l-5 5 5 5V7z"/>',
        'book-open'    => '<path d="M21 4H3a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h18a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1zM3 6h8v12H3V6zm10 12V6h8v12h-8z"/>',
        'eye'          => '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>',
        'pencil'       => '<path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>',
        'download'     => '<path d="M5 20h14v-2H5v2zm7-14l5 5h-3v4h-4v-4H7l5-5z"/>',
        'info'         => '<path d="M11 9h2V7h-2v2zm0 10h2v-6h-2v6zm1-18C6.48 1 2 5.48 2 11s4.48 10 10 10 10-4.48 10-10S17.52 1 12 1z"/>',
        'house'        => '<path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>',
        'book'         => '<path d="M18 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zM6 4h5v8l-2.5-1.5L6 12V4zm0 16V14l2.5-1.5L11 14v6H6zm12 0h-5v-6l2.5 1.5L18 14v6zm0-8h-5V4h5v8z"/>',
        'tag'          => '<path d="M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58s1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41s-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z"/>',
        'tags'         => '<path d="M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58.55 0 1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41s-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7zm11.77 8.27L13 19.54l-4.27-4.27 4.27-4.27 4.27 4.27z"/>',
        'layers'       => '<path d="M11.99 18.54l-7.37-5.73L3 14.07l9 7 9-7-1.63-1.27-7.38 5.74zM12 16l7.36-5.73L21 9l-9-7-9 7 1.63 1.27L12 16z"/>',
        'history'      => '<path d="M13 3a9 9 0 0 0-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42A8.954 8.954 0 0 0 13 21a9 9 0 0 0 0-18zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/>',
        'star'         => '<path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>',
        'star-outline' => '<path d="M22 9.24l-7.19-.62L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21 12 17.27 18.18 21l-1.63-7.03L22 9.24zM12 15.4l-3.76 2.27 1-4.28-3.32-2.88 4.38-.38L12 6.1l1.71 4.04 4.38.38-3.32 2.88 1 4.28L12 15.4z"/>',
        'lock'         => '<path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>',
        'lock-open'    => '<path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6h2c0-1.72 1.38-3.1 3.1-3.1 1.71 0 3.1 1.38 3.1 3.1v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/>',
        'user-plus'    => '<path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>',
        'sign-in'      => '<path d="M11 7L9.6 8.4l2.6 2.6H2v2h10.2l-2.6 2.6L11 17l5-5-5-5zm9 12h-8v2h8c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-8v2h8v14z"/>',
        'sign-out'     => '<path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>',
        'user'         => '<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>',
        'check-circle' => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>',
        'bell'         => '<path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/>',
        'bars'         => '<path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>',
        'fire'         => '<path d="M13.5.67s.74 2.65.74 4.8c0 2.06-1.35 3.73-3.41 3.73-2.07 0-3.63-1.67-3.63-3.73l.03-.36C5.21 7.51 4 10.62 4 14c0 4.42 3.58 8 8 8s8-3.58 8-8C20 8.61 17.41 3.8 13.5.67zM12 20c-3.31 0-6-2.69-6-6 0-1.53.57-3.05 1.6-4.19.7 1.98 2.53 3.35 4.58 3.35 2.21 0 3.99-1.56 4.34-3.67.99 1.29 1.48 2.87 1.48 4.51 0 3.31-2.69 6-6 6z"/>',
        'arrow-right'  => '<path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6z"/>',
        'search'       => '<path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>',
        'book-reader'  => '<path d="M21 5c-1.11-.35-2.33-.5-3.5-.5-1.95 0-4.05.4-5.5 1.5-1.45-1.1-3.55-1.5-5.5-1.5S2.45 4.9 1 6v14.65c0 .25.25.5.5.5.1 0 .15-.05.25-.05C3.1 20.45 5.05 20 6.5 20c1.95 0 4.05.4 5.5 1.5 1.35-.85 3.8-1.5 5.5-1.5 1.65 0 3.35.3 4.75 1.05.1.05.15.05.25.05.25 0 .5-.25.5-.5V6c-.6-.45-1.25-.75-2-1zm0 13.5c-1.1-.35-2.3-.5-3.5-.5-1.7 0-4.15.65-5.5 1.5V8c1.35-.85 3.8-1.5 5.5-1.5 1.2 0 2.4.15 3.5.5v11.5z"/>',
        'unlock'       => '<path d="M12 1C8.676 1 6 3.676 6 7h2c0-2.206 1.794-4 4-4s4 1.794 4 4v3H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V12c0-1.1-.9-2-2-2h-2V7c0-3.324-2.676-6-6-6zm0 13c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2z"/>',
        'bookmark'     => '<path d="M17 3H7c-1.1 0-1.99.9-1.99 2L5 21l7-3 7 3V5c0-1.1-.9-2-2-2z"/>',
        'bookmark-add' => '<path d="M21 7h-2v2h-2V7h-2V5h2V3h2v2h2v2zm-4 6l-7 3-7-3V3h12v1.54c-.58-.35-1.26-.54-2-.54-2.21 0-4 1.79-4 4s1.79 4 4 4c.74 0 1.42-.19 2-.54V13z"/>',
        'filter'       => '<path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/>',
        'x'            => '<path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>',
        'check'        => '<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>',
        'catalog'      => '<path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-1 9H9V9h10v2zm-4 4H9v-2h6v2zm4-8H9V5h10v2z"/>',
        'heart'        => '<path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>',
        'trash'        => '<path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>',
        'compass'      => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-5.5-2.5l7.51-3.49L17.5 6.5 9.99 9.99 6.5 17.5zm5.5-6.6c.61 0 1.1.49 1.1 1.1s-.49 1.1-1.1 1.1-1.1-.49-1.1-1.1.49-1.1 1.1-1.1z"/>',
        'user-lock'    => '<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-1.5 0-4.5.75-5.99 2.25C7.18 17.38 9.32 18 12 18c.36 0 .71-.02 1.05-.05-.03-.3-.05-.6-.05-.95 0-1.87.85-3.54 2.18-4.67C14.08 12.11 12.63 14 12 14zm6 1c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3zm0 4.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>',
    ];
    $path = $icons[$name] ?? '<circle cx="12" cy="12" r="10"/>';
    return "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"$size\" height=\"$size\" viewBox=\"0 0 24 24\" fill=\"currentColor\"$s>$path</svg>";
}

function coverPath($cover) {
    $cover = trim($cover ?? '');
    if ($cover === '') {
        return null;
    }
    if (preg_match('#^(?:https?://|/|\.\./)#', $cover)) {
        return $cover;
    }
    return '../' . ltrim($cover, '/');
}

function pdfPath($file) {
    $file = trim($file ?? '');
    if ($file === '') {
        return null;
    }
    if (preg_match('#^(?:https?://|/|\.\./)#', $file)) {
        return $file;
    }
    return '../' . ltrim($file, '/');
}

$dbOk = isset($conn) && $conn instanceof mysqli;
$uid = (int) $_SESSION['user_id'];

$user = null;
if ($dbOk) {
    $q = mysqli_query($conn, "SELECT * FROM users WHERE id = $uid LIMIT 1");
    $user = $q ? mysqli_fetch_assoc($q) : null;
}
if (empty($user)) {
    $user = [
        'nama_lengkap' => $_SESSION['nama_lengkap'] ?? 'Pengguna',
        'username'     => $_SESSION['username'] ?? 'user',
        'role'         => $_SESSION['role'] ?? 'anggota',
    ];
}

$book = null;
$bookId = max(0, (int)($_GET['id'] ?? 0));
if ($bookId > 0 && $dbOk) {
    $sql = "SELECT id, judul, COALESCE(penulis, pengarang) AS pengarang, kategori,
                   COALESCE(tahun, tahun_terbit) AS tahun_terbit,
                   COALESCE(deskripsi, '') AS deskripsi,
                   cover_img, cover_emoji, file_pdf, rating, total_baca, bahasa, halaman
            FROM buku
            WHERE id = $bookId
            LIMIT 1";
    $r = mysqli_query($conn, $sql);
    if ($r) {
        $book = mysqli_fetch_assoc($r);
    }
}

$pdfUrl     = $book ? pdfPath($book['file_pdf']) : null;
$isNotFound = empty($book) || empty($pdfUrl);

require_once __DIR__ . '/../includes/anggota/notif_helper.php';

// ── Catat riwayat baca & notifikasi ──────────────────────────────────────────
if (!$isNotFound && $dbOk) {
    // Cek riwayat hari ini (hindari duplikat saat refresh)
    $cekRiwayat = mysqli_query($conn,
        "SELECT id FROM riwayat_baca
         WHERE id_anggota = $uid
           AND id_buku = $bookId
           AND DATE(tanggal_akses) = CURDATE()
         LIMIT 1"
    );

    // Cek apakah sudah pernah baca sebelumnya (untuk update vs insert)
    $cekRiwayatAll = mysqli_query($conn,
        "SELECT id, status FROM riwayat_baca
         WHERE id_anggota = $uid AND id_buku = $bookId
         ORDER BY id DESC LIMIT 1"
    );
    $riwayatLama = $cekRiwayatAll ? mysqli_fetch_assoc($cekRiwayatAll) : null;

    if ($cekRiwayat && mysqli_num_rows($cekRiwayat) === 0) {
        if ($riwayatLama) {
            // Sudah pernah baca sebelumnya — update tanggal_kembali & pertahankan status
            $riwId = (int) $riwayatLama['id'];
            $newStatus = ($riwayatLama['status'] === 'selesai') ? 'selesai' : 'sedang_dibaca';
            mysqli_query($conn,
                "UPDATE riwayat_baca
                 SET tanggal_kembali = NOW(), status = '$newStatus'
                 WHERE id = $riwId"
            );
        } else {
            // Pertama kali baca user ini — insert riwayat baru & naikkan total_baca
            mysqli_query($conn,
                "INSERT INTO riwayat_baca (id_anggota, id_buku, tanggal_akses, tanggal_kembali, status, created_at)
                 VALUES ($uid, $bookId, NOW(), NOW(), 'sedang_dibaca', NOW())"
            );
            // Naikkan counter hanya saat pertama kali user ini membaca buku ini
            mysqli_query($conn, "UPDATE buku SET total_baca = total_baca + 1 WHERE id = $bookId");
            // Kirim notifikasi hanya pertama kali
            $judulBuku = $book['judul'] ?? 'buku ini';
            kirimNotif($conn, $uid, 'info', 'Mulai Membaca',
                "Kamu mulai membaca \"$judulBuku\" hari ini.");
        }
    } else {
        // Sudah baca hari ini — update tanggal_kembali saja, jangan ubah counter
        if ($riwayatLama) {
            $riwId = (int) $riwayatLama['id'];
            // Jika status bukan selesai (misal baru direset ke sedang_dibaca), pertahankan status itu
            $keepStatus = $riwayatLama['status'] ?? 'sedang_dibaca';
            mysqli_query($conn,
                "UPDATE riwayat_baca SET tanggal_kembali = NOW(), status = '$keepStatus' WHERE id = $riwId"
            );
        }
    }
}

// Ambil data riwayat terkini untuk ditampilkan progress & status di UI
$riwayatSaatIni = null;
if ($dbOk && $bookId > 0) {
    $rq = mysqli_query($conn,
        "SELECT id, status FROM riwayat_baca
         WHERE id_anggota = $uid AND id_buku = $bookId
         ORDER BY id DESC LIMIT 1"
    );
    $riwayatSaatIni = $rq ? mysqli_fetch_assoc($rq) : null;
}
$riwId      = $riwayatSaatIni ? (int) $riwayatSaatIni['id'] : 0;
$riwStatus  = $riwayatSaatIni['status'] ?? 'sedang_dibaca';
$sudahSelesai = ($riwStatus === 'selesai');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Baca eBook — Pojok Baca</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/anggota/sidebar.css">
    <link rel="stylesheet" href="../assets/css/anggota/katalog_ebook.css">
    <style>
        .reader-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 300px;
            gap: 24px;
            align-items: start;
        }
        .reader-panel {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .reader-meta {
            display: grid;
            grid-template-columns: minmax(180px, 260px) 1fr;
            gap: 18px;
        }
        .reader-cover {
            border-radius: 18px;
            overflow: hidden;
            width: 100%;
            aspect-ratio: 2 / 3;
            background: linear-gradient(135deg, rgba(15,23,42,.95), rgba(51,65,85,.95));
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .reader-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center top;
            display: block;
        }
        .reader-cover-fallback {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
        }
        .reader-info {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .reader-info h1 {
            margin: 0;
            font-size: 2rem;
            line-height: 1.05;
        }
        .reader-info .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 18px;
            color: var(--muted);
            align-items: center;
        }
        .reader-info .meta span {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .reader-info p {
            color: var(--text);
            line-height: 1.75;
        }
        .reader-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        .reader-actions .btn-primary,
        .reader-actions .btn-secondary {
            min-width: 140px;
        }
        .reader-sidebar {
            display: flex;
            flex-direction: column;
            gap: 18px;
            position: sticky;
            top: 28px;
        }
        .reader-sidebar .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 18px;
            display: grid;
            gap: 14px;
        }
        .reader-sidebar .card h3 {
            margin: 0;
            font-size: 1rem;
            color: var(--text);
        }
        .reader-sidebar .card p {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
            font-size: 0.95rem;
        }
        .reader-sidebar .stat {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            color: var(--muted);
            font-size: 0.95rem;
        }
        .reader-frame {
            width: 100%;
            height: calc(100vh - 220px);
            min-height: 600px;
            border: 1px solid var(--border);
            border-radius: 18px;
            overflow: hidden;
            background: #090b12;
        }
        .reader-frame iframe {
            width: 100%;
            height: 100%;
            border: 0;
        }
        @media (max-width: 1080px) {
            .reader-layout {
                grid-template-columns: 1fr;
            }
            .reader-sidebar {
                position: static;
                top: auto;
            }
        }
    </style>
</head>
<body>

<?php require_once '../includes/anggota/sidebar.php'; ?>

<div class="main">
    <div class="topbar">
        <div class="topbar-left">
            <button class="menu-btn" onclick="toggleSidebar()">
                <?= icon('bars', 20) ?>
            </button>
            <div>
                <div class="topbar-title">Baca eBook</div>
                <div class="topbar-breadcrumb">Pojok Baca / <span>Baca eBook</span></div>
            </div>
        </div>
        <div class="topbar-right">
            <div class="user-chip">
                <div class="chip-ava"><?= strtoupper(substr($user['nama_lengkap'], 0, 1)) ?></div>
                <div>
                    <div class="chip-name"><?= htmlspecialchars(explode(' ', $user['nama_lengkap'])[0]) ?></div>
                    <div class="chip-role">Member</div>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <?php if ($isNotFound): ?>
            <div class="empty-state">
                <?= icon('info', 64, 'display:block;margin:0 auto 16px;opacity:0.18') ?>
                <h3>eBook tidak tersedia</h3>
                <p>Maaf, file PDF untuk eBook ini belum tersedia atau tidak dapat dibuka.</p>
                <a href="detail_ebook.php?id=<?= $bookId ?>" class="btn-primary" style="display:inline-flex;margin-top:20px;">
                    <?= icon('arrow-left', 14) ?> Kembali ke Detail
                </a>
            </div>
        <?php else: ?>
            <div class="reader-layout">
                <div class="reader-panel">
                    <div class="reader-meta">
                        <div class="reader-cover">
                            <?php $coverUrl = coverPath($book['cover_img']); ?>
                            <?php if ($coverUrl): ?>
                                <img src="<?= htmlspecialchars($coverUrl) ?>" alt="<?= htmlspecialchars($book['judul']) ?>">
                            <?php else: ?>
                                <div class="reader-cover-fallback"><?= htmlspecialchars($book['cover_emoji'] ?? '📚') ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="reader-info">
                            <div class="detail-badge"><?= htmlspecialchars($book['kategori'] ?? '-') ?></div>
                            <h1><?= htmlspecialchars($book['judul']) ?></h1>
                            <div class="meta">
                                <span><?= icon('pencil', 14) ?> <?= htmlspecialchars($book['pengarang'] ?? '-') ?></span>
                                <span><?= icon('info', 14) ?> <?= htmlspecialchars($book['tahun_terbit'] ?? '-') ?></span>
                            </div>
                            <div class="meta">
                                <span><?= icon('eye', 14) ?> <?= number_format((int)($book['total_baca'] ?? 0)) ?> dibaca</span>
                                <?php if (!empty($book['halaman'])): ?>
                                    <span><?= icon('book-open', 14) ?> <?= htmlspecialchars($book['halaman']) ?> halaman</span>
                                <?php endif; ?>
                            </div>
                            <p><?= nl2br(htmlspecialchars($book['deskripsi'] ?: 'Deskripsi belum tersedia.')) ?></p>
                            <div class="reader-actions">
                                <a href="<?= htmlspecialchars($pdfUrl) ?>" target="_blank" rel="noopener" class="btn-secondary">
                                    <?= icon('download', 14) ?> Buka di Tab Baru
                                </a>
                                <a href="detail_ebook.php?id=<?= $book['id'] ?>" class="btn-primary">
                                    <?= icon('arrow-left', 14) ?> Kembali ke Detail
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="reader-frame">
                        <iframe src="<?= htmlspecialchars($pdfUrl) ?>" allowfullscreen title="Pembaca eBook"></iframe>
                    </div>
                </div>

                <aside class="reader-sidebar">
                    <div class="card">
                        <h3>Informasi eBook</h3>
                        <div class="stat"><span>Judul</span><strong><?= htmlspecialchars($book['judul']) ?></strong></div>
                        <div class="stat"><span>Penulis</span><strong><?= htmlspecialchars($book['pengarang'] ?? '-') ?></strong></div>
                        <div class="stat"><span>Kategori</span><strong><?= htmlspecialchars($book['kategori'] ?? '-') ?></strong></div>
                        <div class="stat"><span>Bahasa</span><strong><?= htmlspecialchars($book['bahasa'] ?? '-') ?></strong></div>
                        <?php if (!empty($book['halaman'])): ?>
                        <div class="stat"><span>Halaman</span><strong><?= htmlspecialchars($book['halaman']) ?></strong></div>
                        <?php endif; ?>
                    </div>
                    <div class="card" id="progressCard">
                        <h3><?= icon('check-circle', 16) ?> Progress Membaca</h3>

                        <?php if ($sudahSelesai): ?>
                            <div class="progress-done-badge">
                                <?= icon('check-circle', 18) ?> Selesai Dibaca
                            </div>
                            <p style="font-size:.9rem;color:var(--muted)">Kamu sudah menyelesaikan buku ini.</p>
                            <button class="btn-reset-progress" onclick="resetProgress(<?= $riwId ?>)">
                                <?= icon('history', 14) ?> Baca Ulang
                            </button>
                        <?php else: ?>
                            <p style="font-size:.9rem;color:var(--muted);margin:0">Sudah selesai membaca buku ini?</p>
                            <button class="btn-selesai" id="btnSelesai" onclick="tandaiSelesai(<?= $riwId ?>)">
                                <?= icon('check', 16) ?> Tandai Selesai
                            </button>
                        <?php endif; ?>
                    </div>
                </aside>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.btn-selesai {
    display: flex; align-items: center; gap: 8px;
    width: 100%; padding: 10px 16px;
    background: linear-gradient(135deg, #2ecc8a, #16a34a);
    color: #fff; border: none; border-radius: 10px;
    font-size: 14px; font-weight: 600; cursor: pointer;
    transition: opacity .2s;
}
.btn-selesai:hover { opacity: .85; }
.btn-selesai:disabled { opacity: .5; cursor: not-allowed; }

.btn-reset-progress {
    display: flex; align-items: center; gap: 8px;
    width: 100%; padding: 9px 16px;
    background: transparent; border: 1px solid var(--border);
    color: var(--muted); border-radius: 10px;
    font-size: 13px; font-weight: 500; cursor: pointer;
    transition: background .2s;
}
.btn-reset-progress:hover { background: rgba(255,255,255,.05); }

.progress-done-badge {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 14px;
    background: rgba(46,204,138,.15);
    color: #2ecc8a;
    border-radius: 10px;
    font-weight: 600; font-size: 14px;
}

.toast-baca {
    position: fixed; bottom: 24px; right: 24px;
    background: #1e293b; border: 1px solid rgba(255,255,255,.1);
    color: #f0eeea; border-radius: 12px;
    padding: 14px 20px; font-size: 14px;
    display: flex; align-items: center; gap: 10px;
    z-index: 999; transform: translateY(80px); opacity: 0;
    transition: all .3s; pointer-events: none;
}
.toast-baca.show { transform: translateY(0); opacity: 1; }
</style>

<div class="toast-baca" id="toastBaca"></div>

<script>
function showToast(msg, ok = true) {
    const t = document.getElementById('toastBaca');
    t.innerHTML = (ok ? '✓ ' : '✕ ') + msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

function tandaiSelesai(riwId) {
    if (!riwId) return;
    const btn = document.getElementById('btnSelesai');
    btn.disabled = true;
    btn.textContent = 'Menyimpan...';

    fetch('update_progress.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'riwayat_id=' + riwId + '&status=selesai&progress=100'
    })
    .then(r => r.text())
    .then(text => {
        try {
            const d = JSON.parse(text);
            if (d.success) {
                showToast('Buku ditandai selesai!');
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast(d.message || 'Gagal menyimpan.', false);
                btn.disabled = false;
                btn.textContent = 'Tandai Selesai';
            }
        } catch(e) {
            console.error('Response bukan JSON:', text);
            showToast('Server error. Cek console.', false);
            btn.disabled = false;
            btn.textContent = 'Tandai Selesai';
        }
    })
    .catch(e => {
        showToast('Network error: ' + e.message, false);
        btn.disabled = false;
        btn.textContent = 'Tandai Selesai';
    });
}

function resetProgress(riwId) {
    if (!riwId || !confirm('Reset progress ke sedang dibaca?')) return;
    fetch('update_progress.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'riwayat_id=' + riwId + '&status=sedang_dibaca&progress=0'
    })
    .then(r => r.text())
    .then(text => {
        try {
            const d = JSON.parse(text);
            if (d.success) {
                showToast('Progress direset.');
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast(d.message || 'Gagal reset.', false);
            }
        } catch(e) {
            console.error('Response bukan JSON:', text);
            showToast('Server error. Cek console.', false);
        }
    })
    .catch(e => showToast('Network error: ' + e.message, false));
}
</script>

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('overlay').classList.toggle('show');
    }
    function closeSidebar() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('overlay').classList.remove('show');
    }
</script>
</body>
</html>