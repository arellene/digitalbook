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

// ── Ambil semua kategori beserta jumlah buku ──────────────────────────────
$kategori_data = [];
if ($dbOk) {
    $res = mysqli_query($conn,
        "SELECT kategori,
                COUNT(*) AS total,
                SUM(CASE WHEN file_pdf IS NOT NULL AND file_pdf != '' THEN 1 ELSE 0 END) AS bisa_dibaca
         FROM buku
         WHERE kategori IS NOT NULL AND kategori != ''
         GROUP BY kategori
         ORDER BY total DESC"
    );
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) $kategori_data[] = $row;
    }
}

// Fallback data demo
if (empty($kategori_data)) {
    $kategori_data = [
        ['kategori' => 'Novel',                   'total' => 34, 'bisa_dibaca' => 28],
        ['kategori' => 'Motivasi & Inspirasi',    'total' => 21, 'bisa_dibaca' => 18],
        ['kategori' => 'Komputer & Pemrograman',  'total' => 19, 'bisa_dibaca' => 19],
        ['kategori' => 'Bisnis & Ekonomi',        'total' => 17, 'bisa_dibaca' => 12],
        ['kategori' => 'Sejarah & Budaya',        'total' => 14, 'bisa_dibaca' => 10],
        ['kategori' => 'Sains & Teknologi',       'total' => 11, 'bisa_dibaca' => 9],
        ['kategori' => 'Agama & Spiritualitas',   'total' => 9,  'bisa_dibaca' => 7],
        ['kategori' => 'Pendidikan',              'total' => 8,  'bisa_dibaca' => 6],
        ['kategori' => 'Kesehatan',               'total' => 7,  'bisa_dibaca' => 5],
        ['kategori' => 'Anak & Remaja',           'total' => 6,  'bisa_dibaca' => 4],
        ['kategori' => 'Filsafat',                'total' => 5,  'bisa_dibaca' => 4],
        ['kategori' => 'Seni & Budaya',           'total' => 4,  'bisa_dibaca' => 3],
    ];
}

$totalBuku     = array_sum(array_column($kategori_data, 'total'));
$totalKategori = count($kategori_data);
$totalBisaBaca = array_sum(array_column($kategori_data, 'bisa_dibaca'));

$active_menu = "kategori";

// ── Icon palette per kategori ─────────────────────────────────────────────
$kat_icons = [
    'Novel'                  => ['icon' => 'book',      'color1' => '#1a1a2e', 'color2' => '#0f3460', 'accent' => '#6c8aff'],
    'Motivasi & Inspirasi'   => ['icon' => 'fire',      'color1' => '#2c1810', 'color2' => '#5c2d0e', 'accent' => '#ff9f43'],
    'Komputer & Pemrograman' => ['icon' => 'code',      'color1' => '#0d2137', 'color2' => '#0a3d62', 'accent' => '#4aa8a0'],
    'Bisnis & Ekonomi'       => ['icon' => 'trending',  'color1' => '#1b2838', 'color2' => '#1b8a4f', 'accent' => '#2ecc71'],
    'Sejarah & Budaya'       => ['icon' => 'landmark',  'color1' => '#2d2416', 'color2' => '#5c4d2c', 'accent' => '#c9a84c'],
    'Sains & Teknologi'      => ['icon' => 'science',   'color1' => '#1a2332', 'color2' => '#233554', 'accent' => '#4a90a4'],
    'Agama & Spiritualitas'  => ['icon' => 'star',      'color1' => '#2d1b33', 'color2' => '#4a1942', 'accent' => '#a78bfa'],
    'Pendidikan'             => ['icon' => 'education', 'color1' => '#0d2137', 'color2' => '#1b4f72', 'accent' => '#3498db'],
    'Kesehatan'              => ['icon' => 'health',    'color1' => '#1b2838', 'color2' => '#145a32', 'accent' => '#27ae60'],
    'Anak & Remaja'          => ['icon' => 'smile',     'color1' => '#2c1810', 'color2' => '#922b21', 'accent' => '#e05c5c'],
    'Filsafat'               => ['icon' => 'compass',   'color1' => '#1f2b1f', 'color2' => '#2d4a2d', 'accent' => '#4caf50'],
    'Seni & Budaya'          => ['icon' => 'palette',   'color1' => '#2d1b33', 'color2' => '#7b2d8b', 'accent' => '#e91e63'],
];
$default_palette = ['color1' => '#1e2535', 'color2' => '#12172a', 'accent' => '#6c8aff'];

