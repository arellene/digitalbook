<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['role'])) {
    header('Location: ../index.php');
    exit();
}

$isGuest = ($_SESSION['role'] === 'guest');

if ($isGuest) {
    header('Location: ../index.php');
    exit();
}

if (!is_numeric($_SESSION['user_id'] ?? '')) {
    header('Location: ../index.php');
    exit();
}

$dbOk = isset($conn) && $conn instanceof mysqli;
$uid  = (int) $_SESSION['user_id'];

$user = null;
if ($dbOk) {
    $q    = mysqli_query($conn, "SELECT * FROM users WHERE id = $uid LIMIT 1");
    $user = $q ? mysqli_fetch_assoc($q) : null;
}
if (empty($user)) {
    $user = [
        'nama_lengkap' => $_SESSION['nama_lengkap'] ?? 'Pengguna',
        'username'     => $_SESSION['username']     ?? 'user',
        'role'         => $_SESSION['role']         ?? 'anggota',
    ];
}

// ── HANDLE ACTIONS ──────────────────────────────────────────────────────────
$msg = '';
$msgType = '';

// Hapus dari wishlist
if (isset($_GET['hapus']) && is_numeric($_GET['hapus'])) {
    $wid_hapus = (int) $_GET['hapus'];
    if ($dbOk) {
        // Ambil judul untuk notifikasi sebelum hapus
        $qJudul = mysqli_query($conn, "SELECT b.judul FROM wishlist w LEFT JOIN buku b ON b.id = w.id_buku WHERE w.id = $wid_hapus AND w.id_anggota = $uid LIMIT 1");
        $judulHapus = ($qJudul && $row = mysqli_fetch_assoc($qJudul)) ? $row['judul'] : 'Buku';

        $del = mysqli_query($conn, "DELETE FROM wishlist WHERE id = $wid_hapus AND id_anggota = $uid");
        if ($del && mysqli_affected_rows($conn) > 0) {
            $msg = 'Buku berhasil dihapus dari wishlist.';
            $msgType = 'success';
            // Notifikasi
            require_once __DIR__ . '/../includes/anggota/notif_helper.php';
            kirimNotif($conn, $uid, 'info', 'Dihapus dari Wishlist', "\"$judulHapus\" dihapus dari wishlist kamu.");
        } else {
            $msg = 'Gagal menghapus atau data tidak ditemukan.';
            $msgType = 'error';
        }
    }
}

// ── FETCH WISHLIST ──────────────────────────────────────────────────────────
$wishlist = [];
if ($dbOk) {
    $sql = "SELECT w.id AS wid, w.id_buku AS buku_id, w.created_at,
                   COALESCE(b.judul, '[Buku Dihapus]') AS judul,
                   COALESCE(b.penulis, b.pengarang, '-') AS pengarang,
                   b.cover_img AS cover,
                   COALESCE(b.cover_emoji, '📖') AS cover_emoji,
                   COALESCE(b.kategori, '-') AS kategori,
                   COALESCE(b.tahun, b.tahun_terbit, '') AS tahun_terbit,
                   COALESCE(b.deskripsi,'') AS deskripsi,
                   b.rating, b.total_baca,
                   IF(b.id IS NULL, 1, 0) AS buku_dihapus
            FROM wishlist w
            LEFT JOIN buku b ON b.id = w.id_buku
            WHERE w.id_anggota = $uid
            ORDER BY w.created_at DESC";
    $r = mysqli_query($conn, $sql);
    if ($r) {
        while ($row = mysqli_fetch_assoc($r)) $wishlist[] = $row;
    }
}

// Tidak ada fallback dummy — tampilkan data nyata dari DB saja

$totalWishlist = count($wishlist);

// Filter & Search
$search = trim($_GET['q'] ?? '');
$filterKategori = trim($_GET['kategori'] ?? '');

$filtered = array_filter($wishlist, function($b) use ($search, $filterKategori) {
    $matchSearch = $search === '' ||
        stripos($b['judul'], $search) !== false ||
        stripos($b['pengarang'], $search) !== false;
    $matchKat = $filterKategori === '' || $b['kategori'] === $filterKategori;
    return $matchSearch && $matchKat;
});

$kategoriList = array_unique(array_column($wishlist, 'kategori'));
sort($kategoriList);

$covers = [
    ['#1a1a2e','#16213e','#0f3460'],
    ['#2d1b33','#4a1942','#7b2d8b'],
    ['#0d2137','#0a3d62','#1e88e5'],
    ['#1b2838','#2a475e','#1b8a4f'],
    ['#2c1810','#5c2d0e','#c0392b'],
    ['#1a2332','#233554','#4a90a4'],
    ['#2d2416','#5c4d2c','#d4a853'],
    ['#1f2b1f','#2d4a2d','#4caf50'],
];

