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

// -- Ambil kategori + jumlah buku dari DB -------------------------------------
$kategoris = [];
if ($dbOk) {
    $sql = "SELECT kategori, COUNT(*) AS jumlah
            FROM buku
            WHERE kategori IS NOT NULL AND kategori != ''
            GROUP BY kategori
            ORDER BY jumlah DESC";
    $r = mysqli_query($conn, $sql);
    if ($r) {
        while ($row = mysqli_fetch_assoc($r)) {
            $kategoris[] = $row;
        }
    }
}

// -- Filter pencarian ----------------------------------------------------------
$searchKat = trim($_GET['search'] ?? '');
if ($searchKat !== '') {
    $kategoris = array_values(array_filter($kategoris, function($k) use ($searchKat) {
        return stripos($k['kategori'], $searchKat) !== false;
    }));
}

// -- Fallback data dummy -------------------------------------------------------
if (empty($kategoris)) {
    $dummy = [
        ['kategori' => 'Novel',                   'jumlah' => 48],
        ['kategori' => 'Motivasi & Inspirasi',     'jumlah' => 35],
        ['kategori' => 'Bisnis & Ekonomi',         'jumlah' => 29],
        ['kategori' => 'Sejarah & Budaya',         'jumlah' => 24],
        ['kategori' => 'Komputer & Pemrograman',   'jumlah' => 22],
        ['kategori' => 'Sains & Teknologi',        'jumlah' => 18],
        ['kategori' => 'Pendidikan',               'jumlah' => 17],
        ['kategori' => 'Agama & Spiritual',        'jumlah' => 15],
        ['kategori' => 'Anak & Remaja',            'jumlah' => 14],
        ['kategori' => 'Kesehatan & Gaya Hidup',   'jumlah' => 13],
        ['kategori' => 'Filsafat',                 'jumlah' => 10],
        ['kategori' => 'Hukum & Politik',          'jumlah' => 9],
        ['kategori' => 'Komik & Manga',            'jumlah' => 8],
        ['kategori' => 'Bahasa & Sastra',          'jumlah' => 7],
        ['kategori' => 'Kamus & Referensi',        'jumlah' => 5],
        ['kategori' => 'Seni & Desain',            'jumlah' => 4],
    ];
    if ($searchKat !== '') {
        $dummy = array_values(array_filter($dummy, function($k) use ($searchKat) {
            return stripos($k['kategori'], $searchKat) !== false;
        }));
    }
    $kategoris = $dummy;
}

$totalKategori = count($kategoris);
$totalBuku     = array_sum(array_column($kategoris, 'jumlah'));

$active_menu = "kategori";

// -- SVG ICON HELPER -----------------------------------------------------------
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
        'grid'         => '<path d="M3 3h8v8H3V3zm0 10h8v8H3v-8zm10-10h8v8h-8V3zm0 10h8v8h-8v-8z"/>',
        'list'         => '<path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/>',
        'x'            => '<path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>',
    ];
    $path = $icons[$name] ?? '<circle cx="12" cy="12" r="10"/>';
    return "<svg xmlns='http://www.w3.org/2000/svg' width='{$size}' height='{$size}' viewBox='0 0 24 24' fill='currentColor'{$s}>{$path}</svg>";
}

