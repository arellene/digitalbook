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
$bookId = (int) ($_GET['id'] ?? 0);
if ($bookId > 0 && $dbOk) {
    $sql = "SELECT id, judul, COALESCE(penulis, pengarang) AS pengarang, kategori,
                   COALESCE(tahun, tahun_terbit) AS tahun_terbit,
                   COALESCE(deskripsi, '') AS deskripsi,
                   cover_img, cover_emoji,
                   rating, total_baca, file_pdf, isbn, bahasa, halaman, stok
            FROM buku
            WHERE id = $bookId
            LIMIT 1";
    $r = mysqli_query($conn, $sql);
    if ($r) {
        $book = mysqli_fetch_assoc($r);
    }
}

$isNotFound = empty($book);

// ── FITUR BARU: Proses submit / hapus ulasan ─────────────────────────────────
$ulasanPesan = '';
$ulasanTipe  = '';
if (!$isNotFound && $dbOk && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_ulasan'])) {
    if ($_POST['aksi_ulasan'] === 'simpan') {
        $ratingBaru   = isset($_POST['rating']) ? (int) $_POST['rating'] : 0;
        $komentarBaru = isset($_POST['komentar']) ? trim($_POST['komentar']) : '';

        if ($ratingBaru < 1 || $ratingBaru > 5) {
            $ulasanPesan = 'Rating harus antara 1–5 bintang.';
            $ulasanTipe  = 'error';
        } else {
            $komentarEsc = mysqli_real_escape_string($conn, $komentarBaru);
            $sql = "INSERT INTO ulasan (buku_id, user_id, rating, komentar)
                    VALUES ($bookId, $uid, $ratingBaru, " . ($komentarBaru ? "'$komentarEsc'" : "NULL") . ")
                    ON DUPLICATE KEY UPDATE
                        rating = VALUES(rating),
                        komentar = VALUES(komentar),
                        created_at = NOW()";
            if (mysqli_query($conn, $sql)) {
                $ulasanPesan = 'Ulasan berhasil disimpan!';
                $ulasanTipe  = 'success';
            } else {
                $ulasanPesan = 'Gagal menyimpan ulasan.';
                $ulasanTipe  = 'error';
            }
        }
    } elseif ($_POST['aksi_ulasan'] === 'hapus') {
        if (mysqli_query($conn, "DELETE FROM ulasan WHERE buku_id = $bookId AND user_id = $uid")) {
            $ulasanPesan = 'Ulasan kamu berhasil dihapus.';
            $ulasanTipe  = 'success';
        } else {
            $ulasanPesan = 'Gagal menghapus ulasan.';
            $ulasanTipe  = 'error';
        }
    }
}

// ── Cek status wishlist & koleksi user untuk buku ini ────────────────────────
$inWishlist = false;
$inKoleksi  = false;
if (!$isNotFound && $dbOk) {
    $w = mysqli_query($conn, "SELECT id FROM wishlist WHERE id_anggota = $uid AND id_buku = $bookId LIMIT 1");
    $inWishlist = $w && mysqli_num_rows($w) > 0;

    $k = mysqli_query($conn, "SELECT id FROM koleksi WHERE id_anggota = $uid AND id_buku = $bookId LIMIT 1");
    $inKoleksi = $k && mysqli_num_rows($k) > 0;
}

// ── FITUR BARU: Statistik copy, antrian, dipinjam, ukuran file ───────────────
$fileSizeLabel  = '—';
$totalDibaca    = 0;
$ulasanList     = [];