$active_menu = "wishlist";

// ── SVG ICON HELPER ──────────────────────────────────────────────────────────
function icon($name, $size = 16, $style = '') {
    $s = $style ? " style=\"$style\"" : '';
    $icons = [
        'house'        => '<path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>',
        'book-open'    => '<path d="M21 4H3a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h18a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1zM3 6h8v12H3V6zm10 12V6h8v12h-8z"/>',
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
        'eye'          => '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>',
        'pencil'       => '<path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>',
        'user-lock'    => '<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-1.5 0-4.5.75-5.99 2.25C7.18 17.38 9.32 18 12 18c.36 0 .71-.02 1.05-.05-.03-.3-.05-.6-.05-.95 0-1.87.85-3.54 2.18-4.67C14.08 12.11 12.63 14 12 14zm6 1c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3zm0 4.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>',
        'compass'      => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-5.5-2.5l7.51-3.49L17.5 6.5 9.99 9.99 6.5 17.5zm5.5-6.6c.61 0 1.1.49 1.1 1.1s-.49 1.1-1.1 1.1-1.1-.49-1.1-1.1.49-1.1 1.1-1.1z"/>',
        'trash'        => '<path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>',
        'filter'       => '<path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/>',
        'x'            => '<path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>',
        'check'        => '<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>',
        'catalog'      => '<path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-1 9H9V9h10v2zm-4 4H9v-2h6v2zm4-8H9V5h10v2z"/>',
        'heart'        => '<path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>',
        'info'         => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>',
    ];
    $path = $icons[$name] ?? '<circle cx="12" cy="12" r="10"/>';
    return "<svg xmlns='http://www.w3.org/2000/svg' width='{$size}' height='{$size}' viewBox='0 0 24 24' fill='currentColor'{$s}>{$path}</svg>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wishlist — Pojok Baca</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/anggota/sidebar.css">
    <link rel="stylesheet" href="../assets/css/anggota/dashboard.css">
    <link rel="stylesheet" href="../assets/css/anggota/wishlist.css">
</head>
<body>

<?php require_once '../includes/anggota/sidebar.php'; ?>

<div class="main">

    <!-- TOPBAR -->
    <header class="topbar">
        <div class="topbar-left">
            <button class="menu-btn" onclick="toggleSidebar()" aria-label="Toggle menu">
                <?= icon('bars', 22) ?>
            </button>
            <div>
                <div class="topbar-title">Wishlist Saya</div>
                <div class="topbar-breadcrumb">
                    <span>Pojok Baca</span> &rsaquo; Wishlist
                </div>
            </div>
        </div>
        <div class="topbar-right">
            <div class="user-chip">
                <div class="chip-ava"><?= strtoupper(substr($user['nama_lengkap'], 0, 1)) ?></div>
                <div>
                    <div class="chip-name"><?= htmlspecialchars($user['nama_lengkap']) ?></div>
                    <div class="chip-role">Member</div>
                </div>
            </div>
        </div>
    </header>

    <!-- CONTENT -->
    <div class="content">

        <!-- FLASH MESSAGE -->
        <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?> anim">
            <?= icon($msgType === 'success' ? 'check-circle' : 'info', 16) ?>
            <?= htmlspecialchars($msg) ?>
        </div>
        <?php endif; ?>

        <!-- HERO -->
        <div class="hero anim anim-d1">
            <div class="hero-text">
                <div class="hero-eyebrow"><?= icon('star', 12) ?> &nbsp; Koleksi Impian</div>
                <h2>Wishlist <span>Bacaan</span> Kamu</h2>
                <p>Simpan buku yang ingin kamu baca nanti. Akses kapan saja dan mulai membaca langsung dari sini.</p>
                <div class="hero-btns">
                    <a href="katalog_ebook.php" class="btn-primary">
                        <?= icon('search', 15) ?> Tambah dari Katalog
                    </a>
                    <a href="koleksi.php" class="btn-secondary">
                        <?= icon('layers', 15) ?> Koleksi Saya
                    </a>
                </div>
            </div>
            <div class="hero-deco">
                <?= icon('heart', 80, 'color:rgba(99,102,241,0.25)') ?>
            </div>
        </div>

        <!-- STATS -->
        <div class="stats anim anim-d1">
            <div class="stat-card">
                <div class="stat-icon gold"><?= icon('star', 22) ?></div>
                <div>
                    <div class="stat-num"><?= $totalWishlist ?></div>
                    <div class="stat-label">Total Wishlist</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon teal"><?= icon('tags', 22) ?></div>
                <div>
                    <div class="stat-num"><?= count($kategoriList) ?></div>
                    <div class="stat-label">Kategori</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><?= icon('fire', 22) ?></div>
                <div>
                    <div class="stat-num">
                        <?php
                        $topRated = array_filter($wishlist, fn($b) => ($b['rating'] ?? 0) >= 4.7);
                        echo count($topRated);
                        ?>
                    </div>
                    <div class="stat-label">Rating Tinggi (≥4.7)</div>
                </div>
            </div>
        </div>

        <!-- FILTER + SEARCH -->
        <div class="anim anim-d2">
            <div class="section-head">
                <h3><?= icon('filter', 16) ?> Filter &amp; Cari</h3>
                <span class="result-badge">
                    <?= icon('bookmark', 12) ?> <strong><?= count($filtered) ?></strong> dari <?= $totalWishlist ?> buku
                </span>
            </div>
            <form method="GET" action="">
                <div class="filter-bar">
                    <div class="search-wrap">
                        <?= icon('search', 15) ?>
                        <input
                            type="text"
                            name="q"
                            class="search-input"
                            placeholder="Cari judul atau pengarang..."
                            value="<?= htmlspecialchars($search) ?>"
                        >
                    </div>
                    <select name="kategori" class="filter-select">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($kategoriList as $kat): ?>
                        <option value="<?= htmlspecialchars($kat) ?>" <?= $filterKategori === $kat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($kat) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="filter-btn">
                        <?= icon('search', 14) ?> Cari
                    </button>
                    <?php if ($search || $filterKategori): ?>
                    <a href="wishlist.php" class="clear-filter">
                        <?= icon('x', 13) ?> Reset
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- WISHLIST ITEMS -->
        <div class="anim anim-d3">
            <div class="section-head">
                <h3><?= icon('heart', 17) ?> Daftar Wishlist</h3>
                <?php if (count($filtered) > 0): ?>
                <a href="katalog_ebook.php" class="see-all">
                    Tambah Buku <?= icon('arrow-right', 14) ?>
                </a>
                <?php endif; ?>
            </div>

            <?php if (empty($filtered)): ?>
            <!-- EMPTY STATE -->
            <div class="empty-state" style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);">
                <?php if ($search || $filterKategori): ?>
                    <div class="empty-icon"><?= icon('search', 32) ?></div>
                    <h4>Tidak ditemukan</h4>
                    <p>Tidak ada buku yang cocok dengan filter kamu. Coba kata kunci atau kategori lain.</p>
                    <a href="wishlist.php" class="btn-secondary" style="margin-top:6px;">
                        <?= icon('x', 14) ?> Reset Filter
                    </a>
                <?php else: ?>
                    <div class="empty-icon"><?= icon('star', 32) ?></div>
                    <h4>Wishlist masih kosong</h4>
                    <p>Kamu belum menambahkan buku apapun. Jelajahi katalog dan simpan buku favorit kamu!</p>
                    <a href="katalog_ebook.php" class="btn-primary" style="margin-top:6px;">
                        <?= icon('search', 14) ?> Jelajahi Katalog
                    </a>
                <?php endif; ?>
            </div>

            <?php else: ?>
            <!-- GRID CARDS -->
            <div class="wishlist-grid">
                <?php foreach ($filtered as $i => $item):
                    $idx = (int)($item['buku_id'] ?? $i) % count($covers);
                    $c = $covers[$idx];
                    $rating = number_format((float)($item['rating'] ?? 0), 1);
                    $baca   = number_format((int)($item['total_baca'] ?? 0));
                    $tanggal = date('d M Y', strtotime($item['created_at'] ?? 'now'));
                    $wid = (int)$item['wid'];
                    $bid = (int)$item['buku_id'];
                    $cover = trim($item['cover'] ?? '');
                    $emoji = $item['cover_emoji'] ?? '📖';
                    $coverPath = '';
                    if ($cover !== '') {
                        if (preg_match('#^(?:https?://|/|\.\./)#', $cover)) {
                            $coverPath = $cover;
                        } elseif (str_starts_with($cover, 'uploads/cover/')) {
                            $coverPath = '../' . $cover;
                        } else {
                            $coverPath = '../uploads/cover/' . ltrim($cover, '/');
                        }
                    }
                ?>
                <div class="wl-card" id="wl-card-<?= $wid ?>">
                    <!-- Cover -->
                    <div class="wl-cover" style="width:90px;min-width:90px;flex-shrink:0;position:relative;overflow:hidden;align-self:stretch;background:linear-gradient(160deg,<?= $c[0] ?>,<?= $c[1] ?>,<?= $c[2] ?>);">
                        <?php if ($coverPath !== ''): ?>
                            <img src="<?= htmlspecialchars($coverPath) ?>"
                                 alt="<?= htmlspecialchars($item['judul']) ?>"
                                 style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block;max-width:none;min-width:unset;">
                        <?php else: ?>
                            <span class="cover-placeholder"><?= $emoji ?></span>
                        <?php endif; ?>
                        <div class="cover-rating"><?= icon('star', 10, 'color:#fbbf24') ?> <?= $rating ?></div>
                    </div>
                    <!-- Body -->
                    <div class="wl-body">
                        <div class="wl-genre"><?= htmlspecialchars($item['kategori'] ?? '-') ?></div>
                        <div class="wl-title"><?= htmlspecialchars($item['judul']) ?></div>
                        <?php if (!empty($item['buku_dihapus'])): ?>
                            <div class="wl-author" style="color:#e05c5c;font-size:11px;">
                                ⚠️ Buku ini telah dihapus oleh admin
                            </div>
                        <?php else: ?>
                        <div class="wl-author">
                            <?= icon('pencil', 11) ?>
                            <?= htmlspecialchars($item['pengarang'] ?? '-') ?>
                        </div>
                        <?php if (!empty($item['deskripsi'])): ?>
                        <div class="wl-desc"><?= htmlspecialchars(mb_strimwidth($item['deskripsi'], 0, 90, '...')) ?></div>
                        <?php endif; ?>
                        <div class="wl-meta">
                            <span class="wl-reads">
                                <?= icon('eye', 11) ?> <?= $baca ?> dibaca
                            </span>
                            <span class="wl-date"><?= icon('bookmark', 10) ?> <?= $tanggal ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="wl-actions">
                            <?php if (empty($item['buku_dihapus'])): ?>
                            <a href="detail_ebook.php?id=<?= $bid ?>" class="wl-btn wl-btn-detail">
                                <?= icon('eye', 13) ?> Detail
                            </a>
                            <a href="baca.php?id=<?= $bid ?>" class="wl-btn wl-btn-read">
                                <?= icon('book-open', 13) ?> Baca
                            </a>
                            <?php endif; ?>
                            <button
                                class="wl-btn wl-btn-del"
                                onclick="confirmDelete(<?= $wid ?>, '<?= addslashes(htmlspecialchars($item['judul'])) ?>')">
                                <?= icon('trash', 13) ?> Hapus
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /content -->
</div><!-- /main -->

