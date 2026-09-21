<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$isGuest = ($_SESSION['role'] === 'guest');

$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (empty($user)) {
    $user = [
        'nama_lengkap' => $_SESSION['nama_lengkap'] ?? ($isGuest ? 'Tamu' : 'Pengguna'),
        'username'     => $_SESSION['username']     ?? ($isGuest ? 'guest' : 'user'),
        'role'         => $_SESSION['role']         ?? 'anggota',
    ];
}

define('BASE_URL', '../');

$search     = trim($_GET['search']   ?? '');
$filter_kat = trim($_GET['kategori'] ?? '');

$where  = "WHERE 1=1";
$params = [];
$types  = '';

if ($search !== '') {
    $like   = "%$search%";
    $where .= " AND (judul LIKE ? OR penulis LIKE ? OR pengarang LIKE ? OR isbn LIKE ?)";
    $params = array_merge($params, [$like, $like, $like, $like]);
    $types .= 'ssss';
}
if ($filter_kat !== '') {
    $where .= " AND kategori = ?";
    $params[] = $filter_kat;
    $types   .= 's';
}

$per_page = 12;
$page     = max(1, (int) ($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$stmt_cnt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM buku $where");
if ($types) mysqli_stmt_bind_param($stmt_cnt, $types, ...$params);
mysqli_stmt_execute($stmt_cnt);
$total_buku = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_cnt))['total'];
mysqli_stmt_close($stmt_cnt);
$total_page = max(1, ceil($total_buku / $per_page));

$stmt_list = mysqli_prepare($conn,
    "SELECT id, judul,
            COALESCE(pengarang, penulis, '—') AS pengarang,
            penerbit, tahun, kategori, isbn,
            deskripsi, cover_emoji, cover_img,
            file_pdf, halaman, bahasa, rating, total_baca
     FROM buku $where
     ORDER BY created_at DESC
     LIMIT ? OFFSET ?"
);
$limit_params = array_merge($params, [$per_page, $offset]);
$limit_types  = $types . 'ii';
mysqli_stmt_bind_param($stmt_list, $limit_types, ...$limit_params);
mysqli_stmt_execute($stmt_list);
$buku_list = mysqli_stmt_get_result($stmt_list);
$buku_arr  = [];
while ($row = mysqli_fetch_assoc($buku_list)) $buku_arr[] = $row;

$stat_total    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as n FROM buku"))['n'];
$stat_kategori = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT kategori) as n FROM buku WHERE kategori IS NOT NULL AND kategori != ''"))['n'];
$stat_file     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as n FROM buku WHERE file_pdf IS NOT NULL AND file_pdf != ''"))['n'];

$kategori_list = [];
$res_kat = mysqli_query($conn, "SELECT DISTINCT kategori FROM buku WHERE kategori IS NOT NULL AND kategori != '' ORDER BY kategori ASC");
while ($r = mysqli_fetch_assoc($res_kat)) $kategori_list[] = $r['kategori'];

$base_url = "katalog.php?" . http_build_query(array_filter([
    'search'   => $search,
    'kategori' => $filter_kat,
])) . "&page=";

$active_menu = "katalog";