if (!$isNotFound && $dbOk) {
    // Ukuran file PDF (dibaca langsung dari disk)
    if (!empty($book['file_pdf'])) {
        $filePath = '../' . ltrim($book['file_pdf'], '/');
        if (file_exists($filePath)) {
            $bytes = filesize($filePath);
            $fileSizeLabel = $bytes >= 1048576
                ? number_format($bytes / 1048576, 1) . ' MB'
                : number_format($bytes / 1024, 1) . ' KB';
        }
    }

    $totalDibaca = (int) ($book['total_baca'] ?? 0);

    // Daftar ulasan
    $rUlasan = mysqli_query($conn, "
        SELECT u.rating, u.komentar, u.created_at, u.user_id, us.nama_lengkap
        FROM ulasan u
        JOIN users us ON us.id = u.user_id
        WHERE u.buku_id = $bookId
        ORDER BY u.created_at DESC
    ");
    if ($rUlasan) while ($row = mysqli_fetch_assoc($rUlasan)) $ulasanList[] = $row;
}

// ── FITUR BARU: Ulasan milik user yang sedang login (untuk prefill form) ────
$ulasanSaya = null;
if (!$isNotFound && $dbOk) {
    $rSaya = mysqli_query($conn, "SELECT rating, komentar FROM ulasan WHERE buku_id = $bookId AND user_id = $uid LIMIT 1");
    $ulasanSaya = $rSaya ? mysqli_fetch_assoc($rSaya) : null;
}

// ── Flash message dari aksi wishlist/koleksi ─────────────────────────────────
$flash = null;
if (!empty($_SESSION['flash_wishlist'])) {
    $flash = $_SESSION['flash_wishlist'];
    unset($_SESSION['flash_wishlist']);
} elseif (!empty($_SESSION['flash_koleksi'])) {
    $flash = $_SESSION['flash_koleksi'];
    unset($_SESSION['flash_koleksi']);
}

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
        'message-sq'   => '<path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>',
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail eBook — Pojok Baca</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/anggota/sidebar.css">
    <link rel="stylesheet" href="../assets/css/anggota/katalog_ebook.css">
    <style>
        .detail-card {
            display: grid;
            grid-template-columns: minmax(220px, 320px) 1fr;
            gap: 28px;
            padding: 24px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            min-height: 520px;
            align-items: start;
        }
        .detail-cover {
            position: relative;
            min-height: 420px;
            border-radius: calc(var(--radius) * 1.1);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(15,23,42,.95), rgba(51,65,85,.95));
        }
        .detail-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .cover-fallback {
            font-size: 5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
        }
        .detail-body {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .detail-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(99,102,241,.12);
            color: var(--accent);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .4px;
            width: fit-content;
        }
        .detail-title {
            font-size: 2.25rem;
            line-height: 1.05;
            margin: 0;
            max-width: 780px;
        }
        .detail-subtitle,
        .detail-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            color: var(--muted);
            align-items: center;
        }
        .detail-subtitle span,
        .detail-meta span {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .detail-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        .detail-actions .btn-secondary {
            border-color: rgba(255,255,255,.12);
        }
        .detail-extra {
            padding: 16px 18px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: rgba(148,163,184,.06);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text);
        }
        @media (max-width: 900px) {
            .detail-card {
                grid-template-columns: 1fr;
            }
        }
        /* ── Tombol Wishlist & Koleksi ── */
        .btn-wishlist,
        .btn-koleksi {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid var(--border);
            color: var(--text);
            background: var(--surface);
        }
        .btn-wishlist.active,
        .btn-koleksi.active {
            background: rgba(99,102,241,.14);
            border-color: var(--accent);
            color: var(--accent);
        }
        .btn-ulasan {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid var(--border);
            color: var(--text);
            background: var(--surface);
        }

        /* ── FITUR BARU: Info Stats Bar (gaya iPusnas) ── */
        .info-stats-bar {
            display: flex;
            gap: 32px;
            flex-wrap: wrap;
            padding: 20px 24px;
            margin-top: 20px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: var(--surface);
        }
        .stat-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(99,102,241,.12);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .stat-label {
            font-size: 11.5px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .3px;
        }
        .stat-value {
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
        }

        /* ── FITUR BARU: Info Meta Row (dibaca / antrian / dipinjam) ── */
        .info-meta-row {
            display: flex;
            gap: 32px;
            flex-wrap: wrap;
            padding: 18px 24px;
            border: 1px solid var(--border);
            border-top: none;
            border-radius: 0 0 var(--radius) var(--radius);
            background: var(--surface);
            margin-top: -1px;
        }
        .meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--muted);
        }
        .meta-item svg { color: var(--accent); flex-shrink: 0; }
        .meta-label {
            font-size: 11.5px;
            font-weight: 700;
            color: var(--muted);
        }
        .meta-value {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--text);
        }

        /* ── FITUR BARU: Tabs Deskripsi / Detail / Ulasan ── */
        .detail-tabs {
            display: flex;
            gap: 28px;
            margin-top: 20px;
            padding: 0 4px;
            border-bottom: 1px solid var(--border);
        }
        .tab-btn {
            background: none;
            border: none;
            padding: 12px 2px;
            font-size: 14px;
            font-weight: 700;
            color: var(--muted);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            font-family: inherit;
        }
        .tab-btn.active {
            color: var(--accent);
            border-bottom-color: var(--accent);
        }
        .tab-panel { display: none; padding: 20px 4px; }
        .tab-panel.active { display: block; }
        .tab-panel .detail-desc { color: var(--text); line-height: 1.8; max-width: 760px; }

        .detail-table { width: 100%; border-collapse: collapse; max-width: 640px; }
        .detail-table td {
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
            color: var(--text);
            font-size: 13.5px;
        }
        .detail-table td:first-child { color: var(--muted); width: 180px; }

        .ulasan-item {
            display: flex;
            gap: 14px;
            padding: 16px 0;
            border-bottom: 1px solid var(--border);
        }
        .ulasan-item:last-child { border-bottom: none; }
        .ulasan-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--accent);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            flex-shrink: 0;
            font-size: 14px;
        }
        .ulasan-body { flex: 1; min-width: 0; }
        .ulasan-head {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            color: var(--text);
        }
        .ulasan-rating-label { font-size: 12px; color: var(--muted); font-weight: 400; }
        .ulasan-stars { font-size: 12px; letter-spacing: 1px; }
        .ulasan-date { font-size: 11.5px; color: var(--muted); margin: 3px 0 8px; }
        .ulasan-text { margin: 0 0 6px; font-size: 13.5px; color: var(--text); line-height: 1.6; }
        .ulasan-reply {
            font-size: 12.5px;
            color: var(--accent);
            text-decoration: none;
            font-weight: 700;
        }
        .empty-ulasan { color: var(--muted); font-size: 13.5px; padding: 8px 0; }

        /* ── FITUR BARU: Form Tulis Ulasan ── */
        .ulasan-form-box {
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 18px 20px;
            margin-bottom: 20px;
            background: rgba(148,163,184,.04);
        }
        .ulasan-form-title { font-size: 14px; font-weight: 700; color: var(--text); margin-bottom: 12px; }
        .rating-picker { display: flex; flex-direction: row-reverse; justify-content: flex-end; gap: 4px; margin-bottom: 12px; }
        .rating-picker input { display: none; }
        .rating-picker label {
            font-size: 26px;
            color: var(--border);
            cursor: pointer;
            transition: color .15s;
        }
        .rating-picker label.lit,
        .rating-picker input:checked ~ label,
        .rating-picker label:hover,
        .rating-picker label:hover ~ label { color: #fbbf24; }
        .ulasan-textarea {
            width: 100%;
            min-height: 80px;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg, #10131a);
            color: var(--text);
            font-family: inherit;
            font-size: 13.5px;
            resize: vertical;
            margin-bottom: 12px;
        }
        .ulasan-form-actions { display: flex; gap: 10px; align-items: center; }
        .btn-ulasan-save {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 18px;
            border-radius: 8px;
            border: none;
            background: var(--accent);
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-ulasan-hapus {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 16px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: transparent;
            color: #ef4444;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }
        .ulasan-form-msg {
            font-size: 12.5px;
            padding: 8px 12px;
            border-radius: 8px;
            margin-bottom: 12px;
        }
        .ulasan-form-msg.success { background: rgba(34,197,94,.12); color: #4ade80; }
        .ulasan-form-msg.error { background: rgba(239,68,68,.12); color: #f87171; }
        .mine-tag {
            font-size: 10.5px;
            font-weight: 700;
            color: var(--accent);
            background: rgba(99,102,241,.12);
            padding: 2px 8px;
            border-radius: 999px;
            margin-left: 6px;
        }
    </style>
</head>
<body>

<?php include '../includes/anggota/sidebar.php'; ?>

<div class="main">
    <div class="topbar">
        <div class="topbar-left">
            <button class="menu-btn" onclick="toggleSidebar()"><?= icon('bars', 20) ?></button>
            <div>
                <div class="topbar-title">Detail eBook</div>
                <div class="topbar-breadcrumb">Pojok Baca / <span>Detail eBook</span></div>
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
        <?php if ($flash): ?>
        <?php
            $icon_f = $flash['tipe'] === 'success' ? 'check-circle' : ($flash['tipe'] === 'info' ? 'info' : 'x');
            $label  = match($flash['pesan']) {
                'success_wishlist' => '✅ Buku berhasil ditambahkan ke <a href="wishlist.php">Wishlist</a>.',
                'already_wishlist' => 'ℹ️ Buku sudah ada di <a href="wishlist.php">Wishlist</a> kamu.',
                'success_koleksi'  => '✅ Buku berhasil ditambahkan ke <a href="koleksi.php">Koleksi</a>.',
                'already_koleksi'  => 'ℹ️ Buku sudah ada di <a href="koleksi.php">Koleksi</a> kamu.',
                default            => htmlspecialchars($flash['pesan']),
            };
        ?>
        <div class="flash-msg flash-<?= $flash['tipe'] ?>"><?= $label ?></div>
        <?php endif; ?>

        <?php if ($isNotFound): ?>
            <div class="empty-state">
                <?= icon('info', 64, 'display:block;margin:0 auto 16px;opacity:0.18') ?>
                <h3>eBook tidak ditemukan</h3>
                <p>Maaf, eBook yang Anda cari tidak tersedia. Kembali ke katalog untuk melihat pilihan buku lainnya.</p>
                <a href="katalog_ebook.php" class="btn-primary" style="display:inline-flex;margin-top:20px;">
                    <?= icon('arrow-left', 14) ?> Kembali ke Katalog
                </a>
            </div>
        <?php else: ?>
            <div class="detail-card">
                <div class="detail-cover">
                    <?php $coverUrl = coverPath($book['cover_img']); ?>
                    <?php if ($coverUrl): ?>
                        <img src="<?= htmlspecialchars($coverUrl) ?>" alt="<?= htmlspecialchars($book['judul']) ?>">
                    <?php else: ?>
                        <div class="cover-fallback"><?= htmlspecialchars($book['cover_emoji'] ?? '📚') ?></div>
                    <?php endif; ?>
                </div>
                <div class="detail-body">
                    <div class="detail-badge"><?= htmlspecialchars($book['kategori'] ?? '-') ?></div>
                    <h1 class="detail-title"><?= htmlspecialchars($book['judul']) ?></h1>
                    <div class="detail-subtitle">
                        <span><?= icon('pencil', 14) ?> <?= htmlspecialchars($book['pengarang'] ?? '-') ?></span>
                        <span><?= icon('info', 14) ?> <?= htmlspecialchars($book['tahun_terbit'] ?? '-') ?></span>
                    </div>
                    <div class="detail-meta">
                        <span><?= icon('star', 14) ?> <?= number_format((float)($book['rating'] ?? 0), 1) ?></span>
                        <span><?= icon('eye', 14) ?> <?= number_format((int)($book['total_baca'] ?? 0)) ?> dibaca</span>
                        <?php if (!empty($book['halaman'])): ?>
                        <span><?= icon('book-open', 14) ?> <?= htmlspecialchars($book['halaman']) ?> halaman</span>
                        <?php endif; ?>
                    </div>
                    <div class="detail-actions">
                        <a href="baca.php?id=<?= $book['id'] ?>" class="btn-primary">
                            <?= icon('eye', 14) ?> Baca Sekarang
                        </a>
                        <a href="ajax/tambah_wishlist.php?id=<?= $bookId ?>&redirect=detail_ebook.php%3Fid%3D<?= $bookId ?>"
                           class="btn-wishlist <?= $inWishlist ? 'active' : '' ?>"
                           title="<?= $inWishlist ? 'Sudah di Wishlist' : 'Tambah ke Wishlist' ?>">
                            <?= icon('bookmark', 15) ?>
                            <?= $inWishlist ? 'Di Wishlist' : 'Wishlist' ?>
                        </a>
                        <a href="ajax/tambah_koleksi.php?id=<?= $bookId ?>&redirect=detail_ebook.php%3Fid%3D<?= $bookId ?>"
                           class="btn-koleksi <?= $inKoleksi ? 'active' : '' ?>"
                           title="<?= $inKoleksi ? 'Sudah di Koleksi' : 'Tambah ke Koleksi' ?>">
                            <?= icon('layers', 15) ?>
                            <?= $inKoleksi ? 'Di Koleksi' : 'Koleksi' ?>
                        </a>
                        <a href="katalog_ebook.php" class="btn-secondary">
                            <?= icon('arrow-left', 14) ?> Kembali ke Katalog
                        </a>
                    </div>
                    <?php if (!empty($book['file_pdf'])): ?>
                        <div class="detail-extra">
                            <a href="../<?= htmlspecialchars(ltrim($book['file_pdf'], '/')) ?>" target="_blank">
                                <?= icon('download', 14) ?> Unduh PDF
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- FITUR BARU: Info Stats Bar -->
            <div class="info-stats-bar">
                <div class="stat-item">
                    <div class="stat-icon"><?= icon('download', 18) ?></div>
                    <div>
                        <div class="stat-label">File Size</div>
                        <div class="stat-value"><?= htmlspecialchars($fileSizeLabel) ?></div>
                    </div>
                </div>
            </div>

            <!-- FITUR BARU: Info Meta Row -->
            <div class="info-meta-row">
                <div class="meta-item">
                    <?= icon('eye', 18) ?>
                    <div>
                        <div class="meta-label">Telah dibaca Oleh</div>
                        <div class="meta-value"><?= number_format($totalDibaca) ?> Pengguna</div>
                    </div>
                </div>
            </div>

            <!-- FITUR BARU: Tabs Deskripsi / Detail / Ulasan -->
            <div class="detail-tabs">
                <button class="tab-btn active" data-tab="deskripsi">Deskripsi</button>
                <button class="tab-btn" data-tab="detail">Detail</button>
                <button class="tab-btn" data-tab="ulasan">Ulasan <?= count($ulasanList) ? '(' . count($ulasanList) . ')' : '' ?></button>
            </div>

            <div class="tab-panel active" id="tab-deskripsi">
                <div class="detail-desc">
                    <?= nl2br(htmlspecialchars($book['deskripsi'] ?: 'Deskripsi belum tersedia.')) ?>
                </div>
            </div>

            <div class="tab-panel" id="tab-detail">
                <table class="detail-table">
                    <tr><td>ISBN</td><td><?= htmlspecialchars($book['isbn'] ?: '-') ?></td></tr>
                    <tr><td>Bahasa</td><td><?= htmlspecialchars($book['bahasa'] ?: '-') ?></td></tr>
                    <tr><td>Jumlah Halaman</td><td><?= $book['halaman'] ? htmlspecialchars($book['halaman']) . ' halaman' : '-' ?></td></tr>
                    <tr><td>Kategori</td><td><?= htmlspecialchars($book['kategori'] ?: '-') ?></td></tr>
                    <tr><td>Tahun Terbit</td><td><?= htmlspecialchars($book['tahun_terbit'] ?: '-') ?></td></tr>
                    <tr><td>Penulis</td><td><?= htmlspecialchars($book['pengarang'] ?: '-') ?></td></tr>
                </table>
            </div>

            <div class="tab-panel" id="tab-ulasan">
                <!-- FITUR BARU: Form Tulis / Edit Ulasan -->
                <div class="ulasan-form-box">
                    <div class="ulasan-form-title"><?= $ulasanSaya ? 'Edit Ulasan Kamu' : 'Tulis Ulasan' ?></div>

                    <?php if ($ulasanPesan): ?>
                    <div class="ulasan-form-msg <?= $ulasanTipe ?>"><?= htmlspecialchars($ulasanPesan) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="detail_ebook.php?id=<?= $bookId ?>#tab-ulasan" id="formUlasan">
                        <input type="hidden" name="aksi_ulasan" value="simpan">
                        <div class="rating-picker" id="ratingPicker">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" name="rating" id="bintang<?= $i ?>" value="<?= $i ?>"
                                <?= (!empty($ulasanSaya) && (int) $ulasanSaya['rating'] === $i) ? 'checked' : '' ?>>
                            <label for="bintang<?= $i ?>">★</label>
                            <?php endfor; ?>
                        </div>
                        <textarea name="komentar" class="ulasan-textarea"
                                  placeholder="Bagikan pendapatmu tentang buku ini..."><?= htmlspecialchars($ulasanSaya['komentar'] ?? '') ?></textarea>
                        <div class="ulasan-form-actions">
                            <button type="submit" class="btn-ulasan-save">
                                <?= icon('check-circle', 14) ?> <?= $ulasanSaya ? 'Simpan Perubahan' : 'Kirim Ulasan' ?>
                            </button>
                        </div>
                    </form>
                    <?php if ($ulasanSaya): ?>
                    <form method="POST" action="detail_ebook.php?id=<?= $bookId ?>#tab-ulasan" style="margin-top:8px;"
                          onsubmit="return confirm('Yakin ingin menghapus ulasan kamu?')">
                        <input type="hidden" name="aksi_ulasan" value="hapus">
                        <button type="submit" class="btn-ulasan-hapus">Hapus Ulasan Saya</button>
                    </form>
                    <?php endif; ?>
                </div>

                <?php if (empty($ulasanList)): ?>
                    <p class="empty-ulasan">Belum ada ulasan untuk eBook ini. Jadilah yang pertama!</p>
                <?php else: ?>
                    <?php foreach ($ulasanList as $u):
                        $mine = ((int) $u['user_id'] === $uid);
                    ?>
                    <div class="ulasan-item">
                        <div class="ulasan-avatar"><?= strtoupper(substr($u['nama_lengkap'], 0, 1)) ?></div>
                        <div class="ulasan-body">
                            <div class="ulasan-head">
                                <strong><?= htmlspecialchars($u['nama_lengkap']) ?></strong>
                                <?php if ($mine): ?><span class="mine-tag">Ulasan Anda</span><?php endif; ?>
                                <span class="ulasan-rating-label">Memberikan rating</span>
                                <span class="ulasan-stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?= $i <= (int) $u['rating'] ? '⭐' : '☆' ?>
                                    <?php endfor; ?>
                                </span>
                            </div>
                            <div class="ulasan-date"><?= date('d F Y - H.i', strtotime($u['created_at'])) ?> WIB</div>
                            <p class="ulasan-text"><?= nl2br(htmlspecialchars($u['komentar'] ?: '')) ?></p>
                            <a href="#" class="ulasan-reply">Balas</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>
</div>

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('overlay').classList.toggle('show');
    }
    function closeSidebar() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('overlay').classList.remove('show');
    }

    // ── FITUR BARU: Switch Tab Deskripsi / Detail / Ulasan ──
    function activateTab(tabName) {
        document.querySelectorAll('.tab-btn').forEach(function (b) { b.classList.remove('active'); });
        document.querySelectorAll('.tab-panel').forEach(function (p) { p.classList.remove('active'); });
        var btn = document.querySelector('.tab-btn[data-tab="' + tabName + '"]');
        var panel = document.getElementById('tab-' + tabName);
        if (btn) btn.classList.add('active');
        if (panel) panel.classList.add('active');
    }

    document.querySelectorAll('.tab-btn').forEach(function (btn) {
        btn.addEventListener('click', function () { activateTab(btn.dataset.tab); });
    });

    // Buka tab Ulasan otomatis kalau URL mengandung #tab-ulasan (setelah submit form ulasan)
    if (window.location.hash === '#tab-ulasan') {
        activateTab('ulasan');
    }
</script>
</body>
</html> 