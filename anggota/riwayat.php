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

// ── Ambil riwayat baca dari DB ────────────────────────────────────────────────
$riwayat = [];
if ($dbOk) {
    $sql = "SELECT r.id AS riwayat_id,
                   r.tanggal_akses  AS tanggal_baca,
                   r.tanggal_kembali,
                   r.status,
                   r.created_at,
                   COALESCE(b.id, 0) AS buku_id,
                   COALESCE(b.judul, '[Buku Dihapus]') AS judul,
                   COALESCE(b.pengarang, '-') AS pengarang,
                   COALESCE(b.penulis, '-') AS penulis,
                   COALESCE(b.kategori, '-') AS kategori,
                   b.cover_img AS cover,
                   COALESCE(b.cover_emoji, '📖') AS cover_emoji,
                   b.halaman AS total_halaman,
                   COALESCE(b.tahun_terbit, '') AS tahun_terbit,
                   NULL AS halaman_terakhir,
                   NULL AS durasi_menit,
                   IF(b.id IS NULL, 1, 0) AS buku_dihapus
            FROM riwayat_baca r
            LEFT JOIN buku b ON b.id = r.id_buku
            WHERE r.id_anggota = $uid
            ORDER BY r.tanggal_akses DESC";
    $r = mysqli_query($conn, $sql);
    if ($r) {
        while ($row = mysqli_fetch_assoc($r)) {
            // Anggap selesai jika status = 'selesai'
            $row['halaman_terakhir'] = $row['status'] === 'selesai'
                ? $row['total_halaman']
                : null;
            $riwayat[] = $row;
        }
    }
}

// ── Filter ────────────────────────────────────────────────────────────────────
$searchQ     = trim($_GET['search'] ?? '');
$filterRange = trim($_GET['range']  ?? '');

if ($searchQ !== '') {
    $riwayat = array_values(array_filter($riwayat, function($r) use ($searchQ) {
        return stripos($r['judul'], $searchQ) !== false
            || stripos($r['pengarang'] ?? $r['penulis'] ?? '', $searchQ) !== false
            || stripos($r['kategori'], $searchQ) !== false;
    }));
}
if ($filterRange !== '') {
    $now = time();
    $riwayat = array_values(array_filter($riwayat, function($r) use ($filterRange, $now) {
        $ts = strtotime($r['tanggal_baca']);
        return match($filterRange) {
            'hari_ini' => date('Y-m-d', $ts) === date('Y-m-d'),
            'minggu'   => ($now - $ts) <= 7 * 86400,
            'bulan'    => ($now - $ts) <= 30 * 86400,
            default    => true,
        };
    }));
}

// Tidak ada fallback dummy — tampilkan data nyata dari DB saja

// ── Stats ─────────────────────────────────────────────────────────────────────
$totalSesi        = count($riwayat);
$bukuSelesai      = count(array_filter($riwayat, fn($r) => $r['status'] === 'selesai'));
$bukuAktif        = count(array_filter($riwayat, fn($r) => $r['status'] === 'aktif'));
$totalHalamanBuku = array_sum(array_column($riwayat, 'total_halaman'));

$isGuest     = false;
$active_menu = 'riwayat';

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
        'x'            => '<path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>',
        'trash'        => '<path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>',
        'eye'          => '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>',
        'clock'        => '<path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/>',
        'bookmark'     => '<path d="M17 3H7c-1.1 0-1.99.9-1.99 2L5 21l7-3 7 3V5c0-1.1-.9-2-2-2z"/>',
        'user'         => '<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>',
        'sign-out'     => '<path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>',
        'check'        => '<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>',
        'trending'     => '<path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/>',
        'calendar'     => '<path d="M20 3h-1V1h-2v2H7V1H5v2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 18H4V8h16v13z"/>',
        'page'         => '<path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>',
        'lock'         => '<path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>',
        'user-plus'    => '<path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>',
        'sign-in'      => '<path d="M11 7L9.6 8.4l2.6 2.6H2v2h10.2l-2.6 2.6L11 17l5-5-5-5zm9 12h-8v2h8c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-8v2h8v14z"/>',
        'book-reader'  => '<path d="M21 5c-1.11-.35-2.33-.5-3.5-.5-1.95 0-4.05.4-5.5 1.5-1.45-1.1-3.55-1.5-5.5-1.5S2.45 4.9 1 6v14.65c0 .25.25.5.5.5.1 0 .15-.05.25-.05C3.1 20.45 5.05 20 6.5 20c1.95 0 4.05.4 5.5 1.5 1.35-.85 3.8-1.5 5.5-1.5 1.65 0 3.35.3 4.75 1.05.1.05.15.05.25.05.25 0 .5-.25.5-.5V6c-.6-.45-1.25-.75-2-1zm0 13.5c-1.1-.35-2.3-.5-3.5-.5-1.7 0-4.15.65-5.5 1.5V8c1.35-.85 3.8-1.5 5.5-1.5 1.2 0 2.4.15 3.5.5v11.5z"/>',
    ];
    $path = $icons[$name] ?? '<circle cx="12" cy="12" r="10"/>';
    return "<svg xmlns='http://www.w3.org/2000/svg' width='{$size}' height='{$size}' viewBox='0 0 24 24' fill='currentColor'{$s}>{$path}</svg>";
}

