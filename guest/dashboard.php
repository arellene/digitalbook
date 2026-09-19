<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['role'])) {
    header('Location: ../index.php');
    exit();
}

$isGuest = ($_SESSION['role'] === 'guest');

if (!$isGuest && !is_numeric($_SESSION['user_id'] ?? '')) {
    header('Location: ../index.php');
    exit();
}

$dbOk = isset($conn) && $conn instanceof mysqli;

$user = null;
if (!$isGuest && $dbOk) {
    $uid  = (int) $_SESSION['user_id'];
    $q    = mysqli_query($conn, "SELECT * FROM users WHERE id = $uid LIMIT 1");
    $user = $q ? mysqli_fetch_assoc($q) : null;
}
if (empty($user)) {
    $user = [
        'nama_lengkap' => $_SESSION['nama_lengkap'] ?? ($isGuest ? 'Tamu' : 'Pengguna'),
        'username'     => $_SESSION['username']     ?? ($isGuest ? 'guest' : 'user'),
        'role'         => $_SESSION['role']         ?? 'anggota',
    ];
}

$totalEbook = $totalKategori = $totalBaru = 0;
if ($dbOk) {
    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM buku");
    if ($r) $totalEbook = (int) mysqli_fetch_assoc($r)['c'];
    $r = mysqli_query($conn, "SELECT COUNT(DISTINCT kategori) AS c FROM buku WHERE kategori IS NOT NULL");
    if ($r) $totalKategori = (int) mysqli_fetch_assoc($r)['c'];
    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM buku WHERE tahun = YEAR(NOW())");
    if ($r) $totalBaru = (int) mysqli_fetch_assoc($r)['c'];
}

$featured = [];
if ($dbOk) {
    $sql = "SELECT id, judul, COALESCE(penulis,pengarang) AS pengarang, kategori,
                   COALESCE(tahun,tahun_terbit) AS tahun_terbit,
                   COALESCE(deskripsi,'') AS deskripsi,
                   cover_img, cover_emoji,
                   rating, total_baca
            FROM buku ORDER BY id DESC LIMIT 8";
    $r = mysqli_query($conn, $sql);
    if ($r) while ($row = mysqli_fetch_assoc($r)) $featured[] = $row;
}

if ($totalEbook    === 0) $totalEbook    = 12;
if ($totalKategori === 0) $totalKategori = 8;
if ($totalBaru     === 0) $totalBaru     = 5;

if (empty($featured)) {
    $featured = [
        ['id'=>1,'judul'=>'Atomic Habits','pengarang'=>'James Clear','kategori'=>'Motivasi & Inspirasi','tahun_terbit'=>'2022','rating'=>4.9,'total_baca'=>876],
        ['id'=>2,'judul'=>'Sapiens','pengarang'=>'Yuval Noah Harari','kategori'=>'Sejarah & Budaya','tahun_terbit'=>'2017','rating'=>4.8,'total_baca'=>1203],
        ['id'=>3,'judul'=>'Rich Dad Poor Dad','pengarang'=>'Robert T. Kiyosaki','kategori'=>'Bisnis & Ekonomi','tahun_terbit'=>'2021','rating'=>4.7,'total_baca'=>1567],
        ['id'=>4,'judul'=>'Laskar Pelangi','pengarang'=>'Andrea Hirata','kategori'=>'Novel','tahun_terbit'=>'2005','rating'=>4.9,'total_baca'=>2341],
        ['id'=>5,'judul'=>'Python untuk Pemula','pengarang'=>'Ahmad Rizky','kategori'=>'Komputer & Pemrograman','tahun_terbit'=>'2023','rating'=>4.6,'total_baca'=>534],
        ['id'=>6,'judul'=>'Filosofi Teras','pengarang'=>'Henry Manampiring','kategori'=>'Motivasi & Inspirasi','tahun_terbit'=>'2019','rating'=>4.8,'total_baca'=>1876],
        ['id'=>7,'judul'=>'Bumi','pengarang'=>'Tere Liye','kategori'=>'Novel','tahun_terbit'=>'2022','rating'=>4.6,'total_baca'=>923],
        ['id'=>8,'judul'=>'Data Science dengan Python','pengarang'=>'Adi Wijaya, M.Kom.','kategori'=>'Komputer & Pemrograman','tahun_terbit'=>'2023','rating'=>4.5,'total_baca'=>312],
    ];
}

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

