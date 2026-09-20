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
$uid  = (int) $_SESSION['user_id'];

// ── Data user ─────────────────────────────────────────────────────────────────
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

// ── Ambil koleksi dari DB ─────────────────────────────────────────────────────
$koleksi = [];
if ($dbOk) {
    $sql = "SELECT k.id AS koleksi_id, k.status_baca, k.tanggal_tambah, k.catatan,
                   COALESCE(b.id, 0) AS buku_id,
                   COALESCE(b.judul, '[Buku Dihapus]') AS judul,
                   COALESCE(b.pengarang, '-') AS pengarang,
                   COALESCE(b.kategori, '-') AS kategori,
                   b.cover_img AS cover,
                   COALESCE(b.tahun_terbit, '') AS tahun_terbit,
                   COALESCE(b.deskripsi, '') AS deskripsi,
                   COALESCE(b.cover_emoji, '📖') AS cover_emoji,
                   COALESCE(b.penulis, '-') AS penulis,
                   COALESCE(b.rating, 0) AS rating,
                   IF(b.id IS NULL, 1, 0) AS buku_dihapus
            FROM koleksi k
            LEFT JOIN buku b ON b.id = k.id_buku
            WHERE k.id_anggota = $uid
            ORDER BY k.tanggal_tambah DESC";
    $r = mysqli_query($conn, $sql);
    if ($r) {
        while ($row = mysqli_fetch_assoc($r)) {
            $koleksi[] = $row;
        }
    }
}