// ── SVG ICON HELPER (sama persis dengan dashboard) ──────────────────────────
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
        'search'       => '<path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>',
        'filter'       => '<path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/>',
        'times'        => '<path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>',
        'book-reader'  => '<path d="M21 5c-1.11-.35-2.33-.5-3.5-.5-1.95 0-4.05.4-5.5 1.5-1.45-1.1-3.55-1.5-5.5-1.5S2.45 4.9 1 6v14.65c0 .25.25.5.5.5.1 0 .15-.05.25-.05C3.1 20.45 5.05 20 6.5 20c1.95 0 4.05.4 5.5 1.5 1.35-.85 3.8-1.5 5.5-1.5 1.65 0 3.35.3 4.75 1.05.1.05.15.05.25.05.25 0 .5-.25.5-.5V6c-.6-.45-1.25-.75-2-1zm0 13.5c-1.1-.35-2.3-.5-3.5-.5-1.7 0-4.15.65-5.5 1.5V8c1.35-.85 3.8-1.5 5.5-1.5 1.2 0 2.4.15 3.5.5v11.5z"/>',
        'unlock'       => '<path d="M12 1C8.676 1 6 3.676 6 7h2c0-2.206 1.794-4 4-4s4 1.794 4 4v3H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V12c0-1.1-.9-2-2-2h-2V7c0-3.324-2.676-6-6-6zm0 13c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2z"/>',
        'bookmark'     => '<path d="M17 3H7c-1.1 0-1.99.9-1.99 2L5 21l7-3 7 3V5c0-1.1-.9-2-2-2z"/>',
        'pencil'       => '<path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>',
        'eye'          => '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>',
        'user-lock'    => '<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-1.5 0-4.5.75-5.99 2.25C7.18 17.38 9.32 18 12 18c.36 0 .71-.02 1.05-.05-.03-.3-.05-.6-.05-.95 0-1.87.85-3.54 2.18-4.67C14.08 12.11 12.63 14 12 14zm6 1c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3zm0 4.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>',
        'info-circle'  => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>',
        'chevron-left' => '<path d="M15.41 16.59L10.83 12l4.58-4.59L14 6l-6 6 6 6z"/>',
        'chevron-right'=> '<path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6z"/>',
        'building'     => '<path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/>',
        'calendar'     => '<path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/>',
        'barcode'      => '<path d="M1 6h2v12H1zm4 0h1v12H5zm2 0h3v12H7zm4 0h1v12h-1zm3 0h2v12h-2zm3 0h1v12h-1zm2 0h2v12h-2z"/>',
        'file-alt'     => '<path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>',
        'language'     => '<path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zm6.93 6h-2.95c-.32-1.25-.78-2.45-1.38-3.56 1.84.63 3.37 1.9 4.33 3.56zM12 4.04c.83 1.2 1.48 2.53 1.91 3.96h-3.82c.43-1.43 1.08-2.76 1.91-3.96zM4.26 14C4.1 13.36 4 12.69 4 12s.1-1.36.26-2h3.38c-.08.66-.14 1.32-.14 2s.06 1.34.14 2H4.26zm.82 2h2.95c.32 1.25.78 2.45 1.38 3.56-1.84-.63-3.37-1.9-4.33-3.56zm2.95-8H5.08c.96-1.66 2.49-2.93 4.33-3.56C8.81 5.55 8.35 6.75 8.03 8zM12 19.96c-.83-1.2-1.48-2.53-1.91-3.96h3.82c-.43 1.43-1.08 2.76-1.91 3.96zM14.34 14H9.66c-.09-.66-.16-1.32-.16-2s.07-1.35.16-2h4.68c.09.65.16 1.32.16 2s-.07 1.34-.16 2zm.25 5.56c.6-1.11 1.06-2.31 1.38-3.56h2.95c-.96 1.65-2.49 2.93-4.33 3.56zM16.36 14c.08-.66.14-1.32.14-2s-.06-1.34-.14-2h3.38c.16.64.26 1.31.26 2s-.1 1.36-.26 2h-3.38z"/>',
        'align-left'   => '<path d="M15 15H3v2h12v-2zm0-8H3v2h12V7zM3 13h18v-2H3v2zm0 8h18v-2H3v2zM3 3v2h18V3H3z"/>',
        'compass'      => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-5.5-2.5l7.51-3.49L17.5 6.5 9.99 9.99 6.5 17.5zm5.5-6.6c.61 0 1.1.49 1.1 1.1s-.49 1.1-1.1 1.1-1.1-.49-1.1-1.1.49-1.1 1.1-1.1z"/>',
        'arrow-right'  => '<path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6z"/>',
    ];
    $path = $icons[$name] ?? '<circle cx="12" cy="12" r="10"/>';
    return "<svg xmlns='http://www.w3.org/2000/svg' width='{$size}' height='{$size}' viewBox='0 0 24 24' fill='currentColor'{$s}>{$path}</svg>";
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Katalog eBook — Pojok Baca</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/guest/katalog.css">
    <style>
        svg { vertical-align: middle; flex-shrink: 0; }
    </style>
</head>
<body>