<!-- CONFIRM DELETE MODAL -->
<div class="modal-overlay" id="modalDelete">
    <div class="modal-box">
        <div class="modal-icon"><?= icon('trash', 24) ?></div>
        <h4>Hapus dari Wishlist?</h4>
        <p id="modalMsg">Buku ini akan dihapus dari wishlist kamu. Tindakan ini tidak bisa dibatalkan.</p>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeModal()">
                <?= icon('x', 14) ?> Batal
            </button>
            <a id="modalConfirmBtn" href="#" class="btn-confirm">
                <?= icon('trash', 14) ?> Hapus
            </a>
        </div>
    </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast">
    <?= icon('user-lock', 18, 'color:var(--accent)') ?>
    <span>Fitur ini membutuhkan akun.
        <a href="../auth/register.php">Daftar</a> atau
        <a href="../index.php">Login</a>.
    </span>
</div>

<script>
    /* Sidebar toggle */
    function toggleSidebar() {
        const s = document.getElementById('sidebar');
        const o = document.getElementById('overlay');
        s.classList.toggle('open');
        o.classList.toggle('show');
    }
    function closeSidebar() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('overlay').classList.remove('show');
    }

    /* Toast */
    let toastTimer;
    function showToast(e) {
        e && e.preventDefault();
        const t = document.getElementById('toast');
        t.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => t.classList.remove('show'), 4000);
    }

    /* Confirm delete modal */
    function confirmDelete(wid, judul) {
        document.getElementById('modalMsg').textContent =
            '"' + judul + '" akan dihapus dari wishlist kamu. Tindakan ini tidak bisa dibatalkan.';
        document.getElementById('modalConfirmBtn').href =
            'wishlist.php?hapus=' + wid;
        document.getElementById('modalDelete').classList.add('show');
    }
    function closeModal() {
        document.getElementById('modalDelete').classList.remove('show');
    }
    // Close modal on overlay click
    document.getElementById('modalDelete').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    /* Auto-hide flash message */
    const alert = document.querySelector('.alert');
    if (alert) setTimeout(() => alert.style.opacity = '0', 3500);
</script>
</body>
</html>