// ── Helper format tanggal relatif ─────────────────────────────────────────────
function formatTanggal($tgl) {
    if (!$tgl) return '—';
    $ts   = strtotime($tgl);
    $diff = time() - $ts;
    if ($diff < 60)       return 'Baru saja';
    if ($diff < 3600)     return floor($diff/60) . ' menit lalu';
    if ($diff < 86400)    return floor($diff/3600) . ' jam lalu';
    if ($diff < 604800)   return floor($diff/86400) . ' hari lalu';
    return date('d M Y', $ts);
}

$coverColors = ['#7b2d8b','#1e88e5','#e05c5c','#4aa8a0','#c9a84c','#4caf50','#8b6ddb','#f59e0b'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Baca — Pojok Baca</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/anggota/sidebar.css">
    <link rel="stylesheet" href="../assets/css/anggota/riwayat.css">
</head>
<body>

<?php
    $active_menu       = 'riwayat';
    $topbar_title      = 'Riwayat Baca';
    $topbar_breadcrumb = 'Riwayat Baca';
    $topbar_search     = true;
    require_once '../includes/anggota/topnav.php';
?>

<div class="main" style="margin-left:0 !important;width:100%;">

    <!-- CONTENT -->
    <div class="content">

        <!-- HERO -->
        <div class="hero anim anim-d1">
            <div class="hero-text">
                <div class="hero-eyebrow">
                    <?= icon('history', 13) ?> Aktivitas Membaca
                </div>
                <h2>Riwayat <span>Baca</span></h2>
                <p>Pantau perjalanan membaca Anda — setiap buku yang dipinjam dan diselesaikan.</p>
            </div>
            <div class="hero-deco"><?= icon('history', 90) ?></div>
        </div>

        <!-- STATS -->
        <div class="stats anim anim-d2">
            <div class="stat-card">
                <div class="stat-icon blue"><?= icon('history', 22) ?></div>
                <div>
                    <div class="stat-num"><?= $totalSesi ?></div>
                    <div class="stat-label">Total Pinjaman</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><?= icon('check-circle', 22) ?></div>
                <div>
                    <div class="stat-num"><?= $bukuSelesai ?></div>
                    <div class="stat-label">Buku Selesai</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><?= icon('book-open', 22) ?></div>
                <div>
                    <div class="stat-num"><?= $bukuAktif ?></div>
                    <div class="stat-label">Sedang Dibaca</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon gold"><?= icon('page', 22) ?></div>
                <div>
                    <div class="stat-num"><?= number_format($totalHalamanBuku) ?></div>
                    <div class="stat-label">Total Halaman</div>
                </div>
            </div>
        </div>

        <!-- TOOLBAR -->
        <div class="toolbar anim anim-d3">
            <div class="search-wrap">
                <span class="si"><?= icon('search', 15) ?></span>
                <input type="text" id="searchInput" placeholder="Cari judul buku, pengarang..."
                       value="<?= htmlspecialchars($searchQ) ?>"
                       oninput="filterRows(this.value)">
                <button class="btn-clear <?= $searchQ ? 'show' : '' ?>" id="clearBtn" onclick="clearSearch()">
                    <?= icon('x', 14) ?>
                </button>
            </div>
            <div class="filter-tabs">
                <button class="filter-tab <?= $filterRange === '' ? 'active' : '' ?>" onclick="setFilter('')">Semua</button>
                <button class="filter-tab <?= $filterRange === 'hari_ini' ? 'active' : '' ?>" onclick="setFilter('hari_ini')">Hari Ini</button>
                <button class="filter-tab <?= $filterRange === 'minggu' ? 'active' : '' ?>" onclick="setFilter('minggu')">7 Hari</button>
                <button class="filter-tab <?= $filterRange === 'bulan' ? 'active' : '' ?>" onclick="setFilter('bulan')">30 Hari</button>
            </div>
        </div>

        <!-- SECTION HEAD -->
        <div class="anim anim-d4">
            <div class="section-head">
                <h3><?= icon('clock', 17) ?> Daftar Riwayat</h3>
                <span class="count-badge" id="countBadge"><?= $totalSesi ?> pinjaman</span>
            </div>

            <!-- LIST RIWAYAT -->
            <div class="riwayat-list" id="riwayatList">
                <?php foreach ($riwayat as $i => $r):
                    $cc      = $coverColors[$i % count($coverColors)];
                    $emoji   = $r['cover_emoji'] ?? '📖';
                    $selesai = $r['status'] === 'selesai';
                    $nama    = $r['pengarang'] ?? $r['penulis'] ?? '-';
                    $pct     = $selesai ? 100 : 0;
                ?>
                <div class="riwayat-row"
                     data-judul="<?= htmlspecialchars(strtolower($r['judul'])) ?>"
                     data-pengarang="<?= htmlspecialchars(strtolower($nama)) ?>"
                     data-tanggal="<?= htmlspecialchars($r['tanggal_baca'] ?? '') ?>">

                    <!-- Cover mini -->
                    <div class="row-cover" style="--cover-color:<?= $cc ?>;">
                        <?php if (!empty($r['cover'])): ?>
                            <img src="../<?= htmlspecialchars($r['cover']) ?>"
                                 alt="<?= htmlspecialchars($r['judul']) ?>">
                        <?php else: ?>
                            <span><?= $emoji ?></span>
                        <?php endif; ?>
                        <?php if ($selesai): ?>
                            <div class="cover-done"><?= icon('check', 10) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Info -->
                    <div class="row-info">
                        <div class="row-judul"><?= htmlspecialchars($r['judul']) ?></div>
                        <?php if (!empty($r['buku_dihapus'])): ?>
                            <div class="row-pengarang" style="color:#e05c5c;font-size:11px;">⚠️ Buku ini telah dihapus oleh admin</div>
                        <?php else: ?>
                        <div class="row-pengarang"><?= htmlspecialchars($nama) ?> · <?= htmlspecialchars($r['tahun_terbit'] ?? '') ?></div>
                        <div class="row-tags">
                            <span class="buku-kat"><?= htmlspecialchars($r['kategori']) ?></span>
                            <?php if ($selesai): ?>
                                <span class="badge-selesai"><?= icon('check-circle', 11) ?> Selesai</span>
                            <?php else: ?>
                                <span class="badge-aktif"><?= icon('book-open', 11) ?> Sedang Dibaca</span>
                            <?php endif; ?>
                        </div>
                        <!-- Progress bar -->
                        <div class="progress-wrap">
                            <div class="progress-bar">
                                <div class="progress-fill <?= $selesai ? 'done' : 'reading' ?>"
                                     style="width:<?= $selesai ? 100 : 40 ?>%"></div>
                            </div>
                            <span class="progress-label">
                                <?= $r['total_halaman'] ? number_format($r['total_halaman']) . ' hal' : '—' ?>
                                · <?= $selesai ? '100%' : 'Aktif' ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Meta waktu -->
                    <div class="row-meta">
                        <div class="meta-item">
                            <?= icon('calendar', 13) ?>
                            <span>Akses: <?= formatTanggal($r['tanggal_baca']) ?></span>
                        </div>
                        <?php if ($r['tanggal_kembali']): ?>
                        <div class="meta-item">
                            <?= icon('check', 13) ?>
                            <span>Kembali: <?= date('d M Y', strtotime($r['tanggal_kembali'])) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Action -->
                    <div class="row-actions">
                        <?php if (empty($r['buku_dihapus'])): ?>
                        <a href="baca.php?id=<?= $r['buku_id'] ?>" class="btn-lanjut">
                            <?= icon('eye', 14) ?> <?= $selesai ? 'Baca Ulang' : 'Lanjut Baca' ?>
                        </a>
                        <?php endif; ?>
                        <button class="btn-hapus-riwayat" onclick="hapusRiwayat(<?= $r['riwayat_id'] ?>, this)" title="Hapus Riwayat">
                            <?= icon('trash', 14) ?>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- EMPTY STATE -->
            <div class="empty-state" id="emptyState" style="display:none;">
                <?= icon('history', 56) ?>
                <h4>Belum Ada Riwayat</h4>
                <p id="emptyMsg">Mulai membaca buku dan riwayat akan muncul di sini.</p>
                <a href="katalog_ebook.php" class="btn-katalog">
                    <?= icon('search', 15) ?> Jelajahi Katalog
                </a>
            </div>

        </div>
    </div><!-- /content -->
</div><!-- /main -->

<!-- Modal Hapus -->
<div class="modal-overlay" id="modalHapus">
    <div class="modal">
        <div class="modal-icon"><?= icon('trash', 28) ?></div>
        <h3>Hapus Riwayat?</h3>
        <p>Riwayat baca ini akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan.</p>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeModal()">Batal</button>
            <button class="btn-confirm" id="btnConfirmHapus">Hapus</button>
        </div>
    </div>
</div>

<script>
    /* ── Sidebar ── */
    function toggleSidebar() {
        document.querySelector('.sidebar').classList.toggle('active');
        document.querySelector('.overlay').classList.toggle('active');
    }
    function closeSidebar() {
        document.querySelector('.sidebar').classList.remove('active');
        document.querySelector('.overlay').classList.remove('active');
    }

    /* ── Search ── */
    function filterRows(q) {
        q = q.toLowerCase().trim();
        const activeFilter = document.querySelector('.filter-tab.active')?.dataset.filter ?? '';
        applyFilters(q, activeFilter);
    }

    function applyFilters(q, range) {
        const rows = document.querySelectorAll('#riwayatList .riwayat-row');
        let visible = 0;
        const now = Date.now();

        rows.forEach(row => {
            const textMatch = q === '' ||
                row.dataset.judul.includes(q) ||
                row.dataset.pengarang.includes(q);

            let rangeMatch = true;
            if (range !== '') {
                const ts = new Date(row.dataset.tanggal).getTime();
                const diff = now - ts;
                if (range === 'hari_ini') rangeMatch = new Date(ts).toDateString() === new Date().toDateString();
                else if (range === 'minggu') rangeMatch = diff <= 7 * 86400000;
                else if (range === 'bulan')  rangeMatch = diff <= 30 * 86400000;
            }

            const show = textMatch && rangeMatch;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        document.getElementById('countBadge').textContent = visible + ' pinjaman';
        document.getElementById('clearBtn').classList.toggle('show', q.length > 0);

        const empty = document.getElementById('emptyState');
        if (visible === 0) {
            empty.style.display = 'flex';
            document.getElementById('emptyMsg').textContent =
                q ? `Tidak ada riwayat untuk "${q}".` : 'Tidak ada riwayat dalam periode ini.';
        } else {
            empty.style.display = 'none';
        }
    }

    function clearSearch() {
        const inp = document.getElementById('searchInput');
        inp.value = '';
        filterRows('');
        inp.focus();
    }

    /* ── Filter Tab ── */
    function setFilter(range) {
        document.querySelectorAll('.filter-tab').forEach(t => {
            t.classList.toggle('active', (t.dataset.filter ?? '') === range);
        });
        const q = document.getElementById('searchInput').value.toLowerCase().trim();
        applyFilters(q, range);
    }

    document.querySelectorAll('.filter-tab').forEach(btn => {
        const map = {'Semua':'','Hari Ini':'hari_ini','7 Hari':'minggu','30 Hari':'bulan'};
        btn.dataset.filter = map[btn.textContent.trim()] ?? '';
    });

    /* ── Hapus Riwayat ── */
    let targetHapusId = null;
    let targetHapusEl = null;

    function hapusRiwayat(id, btn) {
        targetHapusId = id;
        targetHapusEl = btn.closest('.riwayat-row');
        document.getElementById('modalHapus').classList.add('show');
    }

    document.getElementById('btnConfirmHapus').addEventListener('click', () => {
        if (!targetHapusId) return;
        fetch('ajax/hapus_riwayat.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: `riwayat_id=${targetHapusId}`
        })
        .then(r => r.json())
        .then(d => {
            if (d.success && targetHapusEl) {
                targetHapusEl.style.transition = 'opacity .3s, transform .3s';
                targetHapusEl.style.opacity = '0';
                targetHapusEl.style.transform = 'translateX(20px)';
                setTimeout(() => {
                    targetHapusEl.remove();
                    const remaining = document.querySelectorAll('#riwayatList .riwayat-row:not([style*="display: none"])').length;
                    document.getElementById('countBadge').textContent = remaining + ' pinjaman';
                    if (remaining === 0) {
                        document.getElementById('emptyState').style.display = 'flex';
                        document.getElementById('emptyMsg').textContent = 'Riwayat baca Anda kosong.';
                    }
                }, 300);
            }
            closeModal();
        })
        .catch(() => closeModal());
    });

    function closeModal() {
        document.getElementById('modalHapus').classList.remove('show');
        targetHapusId = null;
        targetHapusEl = null;
    }

    document.getElementById('modalHapus').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
</script>
</body>
</html>