// ── Filter ────────────────────────────────────────────────────────────────────
$searchQ      = trim($_GET['search'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

if ($searchQ !== '') {
    $koleksi = array_values(array_filter($koleksi, function($b) use ($searchQ) {
        return stripos($b['judul'], $searchQ) !== false
            || stripos($b['pengarang'], $searchQ) !== false
            || stripos($b['kategori'], $searchQ) !== false;
    }));
}
if ($filterStatus !== '') {
    $koleksi = array_values(array_filter($koleksi, function($b) use ($filterStatus) {
        return $b['status_baca'] === $filterStatus;
    }));
}

// Tidak ada fallback dummy — tampilkan data nyata dari DB saja

// ── Hitung stats ──────────────────────────────────────────────────────────────
$totalKoleksi = count($koleksi);
$sudahSelesai = count(array_filter($koleksi, fn($b) => $b['status_baca'] === 'selesai'));
$sedangDibaca = count(array_filter($koleksi, fn($b) => $b['status_baca'] === 'sedang_dibaca'));
$belumDibaca  = count(array_filter($koleksi, fn($b) => $b['status_baca'] === 'belum_dibaca'));

$isGuest     = false;
$active_menu = 'koleksi';

// ── SVG Icon helper ───────────────────────────────────────────────────────────
function icon($name, $size = 16, $style = '') {
    $s = $style ? " style=\"$style\"" : '';
    $icons = [
        'house'        => '<path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>',
        'book-open'    => '<path d="M21 4H3a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h18a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1zM3 6h8v12H3V6zm10 12V6h8v12h-8z"/>',
        'book'         => '<path d="M18 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zM6 4h5v8l-2.5-1.5L6 12V4zm0 16V14l2.5-1.5L11 14v6H6zm12 0h-5v-6l2.5 1.5L18 14v6zm0-8h-5V4h5v8z"/>',
        'tag'          => '<path d="M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58s1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41s-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z"/>',
        'layers'       => '<path d="M11.99 18.54l-7.37-5.73L3 14.07l9 7 9-7-1.63-1.27-7.38 5.74zM12 16l7.36-5.73L21 9l-9-7-9 7 1.63 1.27L12 16z"/>',
        'history'      => '<path d="M13 3a9 9 0 0 0-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42A8.954 8.954 0 0 0 13 21a9 9 0 0 0 0-18zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/>',
        'star'         => '<path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>',
        'check-circle' => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>',
        'bell'         => '<path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/>',
        'bars'         => '<path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>',
        'search'       => '<path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>',
        'grid'         => '<path d="M3 3h8v8H3V3zm0 10h8v8H3v-8zm10-10h8v8h-8V3zm0 10h8v8h-8v-8z"/>',
        'list'         => '<path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/>',
        'x'            => '<path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>',
        'trash'        => '<path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>',
        'eye'          => '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>',
        'book-reader'  => '<path d="M21 5c-1.11-.35-2.33-.5-3.5-.5-1.95 0-4.05.4-5.5 1.5-1.45-1.1-3.55-1.5-5.5-1.5S2.45 4.9 1 6v14.65c0 .25.25.5.5.5.1 0 .15-.05.25-.05C3.1 20.45 5.05 20 6.5 20c1.95 0 4.05.4 5.5 1.5 1.35-.85 3.8-1.5 5.5-1.5 1.65 0 3.35.3 4.75 1.05.1.05.15.05.25.05.25 0 .5-.25.5-.5V6c-.6-.45-1.25-.75-2-1zm0 13.5c-1.1-.35-2.3-.5-3.5-.5-1.7 0-4.15.65-5.5 1.5V8c1.35-.85 3.8-1.5 5.5-1.5 1.2 0 2.4.15 3.5.5v11.5z"/>',
        'check'        => '<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>',
        'clock'        => '<path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/>',
        'bookmark'     => '<path d="M17 3H7c-1.1 0-1.99.9-1.99 2L5 21l7-3 7 3V5c0-1.1-.9-2-2-2z"/>',
        'user'         => '<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>',
        'sign-out'     => '<path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>',
        'lock'         => '<path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>',
        'sign-in'      => '<path d="M11 7L9.6 8.4l2.6 2.6H2v2h10.2l-2.6 2.6L11 17l5-5-5-5zm9 12h-8v2h8c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-8v2h8v14z"/>',
        'user-plus'    => '<path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V8H4v2H2v2h2v2h2v-2h2v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>',
        'arrow-right'  => '<path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6z"/>',
    ];
    $path = $icons[$name] ?? '<circle cx="12" cy="12" r="10"/>';
    return "<svg xmlns='http://www.w3.org/2000/svg' width='{$size}' height='{$size}' viewBox='0 0 24 24' fill='currentColor'{$s}>{$path}</svg>";
}

// ── Label & warna status ──────────────────────────────────────────────────────
function statusMeta($status) {
    return match($status) {
        'selesai'       => ['label' => 'Selesai',        'color' => '#4caf50', 'bg' => 'rgba(76,175,80,.15)',   'icon' => 'check-circle'],
        'sedang_dibaca' => ['label' => 'Sedang Dibaca',  'color' => '#63b3ed', 'bg' => 'rgba(99,179,237,.15)', 'icon' => 'clock'],
        default         => ['label' => 'Belum Dibaca',   'color' => '#94a3b8', 'bg' => 'rgba(148,163,184,.12)','icon' => 'bookmark'],
    };
}

$coverColors = ['#7b2d8b','#1e88e5','#e05c5c','#4aa8a0','#c9a84c','#4caf50','#8b6ddb','#f59e0b'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Koleksi Saya — Pojok Baca</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/anggota/sidebar.css">
    <link rel="stylesheet" href="../assets/css/anggota/koleksi.css">
</head>
<body>

<?php
    $active_menu       = 'koleksi';
    $topbar_title      = 'Koleksi Saya';
    $topbar_breadcrumb = 'Koleksi Saya';
    $topbar_search     = true;
    require_once '../includes/anggota/topnav.php';
?>

<div class="main" style="margin-left:0 !important;width:100%;">

    <!-- ── CONTENT ── -->
    <div class="content">

        <!-- HERO -->
        <div class="hero anim anim-d1">
            <div class="hero-text">
                <div class="hero-eyebrow">
                    <?= icon('layers', 13) ?> Koleksi Buku
                </div>
                <h2>Koleksi <span>Saya</span></h2>
                <p>Semua eBook yang telah Anda simpan, terorganisir dan siap dibaca kapan saja.</p>
                <div class="hero-btns" style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap;">
                    <a href="katalog_ebook.php" class="btn-baca" style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:600;background:var(--accent,#6366f1);color:#fff;text-decoration:none;">
                        <?= icon('search', 14) ?> Tambah dari Katalog
                    </a>
                    <a href="wishlist.php" style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:600;background:rgba(99,102,241,.15);color:#a5b4fc;border:1px solid rgba(99,102,241,.3);text-decoration:none;">
                        <?= icon('bookmark', 14) ?> Wishlist Saya
                    </a>
                </div>
            </div>
            <div class="hero-deco"><?= icon('book-reader', 90) ?></div>
        </div>

        <!-- STATS -->
        <div class="stats anim anim-d2">
            <div class="stat-card">
                <div class="stat-icon purple"><?= icon('layers', 22) ?></div>
                <div>
                    <div class="stat-num"><?= $totalKoleksi ?></div>
                    <div class="stat-label">Total Koleksi</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon teal"><?= icon('check-circle', 22) ?></div>
                <div>
                    <div class="stat-num"><?= $sudahSelesai ?></div>
                    <div class="stat-label">Selesai Dibaca</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon gold"><?= icon('clock', 22) ?></div>
                <div>
                    <div class="stat-num"><?= $sedangDibaca ?></div>
                    <div class="stat-label">Sedang Dibaca</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><?= icon('bookmark', 22) ?></div>
                <div>
                    <div class="stat-num"><?= $belumDibaca ?></div>
                    <div class="stat-label">Belum Dibaca</div>
                </div>
            </div>
        </div>

        <!-- TOOLBAR -->
        <div class="toolbar anim anim-d3">
            <div class="search-wrap">
                <span class="si"><?= icon('search', 15) ?></span>
                <input type="text" id="searchInput"
                       placeholder="Cari judul, pengarang, kategori..."
                       value="<?= htmlspecialchars($searchQ) ?>"
                       oninput="filterCards(this.value)">
                <button class="btn-clear <?= $searchQ ? 'show' : '' ?>"
                        id="clearBtn" onclick="clearSearch()">
                    <?= icon('x', 14) ?>
                </button>
            </div>

            <div class="filter-tabs" id="filterTabs">
                <button class="filter-tab <?= $filterStatus === '' ? 'active' : '' ?>"
                        onclick="setFilter('')">Semua</button>
                <button class="filter-tab <?= $filterStatus === 'sedang_dibaca' ? 'active' : '' ?>"
                        onclick="setFilter('sedang_dibaca')">Sedang Dibaca</button>
                <button class="filter-tab <?= $filterStatus === 'belum_dibaca' ? 'active' : '' ?>"
                        onclick="setFilter('belum_dibaca')">Belum Dibaca</button>
                <button class="filter-tab <?= $filterStatus === 'selesai' ? 'active' : '' ?>"
                        onclick="setFilter('selesai')">Selesai</button>
            </div>

            <div class="view-toggle">
                <button class="view-btn active" id="btnGrid" onclick="setView('grid')" title="Grid">
                    <?= icon('grid', 16) ?>
                </button>
                <button class="view-btn" id="btnList" onclick="setView('list')" title="List">
                    <?= icon('list', 16) ?>
                </button>
            </div>
        </div>

        <!-- SECTION HEAD + GRID/LIST -->
        <div class="anim anim-d4">
            <div class="section-head">
                <h3><?= icon('book', 17) ?> Daftar Buku</h3>
                <span class="count-badge" id="countBadge"><?= $totalKoleksi ?> buku</span>
            </div>

            <!-- GRID VIEW -->
            <div class="buku-grid" id="bukuGrid">
                <?php foreach ($koleksi as $i => $b):
                    $sm         = statusMeta($b['status_baca']);
                    $coverClass = 'cover-color-' . ($i % count($coverColors));
                    $statusCls  = 'status-' . $b['status_baca'];
                    $emoji      = $b['cover_emoji'] ?? '📖';
                ?>
                <div class="buku-card"
                     data-koleksi-id="<?= $b['koleksi_id'] ?>"
                     data-judul="<?= htmlspecialchars(strtolower($b['judul'])) ?>"
                     data-pengarang="<?= htmlspecialchars(strtolower($b['pengarang'] ?? $b['penulis'] ?? '')) ?>"
                     data-kategori="<?= htmlspecialchars(strtolower($b['kategori'])) ?>"
                     data-status="<?= htmlspecialchars($b['status_baca']) ?>">

                    <div class="buku-cover <?= $coverClass ?>">
                        <?php if (!empty($b['cover'])): ?>
                            <img src="../<?= htmlspecialchars($b['cover']) ?>"
                                 alt="<?= htmlspecialchars($b['judul']) ?>">
                        <?php else: ?>
                            <span class="cover-placeholder"><?= $emoji ?></span>
                        <?php endif; ?>
                        <div class="status-badge <?= $statusCls ?>">
                            <?= icon($sm['icon'], 11) ?> <?= $sm['label'] ?>
                        </div>
                    </div>

                    <div class="buku-info">
                        <div class="buku-judul"><?= htmlspecialchars($b['judul']) ?></div>
                        <?php if (!empty($b['buku_dihapus'])): ?>
                            <div class="buku-pengarang" style="color:#e05c5c;font-size:11px;">
                                ⚠️ Buku ini telah dihapus oleh admin
                            </div>
                        <?php else: ?>
                        <div class="buku-pengarang"><?= htmlspecialchars($b['pengarang'] ?? $b['penulis'] ?? '-') ?></div>
                        <div class="buku-meta">
                            <span class="buku-kat"><?= htmlspecialchars($b['kategori']) ?></span>
                            <span class="buku-tahun"><?= htmlspecialchars($b['tahun_terbit'] ?? '') ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="buku-actions">
                        <?php if (empty($b['buku_dihapus'])): ?>
                        <a href="baca.php?id=<?= $b['buku_id'] ?>" class="btn-baca">
                            <?= icon('eye', 14) ?> Baca
                        </a>
                        <button class="btn-status"
                                onclick="gantiStatus(<?= $b['koleksi_id'] ?>, this)"
                                data-current="<?= htmlspecialchars($b['status_baca']) ?>"
                                title="Ganti Status">
                            <?= icon($sm['icon'], 14) ?>
                        </button>
                        <?php endif; ?>
                        <button class="btn-hapus"
                                onclick="hapusBuku(<?= $b['koleksi_id'] ?>)"
                                title="Hapus dari Koleksi">
                            <?= icon('trash', 14) ?>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- LIST VIEW -->
            <div class="buku-list hidden" id="bukuList">
                <?php foreach ($koleksi as $i => $b):
                    $sm         = statusMeta($b['status_baca']);
                    $coverClass = 'cover-color-' . ($i % count($coverColors));
                    $statusCls  = 'status-' . $b['status_baca'];
                    $emoji      = $b['cover_emoji'] ?? '📖';
                ?>
                <div class="buku-row"
                     data-koleksi-id="<?= $b['koleksi_id'] ?>"
                     data-judul="<?= htmlspecialchars(strtolower($b['judul'])) ?>"
                     data-pengarang="<?= htmlspecialchars(strtolower($b['pengarang'] ?? $b['penulis'] ?? '')) ?>"
                     data-kategori="<?= htmlspecialchars(strtolower($b['kategori'])) ?>"
                     data-status="<?= htmlspecialchars($b['status_baca']) ?>">

                    <div class="row-cover <?= $coverClass ?>">
                        <?php if (!empty($b['cover'])): ?>
                            <img src="../<?= htmlspecialchars($b['cover']) ?>"
                                 alt="<?= htmlspecialchars($b['judul']) ?>">
                        <?php else: ?>
                            <span><?= $emoji ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="row-info">
                        <div class="row-judul"><?= htmlspecialchars($b['judul']) ?></div>
                        <?php if (!empty($b['buku_dihapus'])): ?>
                            <div class="row-pengarang" style="color:#e05c5c;font-size:11px;">⚠️ Buku ini telah dihapus oleh admin</div>
                        <?php else: ?>
                        <div class="row-pengarang">
                            <?= htmlspecialchars($b['pengarang'] ?? $b['penulis'] ?? '-') ?>
                            <?php if (!empty($b['tahun_terbit'])): ?> · <?= htmlspecialchars($b['tahun_terbit']) ?><?php endif; ?>
                        </div>
                        <div class="row-tags">
                            <span class="buku-kat"><?= htmlspecialchars($b['kategori']) ?></span>
                            <span class="row-status-badge <?= $statusCls ?>">
                                <?= icon($sm['icon'], 11) ?> <?= $sm['label'] ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="row-actions">
                        <?php if (empty($b['buku_dihapus'])): ?>
                        <a href="baca.php?id=<?= $b['buku_id'] ?>" class="btn-baca">
                            <?= icon('eye', 14) ?> Baca
                        </a>
                        <button class="btn-status"
                                onclick="gantiStatus(<?= $b['koleksi_id'] ?>, this)"
                                data-current="<?= htmlspecialchars($b['status_baca']) ?>"
                                title="Ganti Status">
                            <?= icon($sm['icon'], 14) ?>
                        </button>
                        <?php endif; ?>
                        <button class="btn-hapus"
                                onclick="hapusBuku(<?= $b['koleksi_id'] ?>)"
                                title="Hapus dari Koleksi">
                            <?= icon('trash', 14) ?>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- EMPTY STATE -->
            <div class="empty-state hidden" id="emptyState">
                <?= icon('book-reader', 56) ?>
                <h4>Koleksi Kosong</h4>
                <p id="emptyMsg">Belum ada buku di koleksi Anda.</p>
                <a href="katalog_ebook.php" class="btn-katalog">
                    <?= icon('search', 15) ?> Jelajahi Katalog
                </a>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /main -->

<!-- ── MODAL KONFIRMASI HAPUS ── -->
<div class="modal-overlay" id="modalHapus">
    <div class="modal">
        <div class="modal-icon"><?= icon('trash', 28) ?></div>
        <h3>Hapus dari Koleksi?</h3>
        <p>Buku ini akan dihapus dari koleksi Anda. Tindakan ini tidak dapat dibatalkan.</p>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeModal()">Batal</button>
            <button class="btn-confirm" id="btnConfirmHapus">Hapus</button>
        </div>
    </div>
</div>

<script>
    /* ── Sidebar Toggle ── */
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('overlay').classList.toggle('show');
    }
    function closeSidebar() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('overlay').classList.remove('show');
    }

    /* ── View Mode ── */
    let currentView = 'grid';
    function setView(v) {
        currentView = v;
        document.getElementById('bukuGrid').classList.toggle('hidden', v !== 'grid');
        document.getElementById('bukuList').classList.toggle('hidden', v !== 'list');
        document.getElementById('btnGrid').classList.toggle('active', v === 'grid');
        document.getElementById('btnList').classList.toggle('active', v === 'list');
        localStorage.setItem('koleksiView', v);
    }
    const savedView = localStorage.getItem('koleksiView') || 'grid';
    setView(savedView);

    /* ── Filter Data Attributes ── */
    document.querySelectorAll('.filter-tab').forEach(btn => {
        const map = {
            'Semua': '',
            'Sedang Dibaca': 'sedang_dibaca',
            'Belum Dibaca': 'belum_dibaca',
            'Selesai': 'selesai'
        };
        btn.dataset.filter = map[btn.textContent.trim()] ?? '';
    });

    /* ── Search & Filter ── */
    function filterCards(q) {
        q = q.toLowerCase().trim();
        const activeFilter = document.querySelector('.filter-tab.active')?.dataset.filter ?? '';
        applyFilters(q, activeFilter);
    }

    function applyFilters(q, status) {
        const gridCards = document.querySelectorAll('#bukuGrid .buku-card');
        const listRows  = document.querySelectorAll('#bukuList .buku-row');
        let visible = 0;

        function matches(el) {
            const textMatch = q === '' ||
                el.dataset.judul.includes(q) ||
                el.dataset.pengarang.includes(q) ||
                el.dataset.kategori.includes(q);
            const statusMatch = status === '' || el.dataset.status === status;
            return textMatch && statusMatch;
        }

        gridCards.forEach(c => {
            const show = matches(c);
            c.classList.toggle('hidden', !show);
            if (show) visible++;
        });
        listRows.forEach(r => {
            const show = matches(r);
            r.classList.toggle('hidden', !show);
        });

        document.getElementById('countBadge').textContent = visible + ' buku';
        document.getElementById('clearBtn').classList.toggle('show', q.length > 0);

        const empty = document.getElementById('emptyState');
        if (visible === 0) {
            empty.classList.remove('hidden');
            document.getElementById('emptyMsg').textContent =
                q ? `Tidak ada buku yang cocok dengan "${q}".` : 'Tidak ada buku dengan filter ini.';
        } else {
            empty.classList.add('hidden');
        }
    }

    function clearSearch() {
        const inp = document.getElementById('searchInput');
        inp.value = '';
        filterCards('');
        inp.focus();
    }

    function setFilter(status) {
        document.querySelectorAll('.filter-tab').forEach(t => {
            t.classList.toggle('active', (t.dataset.filter ?? '') === status);
        });
        const q = document.getElementById('searchInput').value.toLowerCase().trim();
        applyFilters(q, status);
    }

    /* ── Ganti Status ── */
    const statusCycle = ['belum_dibaca', 'sedang_dibaca', 'selesai'];

    function gantiStatus(koleksiId, btn) {
        const curr = btn.dataset.current;
        const next = statusCycle[(statusCycle.indexOf(curr) + 1) % statusCycle.length];
        fetch('ajax/update_status_koleksi.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `koleksi_id=${koleksiId}&status=${next}`
        })
        .then(r => r.json())
        .then(d => { if (d.success) location.reload(); })
        .catch(() => location.reload());
    }

    /* ── Hapus Buku ── */
    let targetHapusId = null;

    function hapusBuku(koleksiId) {
        targetHapusId = koleksiId;
        document.getElementById('modalHapus').classList.add('show');
    }

    document.getElementById('btnConfirmHapus').addEventListener('click', () => {
        if (!targetHapusId) return;
        fetch('ajax/hapus_koleksi.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `koleksi_id=${targetHapusId}`
        })
        .then(r => r.json())
        .then(d => {
            if (d.success && targetHapusId) {
                // Simpan ID ke variabel lokal sebelum closeModal mereset targetHapusId
                const hapusId = targetHapusId;
                // Animasikan semua elemen (grid card + list row) dengan koleksi_id ini
                const targets = document.querySelectorAll(`[data-koleksi-id="${hapusId}"]`);
                targets.forEach(el => {
                    el.style.transition = 'opacity .3s, transform .3s';
                    el.style.opacity    = '0';
                    el.style.transform  = 'scale(.95)';
                });
                setTimeout(() => {
                    document.querySelectorAll(`[data-koleksi-id="${hapusId}"]`).forEach(el => el.remove());
                    updateCount();
                }, 300);
            }
            closeModal();
        })
        .catch(() => closeModal());
    });

    function closeModal() {
        document.getElementById('modalHapus').classList.remove('show');
        targetHapusId = null;
    }

    function updateCount() {
        const visible = document.querySelectorAll(
            '#bukuGrid .buku-card:not(.hidden), #bukuList .buku-row:not(.hidden)'
        ).length;
        document.getElementById('countBadge').textContent = visible + ' buku';
        const empty = document.getElementById('emptyState');
        if (visible === 0) {
            empty.classList.remove('hidden');
            document.getElementById('emptyMsg').textContent = 'Koleksi Anda kosong.';
        } else {
            empty.classList.add('hidden');
        }
    }

    document.getElementById('modalHapus').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
</script>

</body>
</html>