$active_menu = "dashboard";

// ── SVG ICON HELPER ─────────────────────────────────────────────────────────
// Semua icon pakai SVG inline — tidak butuh CDN atau library eksternal apapun
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
        'eye'          => '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>',
        'pencil'       => '<path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>',
        'user-lock'    => '<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-1.5 0-4.5.75-5.99 2.25C7.18 17.38 9.32 18 12 18c.36 0 .71-.02 1.05-.05-.03-.3-.05-.6-.05-.95 0-1.87.85-3.54 2.18-4.67C14.08 12.11 12.63 14 12 14zm6 1c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3zm0 4.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>',
        'compass'      => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-5.5-2.5l7.51-3.49L17.5 6.5 9.99 9.99 6.5 17.5zm5.5-6.6c.61 0 1.1.49 1.1 1.1s-.49 1.1-1.1 1.1-1.1-.49-1.1-1.1.49-1.1 1.1-1.1z"/>',
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
    <title>Beranda — Pojok Baca</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/guest/dashboard.css">
    <style>
        /* SVG icon alignment */
        svg { vertical-align: middle; flex-shrink: 0; }
        .nav-link { display:flex; align-items:center; gap:10px; }
        .nav-link > span:first-child { display:inline-flex; align-items:center; width:20px; justify-content:center; flex-shrink:0; }
        .nav-link .lock { display:inline-flex; align-items:center; margin-left:auto; opacity:0.5; }
    </style>
</head>
<body>

<div class="overlay" id="overlay" onclick="closeSidebar()"></div>

<!-- ── SIDEBAR ── -->
<aside class="sidebar" id="sidebar">

    <!-- BRAND -->
    <div class="brand">
        <div class="brand-logo"><?= icon('book-open', 20) ?></div>
        <div>
            <div class="brand-name">Pojok Baca</div>
            <div class="brand-sub">Portal Guest</div>
        </div>
    </div>

    <!-- USER -->
    <div class="sidebar-user">
        <div class="user-ava">
            <?= strtoupper(substr($user['nama_lengkap'], 0, 1)) ?>
        </div>
        <div>
            <div class="user-name"><?= htmlspecialchars($user['nama_lengkap']) ?></div>
            <span class="user-badge <?= $isGuest ? 'guest' : 'member' ?>">
                <?= $isGuest ? icon('lock', 11).' Tamu' : icon('check-circle', 11).' Member' ?>
            </span>
        </div>
    </div>

    <!-- NAV -->
    <nav>

        <div class="nav-label">MAIN</div>
        <a href="dashboard.php" class="nav-link <?= ($active_menu == 'dashboard') ? 'active' : '' ?>">
            <span><?= icon('house', 16) ?></span> Beranda
        </a>

        <div class="nav-label">KOLEKSI</div>
        <a href="katalog.php" class="nav-link <?= ($active_menu == 'katalog') ? 'active' : '' ?>">
            <span><?= icon('book-open', 16) ?></span> Katalog eBook
        </a>
        <a href="kategori.php" class="nav-link <?= ($active_menu == 'kategori') ? 'active' : '' ?>">
            <span><?= icon('tag', 16) ?></span> Kategori
        </a>

        <?php if (!$isGuest): ?>
        <div class="nav-label">AKTIVITAS</div>
            <a href="koleksi.php" class="nav-link <?= ($active_menu == 'koleksi') ? 'active' : '' ?>">
                <span><?= icon('layers', 16) ?></span> Koleksi Saya
            </a>
            <a href="riwayat.php" class="nav-link <?= ($active_menu == 'riwayat') ? 'active' : '' ?>">
                <span><?= icon('history', 16) ?></span> Riwayat Baca
            </a>
            <a href="wishlist.php" class="nav-link <?= ($active_menu == 'wishlist') ? 'active' : '' ?>">
                <span><?= icon('star', 16) ?></span> Wishlist
            </a>
        <?php endif; ?>

        <?php if (!$isGuest): ?>
        <div class="nav-label">AKUN</div>
            <a href="profil.php" class="nav-link <?= ($active_menu == 'profil') ? 'active' : '' ?>">
                <span><?= icon('user', 16) ?></span> Profil Saya
            </a>
            <a href="notifikasi.php" class="nav-link <?= ($active_menu == 'notifikasi') ? 'active' : '' ?>">
                <span><?= icon('bell', 16) ?></span> Notifikasi
            </a>
        <?php endif; ?>

    </nav>

    <!-- FOOTER -->
    <div class="sidebar-footer">
        <?php if ($isGuest): ?>
            <a href="../auth/register.php" class="btn-logout register">
                <?= icon('user-plus', 16) ?> Daftar Sekarang
            </a>
        <?php else: ?>
            <a href="../auth/logout.php" class="btn-logout">
                <?= icon('sign-out', 16) ?> Keluar
            </a>
        <?php endif; ?>
    </div>