// ── SVG ICON HELPER ───────────────────────────────────────────────────────
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
        'user-lock'    => '<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-1.5 0-4.5.75-5.99 2.25C7.18 17.38 9.32 18 12 18c.36 0 .71-.02 1.05-.05-.03-.3-.05-.6-.05-.95 0-1.87.85-3.54 2.18-4.67C14.08 12.11 12.63 14 12 14zm6 1c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3zm0 4.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>',
        'compass'      => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-5.5-2.5l7.51-3.49L17.5 6.5 9.99 9.99 6.5 17.5zm5.5-6.6c.61 0 1.1.49 1.1 1.1s-.49 1.1-1.1 1.1-1.1-.49-1.1-1.1.49-1.1 1.1-1.1z"/>',
        /* kategori-specific icons */
        'code'         => '<path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/>',
        'trending'     => '<path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/>',
        'landmark'     => '<path d="M12 3L2 9v2h20V9L12 3zm0 2.5L18.5 9h-13L12 5.5zM4 11v7H2v2h20v-2h-2v-7h-2v7h-4v-7h-2v7H8v-7H4z"/>',
        'science'      => '<path d="M9 3v9.54C7.76 13.24 7 14.52 7 16c0 2.76 2.24 5 5 5s5-2.24 5-5c0-1.48-.76-2.76-2-3.46V3H9zm3 16c-1.65 0-3-1.35-3-3s1.35-3 3-3 3 1.35 3 3-1.35 3-3 3zm1-9h-2V5h2v5z"/>',
        'education'    => '<path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3zM5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/>',
        'health'       => '<path d="M12 21.593c-5.63-5.539-11-10.297-11-14.402 0-3.791 3.068-5.191 5.281-5.191 1.312 0 4.151.501 5.719 4.457 1.59-3.968 4.464-4.447 5.726-4.447 2.54 0 5.274 1.621 5.274 5.181 0 4.069-5.136 8.625-11 14.402z"/>',
        'smile'        => '<path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"/>',
        'palette'      => '<path d="M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9c.83 0 1.5-.67 1.5-1.5 0-.39-.15-.74-.39-1.01-.23-.26-.38-.61-.38-.99 0-.83.67-1.5 1.5-1.5H16c2.76 0 5-2.24 5-5 0-4.42-4.03-8-9-8zm-5.5 9c-.83 0-1.5-.67-1.5-1.5S5.67 9 6.5 9 8 9.67 8 10.5 7.33 12 6.5 12zm3-4C8.67 8 8 7.33 8 6.5S8.67 5 9.5 5s1.5.67 1.5 1.5S10.33 8 9.5 8zm5 0c-.83 0-1.5-.67-1.5-1.5S13.67 5 14.5 5s1.5.67 1.5 1.5S15.33 8 14.5 8zm3 4c-.83 0-1.5-.67-1.5-1.5S16.67 9 17.5 9s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>',
    ];
    $path = $icons[$name] ?? '<circle cx="12" cy="12" r="10"/>';
    return "<svg xmlns='http://www.w3.org/2000/svg' width='{$size}' height='{$size}' viewBox='0 0 24 24' fill='currentColor'{$s}>{$path}</svg>";
}