<!-- ── NAVBAR ATAS (pengganti sidebar) ── -->
<?php
$topbar_title      = 'Katalog eBook';
$topbar_breadcrumb = 'Katalog';
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
                <h2>Katalog <span>eBook Digital</span></h2>
                <p>Temukan dan baca koleksi buku pilihan dari perpustakaan digital kami.</p>
                <div class="hero-stats-row">
                    <div class="hero-stat-item">
                        <span class="hero-stat-num"><?= $stat_total ?></span>
                        <span class="hero-stat-lbl">Judul Buku</span>
                    </div>
                    <div class="hero-stat-item">
                        <span class="hero-stat-num"><?= $stat_file ?></span>
                        <span class="hero-stat-lbl">Bisa Dibaca</span>
                    </div>
                    <div class="hero-stat-item">
                        <span class="hero-stat-num"><?= $stat_kategori ?></span>
                        <span class="hero-stat-lbl">Kategori</span>
                    </div>
                </div>
            </div>
            <div class="hero-deco"><?= icon('book-open', 90, 'color:rgba(255,255,255,0.2)') ?></div>
        </div>

        <!-- SEARCH & FILTER -->
        <div class="toolbar-card anim anim-d2">
            <form method="GET" action="" class="toolbar-form">
                <div class="search-box">
                    <?= icon('search', 16, 'color:var(--text3)') ?>
                    <input type="text" name="search"
                        placeholder="Cari judul, pengarang, ISBN..."
                        value="<?= htmlspecialchars($search) ?>">
                </div>
                <select class="select-filter" name="kategori">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($kategori_list as $kat): ?>
                        <option value="<?= htmlspecialchars($kat) ?>"
                            <?= $filter_kat === $kat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($kat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-filter">
                    <?= icon('filter', 15) ?> Filter
                </button>
                <?php if ($search || $filter_kat): ?>
                    <a href="katalog.php" class="btn-reset">
                        <?= icon('times', 15) ?> Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- SECTION HEADER -->
        <div class="section-head anim anim-d3">
            <h3><?= icon('book', 18) ?> <?= ($search || $filter_kat) ? 'Hasil Pencarian' : 'Semua eBook' ?></h3>
            <span class="section-count"><?= number_format($total_buku) ?> buku ditemukan</span>
        </div>

        <!-- BOOK GRID -->
        <div class="books-grid anim anim-d3">
            <?php if (empty($buku_arr)): ?>
            <div class="empty-state">
                <?= icon('search', 48, 'color:var(--text3);margin-bottom:16px;display:block') ?>
                <h3>Buku tidak ditemukan</h3>
                <p>Coba kata kunci atau filter yang berbeda.</p>
                <a href="katalog.php" class="btn-primary" style="margin-top:16px;display:inline-flex;">
                    <?= icon('times', 15) ?> Reset Pencarian
                </a>
            </div>
            <?php else: ?>
                <?php foreach ($buku_arr as $i => $buku):
                    $c = $covers[$i % count($covers)];
                    $rating = (float)($buku['rating'] ?? 0);
                ?>
                <div class="book-card" onclick='bukaDetail(<?= json_encode([
                    "id"         => $buku["id"],
                    "judul"      => $buku["judul"],
                    "pengarang"  => $buku["pengarang"],
                    "penerbit"   => $buku["penerbit"] ?? "",
                    "tahun"      => $buku["tahun"] ?? "",
                    "kategori"   => $buku["kategori"] ?? "",
                    "isbn"       => $buku["isbn"] ?? "",
                    "deskripsi"  => $buku["deskripsi"] ?? "",
                    "cover_emoji"=> $buku["cover_emoji"] ?? "📚",
                    "cover_img"  => $buku["cover_img"] ?? "",
                    "file_pdf"   => $buku["file_pdf"] ?? "",
                    "halaman"    => $buku["halaman"] ?? "",
                    "bahasa"     => $buku["bahasa"] ?? "",
                    "rating"     => $rating,
                    "total_baca" => (int)($buku["total_baca"] ?? 0),
                    "isGuest"    => $isGuest,
                ], JSON_HEX_QUOT|JSON_HEX_APOS) ?>)'>
                    <div class="book-cover" style="background:linear-gradient(160deg,<?= $c[0] ?>,<?= $c[1] ?>,<?= $c[2] ?>);">
                        <?php if (!empty($buku['cover_img'])): ?>
                            <img src="<?= BASE_URL . htmlspecialchars($buku['cover_img']) ?>"
                                 alt="" style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?>
                            <span style="font-size:3rem;"><?= htmlspecialchars($buku['cover_emoji'] ?? '📚') ?></span>
                        <?php endif; ?>
                        <?php if (!empty($buku['file_pdf'])): ?>
                            <span class="format-ribbon pdf">PDF</span>
                        <?php endif; ?>
                        <?php if ($rating > 0): ?>
                            <div class="cover-rating"><?= icon('star', 11, 'color:#fbbf24') ?> <?= number_format($rating, 1) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="book-meta-wrap">
                        <?php if ($buku['kategori']): ?>
                            <div class="book-genre"><?= htmlspecialchars($buku['kategori']) ?></div>
                        <?php endif; ?>
                        <div class="book-title"><?= htmlspecialchars($buku['judul']) ?></div>
                        <div class="book-author">
                            <?= icon('pencil', 12) ?>
                            <?= htmlspecialchars($buku['pengarang']) ?>
                        </div>
                        <div class="book-footer">
                            <?php if ($buku['tahun']): ?>
                                <span class="book-year"><?= (int) $buku['tahun'] ?></span>
                            <?php endif; ?>
                            <?php if ((int)($buku['total_baca'] ?? 0) > 0): ?>
                                <span class="book-reads"><?= icon('eye', 11) ?> <?= number_format($buku['total_baca']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- PAGINATION -->
        <?php if ($total_page > 1): ?>
        <div class="pagination anim anim-d4">
            <div class="pagination-info">
                Menampilkan <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_buku) ?>
                dari <?= $total_buku ?> buku
            </div>
            <div class="pagination-btns">
                <a href="<?= $base_url . max(1, $page - 1) ?>"
                    class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                    <?= icon('chevron-left', 16) ?>
                </a>
                <?php
                $start = max(1, $page - 3);
                $end   = min($total_page, $page + 3);
                if ($start > 1): ?>
                    <a href="<?= $base_url ?>1" class="page-btn">1</a>
                    <?php if ($start > 2): ?><span class="page-btn" style="pointer-events:none;">…</span><?php endif; ?>
                <?php endif;
                for ($i = $start; $i <= $end; $i++): ?>
                    <a href="<?= $base_url . $i ?>"
                        class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor;
                if ($end < $total_page): ?>
                    <?php if ($end < $total_page - 1): ?><span class="page-btn" style="pointer-events:none;">…</span><?php endif; ?>
                    <a href="<?= $base_url . $total_page ?>" class="page-btn"><?= $total_page ?></a>
                <?php endif; ?>
                <a href="<?= $base_url . min($total_page, $page + 1) ?>"
                    class="page-btn <?= $page >= $total_page ? 'disabled' : '' ?>">
                    <?= icon('chevron-right', 16) ?>
                </a>
            </div>
        </div>
        <?php endif; ?>

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