// -- Peta ikon & warna per kategori -------------------------------------------
$katMeta = [
    'Novel'                  => ['icon' => 'book-reader', 'color' => '#7b2d8b', 'bg' => 'rgba(123,45,139,.15)'],
    'Motivasi & Inspirasi'   => ['icon' => 'fire',        'color' => '#e05c5c', 'bg' => 'rgba(224,92,92,.13)'],
    'Bisnis & Ekonomi'       => ['icon' => 'bookmark',    'color' => '#c9a84c', 'bg' => 'rgba(201,168,76,.13)'],
    'Sejarah & Budaya'       => ['icon' => 'compass',     'color' => '#4aa8a0', 'bg' => 'rgba(74,168,160,.13)'],
    'Komputer & Pemrograman' => ['icon' => 'layers',      'color' => '#1e88e5', 'bg' => 'rgba(30,136,229,.13)'],
    'Sains & Teknologi'      => ['icon' => 'star',        'color' => '#4caf50', 'bg' => 'rgba(76,175,80,.13)'],
    'Pendidikan'             => ['icon' => 'book-open',   'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,.13)'],
    'Agama & Spiritual'      => ['icon' => 'compass',     'color' => '#8b6ddb', 'bg' => 'rgba(139,109,219,.13)'],
    'Anak & Remaja'          => ['icon' => 'star',        'color' => '#e05c5c', 'bg' => 'rgba(224,92,92,.10)'],
    'Kesehatan & Gaya Hidup' => ['icon' => 'check-circle','color' => '#4caf50', 'bg' => 'rgba(76,175,80,.13)'],
    'Filsafat'               => ['icon' => 'book',        'color' => '#94a3b8', 'bg' => 'rgba(148,163,184,.12)'],
    'Hukum & Politik'        => ['icon' => 'lock-open',   'color' => '#1e88e5', 'bg' => 'rgba(30,136,229,.12)'],
    'Komik & Manga'          => ['icon' => 'pencil',      'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,.12)'],
    'Bahasa & Sastra'        => '<default>',
    'Kamus & Referensi'      => '<default>',
    'Seni & Desain'          => '<default>',
];
$defaultMeta  = ['icon' => 'tag', 'color' => '#4aa8a0', 'bg' => 'rgba(74,168,160,.13)'];
$colorPalette = [
    ['color'=>'#7b2d8b','bg'=>'rgba(123,45,139,.15)'],
    ['color'=>'#e05c5c','bg'=>'rgba(224,92,92,.13)'],
    ['color'=>'#c9a84c','bg'=>'rgba(201,168,76,.13)'],
    ['color'=>'#4aa8a0','bg'=>'rgba(74,168,160,.13)'],
    ['color'=>'#1e88e5','bg'=>'rgba(30,136,229,.13)'],
    ['color'=>'#4caf50','bg'=>'rgba(76,175,80,.13)'],
    ['color'=>'#f59e0b','bg'=>'rgba(245,158,11,.13)'],
    ['color'=>'#8b6ddb','bg'=>'rgba(139,109,219,.13)'],
];
function getKatMeta($nama, $katMeta, $defaultMeta, $colorPalette, $idx) {
    $m = $katMeta[$nama] ?? null;
    if (!$m || $m === '<default>') {
        $c = $colorPalette[$idx % count($colorPalette)];
        return ['icon' => 'tag', 'color' => $c['color'], 'bg' => $c['bg']];
    }
    return $m;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategori – Pojok Baca</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/anggota/sidebar.css">
    <link rel="stylesheet" href="../assets/css/anggota/kategori.css">
</head>
<body>

<?php require_once '../includes/anggota/sidebar.php'; ?>

<!-- -- MAIN -- -->
<div class="main">

    <!-- TOPBAR -->
    <div class="topbar">
        <div class="topbar-left">
            <button class="menu-btn" onclick="toggleSidebar()">
                <?= icon('bars', 20) ?>
            </button>
            <div>
                <div class="topbar-title">Kategori</div>
                <div class="topbar-breadcrumb">Pojok Baca / <span>Kategori</span></div>
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
                <div class="hero-eyebrow">
                    <?= icon('tag', 13) ?> Koleksi Pojok Baca
                </div>
                <h2>Jelajahi <span>Semua Kategori</span></h2>
                <p>Temukan buku favorit Anda berdasarkan genre atau topik yang paling Anda minati.</p>
            </div>
            <div class="hero-deco"><?= icon('tags', 90, 'color:rgba(255,255,255,0.18)') ?></div>
        </div>

        <!-- STATS -->
        <div class="stats anim anim-d2">
            <div class="stat-card">
                <div class="stat-icon teal"><?= icon('tags', 22) ?></div>
                <div>
                    <div class="stat-num"><?= $totalKategori ?></div>
                    <div class="stat-label">Total Kategori</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon gold"><?= icon('book', 22) ?></div>
                <div>
                    <div class="stat-num"><?= number_format($totalBuku) ?></div>
                    <div class="stat-label">Total Buku</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><?= icon('fire', 22) ?></div>
                <div>
                    <div class="stat-num"><?= $kategoris[0]['jumlah'] ?? 0 ?></div>
                    <div class="stat-label">Kategori Terpopuler</div>
                </div>
            </div>
        </div>

        <!-- TOOLBAR -->
        <div class="anim anim-d3">
            <div class="toolbar">
                <!-- Search -->
                <div class="search-box">
                    <?= icon('search', 16) ?>
                    <input
                        type="text"
                        id="searchInput"
                        placeholder="Cari kategori..."
                        value="<?= htmlspecialchars($searchKat) ?>"
                        oninput="filterCards(this.value)"
                        autocomplete="off"
                    >
                    <button class="btn-clear <?= $searchKat ? 'show' : '' ?>" id="clearBtn" onclick="clearSearch()">
                        <?= icon('x', 14) ?>
                    </button>
                </div>

                <!-- Sort -->
                <select class="filter-select" onchange="sortCards(this.value)">
                    <option value="jumlah_desc">Terbanyak</option>
                    <option value="jumlah_asc">Tersedikit</option>
                    <option value="nama_asc">Nama A-Z</option>
                    <option value="nama_desc">Nama Z-A</option>
                </select>

                <!-- View Toggle -->
                <div class="view-toggle">
                    <button class="view-btn active" id="btnGrid" onclick="setView('grid')" title="Grid">
                        <?= icon('grid', 16) ?>
                    </button>
                    <button class="view-btn" id="btnList" onclick="setView('list')" title="List">
                        <?= icon('list', 16) ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- SECTION HEAD -->
        <div class="section-head anim anim-d4">
            <h3>
                <?= icon('tag', 16) ?> Semua Kategori
            </h3>
            <span class="count-badge" id="countBadge"><?= $totalKategori ?> kategori</span>
        </div>

        <!-- GRID VIEW -->
        <div class="kat-grid anim anim-d4" id="katGrid">
            <?php foreach ($kategoris as $i => $k):
                $m   = getKatMeta($k['kategori'], $katMeta, $defaultMeta, $colorPalette, $i);
                $href = 'katalog_ebook.php?kategori=' . urlencode($k['kategori']);
            ?>
            <a href="<?= $href ?>"
               class="kat-card"
               data-name="<?= htmlspecialchars(strtolower($k['kategori'])) ?>"
               data-jumlah="<?= (int)$k['jumlah'] ?>"
               style="--kat-color:<?= $m['color'] ?>;--kat-bg:<?= $m['bg'] ?>;">
                <div class="kat-icon-wrap">
                    <?= icon($m['icon'], 22) ?>
                </div>
                <div class="kat-name"><?= htmlspecialchars($k['kategori']) ?></div>
                <div class="kat-count">
                    <?= icon('book', 12) ?>
                    <?= number_format((int)$k['jumlah']) ?> buku tersedia
                </div>
                <div class="kat-arrow"><?= icon('arrow-right', 16) ?></div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- LIST VIEW -->
        <?php
        $maxJumlah = max(array_column($kategoris, 'jumlah') ?: [1]);
        ?>
        <div class="kat-list" id="katList" style="display:none;">
            <?php foreach ($kategoris as $i => $k):
                $m      = getKatMeta($k['kategori'], $katMeta, $defaultMeta, $colorPalette, $i);
                $pct    = $maxJumlah > 0 ? round(($k['jumlah'] / $maxJumlah) * 100) : 0;
                $pctAll = $totalBuku > 0 ? round(($k['jumlah'] / $totalBuku) * 100) : 0;
                $href   = 'katalog_ebook.php?kategori=' . urlencode($k['kategori']);
            ?>
            <a href="<?= $href ?>"
               class="kat-row"
               data-name="<?= htmlspecialchars(strtolower($k['kategori'])) ?>"
               data-jumlah="<?= (int)$k['jumlah'] ?>"
               style="--kat-color:<?= $m['color'] ?>;--kat-bg:<?= $m['bg'] ?>;">
                <div class="kat-icon-wrap">
                    <?= icon($m['icon'], 18) ?>
                </div>
                <div class="kat-row-info">
                    <div class="kat-row-name"><?= htmlspecialchars($k['kategori']) ?></div>
                    <div class="kat-row-count"><?= number_format((int)$k['jumlah']) ?> buku tersedia</div>
                </div>
                <div class="kat-row-bar-wrap">
                    <div class="kat-row-bar">
                        <div class="kat-row-fill" style="width:<?= $pct ?>%;"></div>
                    </div>
                    <div class="kat-row-pct"><?= $pctAll ?>% koleksi</div>
                </div>
                <div class="kat-row-arrow"><?= icon('arrow-right', 16) ?></div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- EMPTY STATE -->
        <div class="empty-state" id="emptyState" style="display:none;">
            <?= icon('search', 56, 'display:block;margin:0 auto 16px;opacity:0.15') ?>
            <h4>Kategori Tidak Ditemukan</h4>
            <p>Tidak ada kategori yang cocok dengan "<span id="emptyQuery"></span>".</p>
        </div>

    </div><!-- /content -->
</div><!-- /main -->

<!-- TOAST -->
<div class="toast" id="toast">
    <?= icon('user-lock', 18, 'color:#6366f1') ?>
    <span>Fitur ini membutuhkan akun.
        <a href="../auth/register.php">Daftar</a> atau
        <a href="../index.php">Login</a>.
    </span>
</div>

<script>
    /* -- Sidebar – pakai .open / .show sesuai katalog_ebook -- */
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('overlay').classList.toggle('show');
    }
    function closeSidebar() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('overlay').classList.remove('show');
    }

    /* -- Toast -- */
    let toastTimer;
    function showToast(e) {
        e && e.preventDefault();
        const t = document.getElementById('toast');
        t.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => t.classList.remove('show'), 4000);
    }

    /* -- View Mode -- */
    let currentView = 'grid';
    function setView(v) {
        currentView = v;
        document.getElementById('katGrid').style.display = v === 'grid' ? 'grid' : 'none';
        document.getElementById('katList').style.display = v === 'list' ? 'flex' : 'none';
        document.getElementById('btnGrid').classList.toggle('active', v === 'grid');
        document.getElementById('btnList').classList.toggle('active', v === 'list');
        localStorage.setItem('katView', v);
    }
    const savedView = localStorage.getItem('katView') || 'grid';
    setView(savedView);

    /* -- Search / Filter -- */
    function filterCards(q) {
        q = q.toLowerCase().trim();
        const gridCards = document.querySelectorAll('#katGrid .kat-card');
        const listRows  = document.querySelectorAll('#katList .kat-row');
        let visible = 0;

        gridCards.forEach(c => {
            const match = c.dataset.name.includes(q);
            c.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        listRows.forEach(r => {
            r.style.display = r.dataset.name.includes(q) ? '' : 'none';
        });

        document.getElementById('countBadge').textContent = visible + ' kategori';
        document.getElementById('clearBtn').classList.toggle('show', q.length > 0);

        const empty = document.getElementById('emptyState');
        empty.style.display = visible === 0 ? 'block' : 'none';
        document.getElementById('emptyQuery').textContent = q;
    }

    function clearSearch() {
        const inp = document.getElementById('searchInput');
        inp.value = '';
        filterCards('');
        inp.focus();
    }

    /* -- Sort -- */
    function sortCards(mode) {
        const gridParent = document.getElementById('katGrid');
        const listParent = document.getElementById('katList');

        const gridCards = [...gridParent.querySelectorAll('.kat-card')];
        const listRows  = [...listParent.querySelectorAll('.kat-row')];

        function compare(a, b) {
            const aJ = parseInt(a.dataset.jumlah), bJ = parseInt(b.dataset.jumlah);
            const aN = a.dataset.name,              bN = b.dataset.name;
            if (mode === 'jumlah_desc') return bJ - aJ;
            if (mode === 'jumlah_asc')  return aJ - bJ;
            if (mode === 'nama_asc')    return aN.localeCompare(bN);
            if (mode === 'nama_desc')   return bN.localeCompare(aN);
            return 0;
        }

        gridCards.sort(compare).forEach(c => gridParent.appendChild(c));
        listRows.sort(compare).forEach(r => listParent.appendChild(r));
    }
</script>

</body>
</html>