function getKatInfo($name, $map, $default) {
    foreach ($map as $key => $val) {
        if (stripos($name, $key) !== false || stripos($key, $name) !== false) return $val;
    }
    // exact match first
    return $map[$name] ?? $default;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategori — Pojok Baca</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/guest/kategori.css">
    <style>
        svg { vertical-align: middle; flex-shrink: 0; }
    </style>
</head>
<body>

<!-- ── NAVBAR ATAS (pengganti sidebar) ── -->
<?php
$topbar_title      = 'Kategori';
$topbar_breadcrumb = 'Kategori';
include __DIR__ . '/../includes/guest/topnav.php';
?>

<!-- ── MAIN ── -->
<div class="main">

    <!-- CONTENT -->
    <div class="content">

        <!-- HERO -->
        <div class="hero anim anim-d1">
            <div class="hero-text">
                <div class="hero-eyebrow">Pojok Baca Digital Library</div>
                <h2>Jelajahi <span>Kategori</span></h2>
                <p>Temukan buku favoritmu berdasarkan kategori yang tersedia di perpustakaan digital kami.</p>
                <div class="hero-stats-row">
                    <div class="hero-stat-item">
                        <span class="hero-stat-num"><?= $totalKategori ?></span>
                        <span class="hero-stat-lbl">Kategori</span>
                    </div>
                    <div class="hero-stat-item">
                        <span class="hero-stat-num"><?= number_format($totalBuku) ?></span>
                        <span class="hero-stat-lbl">Total Buku</span>
                    </div>
                    <div class="hero-stat-item">
                        <span class="hero-stat-num"><?= number_format($totalBisaBaca) ?></span>
                        <span class="hero-stat-lbl">Bisa Dibaca</span>
                    </div>
                </div>
            </div>
            <div class="hero-deco"><?= icon('tags', 90, 'color:rgba(255,255,255,0.2)') ?></div>
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

        <!-- SECTION HEAD -->
        <div class="section-head anim anim-d2">
            <h3><?= icon('tag', 18) ?> Semua Kategori</h3>
            <a href="katalog.php" class="see-all">Lihat Semua Buku <?= icon('arrow-right', 14) ?></a>
        </div>

        <!-- KATEGORI GRID -->
        <div class="kategori-grid anim anim-d3">
            <?php foreach ($kategori_data as $i => $kat):
                $info = $kat_icons[$kat['kategori']] ?? $default_palette;
                $iconName = $info['icon'] ?? 'book';
                $pct = $kat['total'] > 0 ? round(($kat['bisa_dibaca'] / $kat['total']) * 100) : 0;
            ?>
            <a href="katalog.php?kategori=<?= urlencode($kat['kategori']) ?>" class="kat-card">
                <div class="kat-cover" style="background:linear-gradient(145deg,<?= $info['color1'] ?>,<?= $info['color2'] ?>);">
                    <div class="kat-icon-wrap" style="color:<?= $info['accent'] ?>;background:<?= $info['accent'] ?>1a;">
                        <?= icon($iconName, 28) ?>
                    </div>
                    <div class="kat-badge"><?= $kat['total'] ?> buku</div>
                </div>
                <div class="kat-body">
                    <div class="kat-name"><?= htmlspecialchars($kat['kategori']) ?></div>
                    <div class="kat-sub">
                        <?= icon('book-reader', 12) ?> <?= $kat['bisa_dibaca'] ?> bisa dibaca
                    </div>
                    <div class="kat-progress">
                        <div class="kat-bar">
                            <div class="kat-fill" style="width:<?= $pct ?>%;background:<?= $info['accent'] ?>;"></div>
                        </div>
                        <span class="kat-pct"><?= $pct ?>%</span>
                    </div>
                </div>
                <div class="kat-arrow"><?= icon('arrow-right', 16) ?></div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- QUICK ACTIONS -->
        <div class="anim anim-d4">
            <div class="section-head">
                <h3><?= icon('compass', 18) ?> Mulai Dari Sini</h3>
            </div>
            <div class="quick-actions">
                <a href="katalog.php" class="qa-btn"
                   style="background:rgba(108,138,255,.12);color:var(--accent);border:1px solid rgba(108,138,255,.2);">
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