<!-- MODAL DETAIL -->
<div class="modal-overlay" id="modalDetail">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-cover" id="d_cover">📚</div>
            <div class="modal-title-wrap">
                <div class="modal-title" id="d_judul">—</div>
                <div class="modal-author" id="d_pengarang">—</div>
            </div>
            <button class="modal-close" onclick="tutupModal()">×</button>
        </div>
        <div class="modal-body">
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="label"><?= icon('building', 12) ?> Penerbit</div>
                    <div class="value" id="d_penerbit">—</div>
                </div>
                <div class="detail-item">
                    <div class="label"><?= icon('calendar', 12) ?> Tahun Terbit</div>
                    <div class="value" id="d_tahun">—</div>
                </div>
                <div class="detail-item">
                    <div class="label"><?= icon('tag', 12) ?> Kategori</div>
                    <div class="value" id="d_kategori">—</div>
                </div>
                <div class="detail-item">
                    <div class="label"><?= icon('barcode', 12) ?> ISBN</div>
                    <div class="value" id="d_isbn">—</div>
                </div>
                <div class="detail-item">
                    <div class="label"><?= icon('file-alt', 12) ?> Halaman</div>
                    <div class="value" id="d_halaman">—</div>
                </div>
                <div class="detail-item">
                    <div class="label"><?= icon('language', 12) ?> Bahasa</div>
                    <div class="value" id="d_bahasa">—</div>
                </div>
                <div class="detail-item">
                    <div class="label"><?= icon('star', 12, 'color:#fbbf24') ?> Rating</div>
                    <div class="value" id="d_rating">—</div>
                </div>
                <div class="detail-item">
                    <div class="label"><?= icon('eye', 12) ?> Total Dibaca</div>
                    <div class="value" id="d_total_baca">—</div>
                </div>
            </div>
            <div class="deskripsi-wrap">
                <div class="deskripsi-label"><?= icon('align-left', 12) ?> Deskripsi</div>
                <div class="deskripsi-text" id="d_deskripsi">—</div>
            </div>
            <div class="modal-action" id="d_actions"></div>
        </div>
    </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';

let toastTimer;
function showToast(e) {
    e && e.preventDefault();
    const t = document.getElementById('toast');
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 4000);
}