</aside>

<!-- ── MAIN ── -->
<div class="main">
    <!-- TOPBAR -->
    <div class="topbar">
        <div class="topbar-left">
            <button class="menu-btn" onclick="toggleSidebar()">
                <?= icon('bars', 20) ?>
            </button>
            <div>
                <div class="topbar-title">Beranda</div>
                <div class="topbar-breadcrumb">Pojok Baca / <span>Beranda</span></div>
            </div>
        </div>
        <div class="topbar-right">
            <div class="user-chip">
                <div class="chip-ava">
                    <?= strtoupper(substr($user['nama_lengkap'], 0, 1)) ?>
                </div>
                <div>
                    <div class="chip-name"><?= htmlspecialchars(explode(' ', $user['nama_lengkap'])[0]) ?></div>
                    <div class="chip-role"><?= $isGuest ? 'Tamu' : 'Member' ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- CONTENT -->
    <div class="content">

        <!-- HERO -->
        <div class="hero anim anim-d1">
            <div class="hero-text">
                <div class="hero-eyebrow">Pojok Baca Digital Library</div>
                <h2>
                    <?php if ($isGuest): ?>
                        Selamat Datang,<br><span>Jelajahi Koleksi Ebook</span>
                    <?php else: ?>
                        Selamat Datang Kembali,<br>
                        <span><?= htmlspecialchars(explode(' ', $user['nama_lengkap'])[0]) ?></span>
                    <?php endif; ?>
                </h2>
                <p>
                    <?php if ($isGuest): ?>
                        Temukan ribuan judul ebook berkualitas. Daftar gratis untuk membaca penuh dan menikmati semua fitur.
                    <?php else: ?>
                        Lanjutkan perjalanan membaca Anda. Koleksi ebook terbaru menanti untuk dieksplorasi.
                    <?php endif; ?>
                </p>
                <div class="hero-btns">
                    <a href="katalog.php" class="btn-primary">
                        <?= icon('book-open', 16) ?> Jelajahi Katalog
                    </a>
                    <?php if ($isGuest): ?>
                        <a href="../auth/register.php" class="btn-secondary">
                            <?= icon('user-plus', 16) ?> Daftar Gratis
                        </a>
                    <?php else: ?>
                        <a href="koleksi.php" class="btn-secondary">
                            <?= icon('bookmark', 16) ?> Koleksi Saya
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="hero-deco"><?= icon('book-open', 90, 'color:rgba(255,255,255,0.2)') ?></div>
        </div>

        <!-- STATS -->
        <div class="stats anim anim-d2">
            <div class="stat-card">
                <div class="stat-icon gold"><?= icon('book', 22) ?></div>
                <div>
                    <div class="stat-num"><?= number_format($totalEbook) ?></div>
                    <div class="stat-label">Total eBook</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon teal"><?= icon('tags', 22) ?></div>
                <div>
                    <div class="stat-num"><?= $totalKategori ?></div>
                    <div class="stat-label">Kategori</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><?= icon('fire', 22) ?></div>
                <div>
                    <div class="stat-num"><?= $totalBaru ?></div>
                    <div class="stat-label">Baru Tahun Ini</div>
                </div>
            </div>
        </div>

        <!-- GUEST BANNER -->
        <?php if ($isGuest): ?>
        <div class="guest-banner anim anim-d2">
            <div class="gb-left">
                <div class="gb-icon"><?= icon('lock-open', 28) ?></div>
                <div>
                    <strong>Buka Akses Penuh — Gratis!</strong>
                    <p>Daftar untuk membaca ebook PDF, simpan koleksi, dan dapatkan rekomendasi personal.</p>
                </div>
            </div>
            <a href="../auth/register.php" class="btn-primary">
                <?= icon('user-plus', 16) ?> Daftar Sekarang
            </a>
        </div>
        <?php endif; ?>

        <!-- EBOOK PILIHAN -->
        <div class="anim anim-d3">
            <div class="section-head">
                <h3><?= icon('fire', 18) ?> eBook Pilihan</h3>
                <a href="katalog.php" class="see-all">Lihat Semua <?= icon('arrow-right', 14) ?></a>
            </div>

            <div class="books-grid">
                <?php foreach ($featured as $i => $buku):
                    $c = $covers[$i % count($covers)];
                    $rating = number_format((float)($buku['rating'] ?? 0), 1);
                    $baca   = number_format((int)($buku['total_baca'] ?? 0));
                ?>
                <a href="katalog.php?search=<?= urlencode($buku['judul']) ?>" class="book-card">
                    <div class="book-cover" style="background:linear-gradient(160deg,<?= $c[0] ?>,<?= $c[1] ?>,<?= $c[2] ?>);">
                        <?php if (!empty($buku['cover_img'])): ?>
                            <img src="../<?= htmlspecialchars($buku['cover_img']) ?>"
                                 alt="<?= htmlspecialchars($buku['judul']) ?>"
                                 style="width:100%;height:100%;object-fit:cover;display:block;">
                        <?php else: ?>
                            <?= icon('book', 40, 'color:rgba(255,255,255,0.3)') ?>
                            <span style="font-size:2.7rem;"><?= htmlspecialchars($buku['cover_emoji'] ?? '📚') ?></span>
                        <?php endif; ?>
                        <div class="cover-rating"><?= icon('star', 11, 'color:#fbbf24') ?> <?= $rating ?></div>
                    </div>
                    <div class="book-meta">
                        <div class="book-genre"><?= htmlspecialchars($buku['kategori'] ?? '-') ?></div>
                        <div class="book-title"><?= htmlspecialchars($buku['judul']) ?></div>
                        <div class="book-author">
                            <?= icon('pencil', 12) ?>
                            <?= htmlspecialchars($buku['pengarang'] ?? '-') ?>
                        </div>
                        <div class="book-reads">
                            <?= icon('eye', 12) ?> <?= $baca ?> dibaca
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- QUICK ACTIONS -->
        <div class="anim anim-d4">
            <div class="section-head">
                <h3><?= icon('compass', 18) ?> Mulai Dari Sini</h3>
            </div>
            <div class="quick-actions">
                <a href="katalog.php" class="qa-btn"
                   style="background:rgba(201,168,76,.12);color:var(--accent2);border:1px solid rgba(201,168,76,.2);">
                    <?= icon('search', 15) ?> Cari eBook
                </a>
                <a href="katalog.php?kategori=Novel" class="qa-btn"
                   style="background:rgba(74,168,160,.12);color:var(--teal);border:1px solid rgba(74,168,160,.2);">
                    <?= icon('book-reader', 15) ?> Baca Novel
                </a>
                <?php if ($isGuest): ?>
                <a href="../auth/register.php" class="qa-btn"
                   style="background:rgba(224,92,92,.1);color:var(--red);border:1px solid rgba(224,92,92,.2);">
                    <?= icon('unlock', 15) ?> Daftar &amp; Baca PDF
                </a>
                <?php else: ?>
                <a href="koleksi.php" class="qa-btn"
                   style="background:rgba(224,92,92,.1);color:var(--red);border:1px solid rgba(224,92,92,.2);">
                    <?= icon('bookmark', 15) ?> Koleksi Saya
                </a>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /main -->

<!-- TOAST -->
<div class="toast" id="toast">
    <?= icon('user-lock', 18, 'color:var(--accent)') ?>
    <span>Fitur ini membutuhkan akun.
        <a href="../auth/register.php">Daftar</a> atau
        <a href="../index.php">Login</a>.
    </span>
</div>

<script>
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

    let toastTimer;
    function showToast(e) {
        e && e.preventDefault();
        const t = document.getElementById('toast');
        t.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => t.classList.remove('show'), 4000);
    }
</script>
</body>
</html>