function bukaDetail(buku) {
    const coverEl = document.getElementById('d_cover');
    if (buku.cover_img) {
        coverEl.innerHTML = `<img src="${BASE_URL}${buku.cover_img}"
            style="width:100%;height:100%;object-fit:cover;border-radius:8px;" alt="">`;
    } else {
        coverEl.textContent = buku.cover_emoji || '📚';
    }

    document.getElementById('d_judul').textContent      = buku.judul      || '—';
    document.getElementById('d_pengarang').textContent  = buku.pengarang  || '—';
    document.getElementById('d_penerbit').textContent   = buku.penerbit   || '—';
    document.getElementById('d_tahun').textContent      = buku.tahun      || '—';
    document.getElementById('d_kategori').textContent   = buku.kategori   || '—';
    document.getElementById('d_isbn').textContent       = buku.isbn       || '—';
    document.getElementById('d_halaman').textContent    = buku.halaman ? buku.halaman + ' hal.' : '—';
    document.getElementById('d_bahasa').textContent     = buku.bahasa     || '—';
    document.getElementById('d_rating').textContent     = buku.rating > 0 ? '⭐ ' + parseFloat(buku.rating).toFixed(1) : '—';
    document.getElementById('d_total_baca').textContent = buku.total_baca ? buku.total_baca + 'x' : '0x';
    document.getElementById('d_deskripsi').textContent  = buku.deskripsi  || 'Tidak ada deskripsi.';

    const actionsEl = document.getElementById('d_actions');
    if (buku.file_pdf) {
        if (buku.isGuest) {
            actionsEl.innerHTML = `
                <button onclick="showLoginMessage()" class="btn-read">
                   <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M21 5c-1.11-.35-2.33-.5-3.5-.5-1.95 0-4.05.4-5.5 1.5-1.45-1.1-3.55-1.5-5.5-1.5S2.45 4.9 1 6v14.65c0 .25.25.5.5.5.1 0 .15-.05.25-.05C3.1 20.45 5.05 20 6.5 20c1.95 0 4.05.4 5.5 1.5 1.35-.85 3.8-1.5 5.5-1.5 1.65 0 3.35.3 4.75 1.05.1.05.15.05.25.05.25 0 .5-.25.5-.5V6c-.6-.45-1.25-.75-2-1zm0 13.5c-1.1-.35-2.3-.5-3.5-.5-1.7 0-4.15.65-5.5 1.5V8c1.35-.85 3.8-1.5 5.5-1.5 1.2 0 2.4.15 3.5.5v11.5z"/></svg>
                   Baca Sekarang
                </button>
            `;
        } else {
            actionsEl.innerHTML = `
                <a href="${BASE_URL}${buku.file_pdf}"
                   target="_blank" rel="noopener"
                   class="btn-read">
                   <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M21 5c-1.11-.35-2.33-.5-3.5-.5-1.95 0-4.05.4-5.5 1.5-1.45-1.1-3.55-1.5-5.5-1.5S2.45 4.9 1 6v14.65c0 .25.25.5.5.5.1 0 .15-.05.25-.05C3.1 20.45 5.05 20 6.5 20c1.95 0 4.05.4 5.5 1.5 1.35-.85 3.8-1.5 5.5-1.5 1.65 0 3.35.3 4.75 1.05.1.05.15.05.25.05.25 0 .5-.25.5-.5V6c-.6-.45-1.25-.75-2-1zm0 13.5c-1.1-.35-2.3-.5-3.5-.5-1.7 0-4.15.65-5.5 1.5V8c1.35-.85 3.8-1.5 5.5-1.5 1.2 0 2.4.15 3.5.5v11.5z"/></svg>
                   Baca Sekarang
                </a>
            `;
        }
    } else {
        actionsEl.innerHTML = `
            <div class="no-file-note">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="margin-right:6px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
                File PDF belum tersedia untuk buku ini.
            </div>
        `;
    }

    document.getElementById('modalDetail').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function showLoginMessage() {
    const confirmed = confirm('Untuk membaca buku, Anda harus login terlebih dahulu.\n\nKlik OK untuk diarahkan ke halaman login atau daftar.');
    if (confirmed) {
        window.location.href = '../index.php';
    }
}

function tutupModal() {
    document.getElementById('modalDetail').classList.remove('show');
    document.body.style.overflow = '';
}

document.getElementById('modalDetail').addEventListener('click', function(e) {
    if (e.target === this) tutupModal();
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') tutupModal();
});
</script>
</body>
</html>