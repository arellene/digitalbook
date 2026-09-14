<?php
// ============================================================
//  ulasan_buku.php  –  Halaman Ulasan Buku
//  Pojok Baca — Portal Anggota
//  Require: config/database.php, includes/anggota/sidebar.php
// ============================================================

session_start();
require_once '../config/database.php';

// ── Auth Guard ───────────────────────────────────────────────
if (!isset($_SESSION['role'])) {
    header('Location: ../index.php');
    exit();
}

$isGuest = ($_SESSION['role'] === 'guest');
$dbOk    = isset($conn) && $conn instanceof mysqli;

// ── Ambil data user (sama persis seperti dashboard.php) ──────
$user = null;
if (!$isGuest && $dbOk) {
    $uid = (int) $_SESSION['user_id'];
    $q   = mysqli_query($conn, "SELECT * FROM users WHERE id = $uid LIMIT 1");
    $user = $q ? mysqli_fetch_assoc($q) : null;
}
if (empty($user)) {
    $user = [
        'nama_lengkap' => $_SESSION['nama_lengkap'] ?? ($isGuest ? 'Tamu' : 'Pengguna'),
        'username'     => $_SESSION['username']     ?? ($isGuest ? 'guest' : 'user'),
        'role'         => $_SESSION['role']         ?? 'anggota',
    ];
}

$user_id = $isGuest ? 0 : (int)($_SESSION['user_id'] ?? 0);

// ── Ambil buku_id dari URL ───────────────────────────────────
$buku_id = isset($_GET['buku_id']) ? (int)$_GET['buku_id'] : 0;
if ($buku_id <= 0) {
    header('Location: katalog_ebook.php');
    exit();
}

// ── Proses form POST ─────────────────────────────────────────
$pesan = '';
$tipe  = '';

if (!$isGuest && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $dbOk) {

    if ($_POST['aksi'] === 'simpan') {
        $rating   = isset($_POST['rating'])   ? (int)$_POST['rating']   : 0;
        $komentar = isset($_POST['komentar']) ? trim($_POST['komentar']) : '';

        if ($rating < 1 || $rating > 5) {
            $pesan = 'Rating harus antara 1–5 bintang.';
            $tipe  = 'error';
        } else {
            $komentar_esc = mysqli_real_escape_string($conn, $komentar);
            $sql = "INSERT INTO ulasan (buku_id, user_id, rating, komentar)
                    VALUES ($buku_id, $user_id, $rating, " . ($komentar ? "'$komentar_esc'" : "NULL") . ")
                    ON DUPLICATE KEY UPDATE
                        rating    = VALUES(rating),
                        komentar  = VALUES(komentar),
                        created_at = NOW()";
            if (mysqli_query($conn, $sql)) {
                $pesan = 'Ulasan berhasil disimpan!';
                $tipe  = 'sukses';
            } else {
                $pesan = 'Gagal menyimpan ulasan.';
                $tipe  = 'error';
            }
        }
    }

    if ($_POST['aksi'] === 'hapus') {
        $sql = "DELETE FROM ulasan WHERE buku_id = $buku_id AND user_id = $user_id";
        if (mysqli_query($conn, $sql)) {
            $pesan = 'Ulasan Anda berhasil dihapus.';
            $tipe  = 'sukses';
        } else {
            $pesan = 'Gagal menghapus ulasan.';
            $tipe  = 'error';
        }
    }
}

// ── Ambil data buku ──────────────────────────────────────────
$buku = null;
if ($dbOk) {
    $r = mysqli_query($conn, "SELECT id, judul,
                COALESCE(penulis,pengarang) AS pengarang,
                kategori,
                COALESCE(tahun,tahun_terbit) AS tahun_terbit,
                cover_img, cover_emoji
           FROM buku WHERE id = $buku_id LIMIT 1");
    if ($r) $buku = mysqli_fetch_assoc($r);
}
if (!$buku) {
    // fallback agar halaman tetap render saat dev / db kosong
    $buku = ['id'=>$buku_id,'judul'=>'Judul Buku','pengarang'=>'Pengarang',
             'kategori'=>'Kategori','tahun_terbit'=>'-','cover_img'=>'','cover_emoji'=>'📚'];
}

// ── Ulasan milik user yang login ─────────────────────────────
$ulasanSaya = null;
if (!$isGuest && $dbOk) {
    $r = mysqli_query($conn,
        "SELECT u.*, us.nama_lengkap AS nama_user
           FROM ulasan u
           JOIN users us ON us.id = u.user_id
          WHERE u.buku_id = $buku_id AND u.user_id = $user_id
          LIMIT 1");
    if ($r) $ulasanSaya = mysqli_fetch_assoc($r);
}

// ── Semua ulasan ─────────────────────────────────────────────
$semuaUlasan = [];
if ($dbOk) {
    $r = mysqli_query($conn,
        "SELECT u.*, us.nama_lengkap AS nama_user
           FROM ulasan u
           JOIN users us ON us.id = u.user_id
          WHERE u.buku_id = $buku_id
          ORDER BY u.created_at DESC");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $semuaUlasan[] = $row;
}

// ── Statistik rating ─────────────────────────────────────────
$stat = ['total'=>0,'rata_rata'=>0,'bintang5'=>0,'bintang4'=>0,'bintang3'=>0,'bintang2'=>0,'bintang1'=>0];
if ($dbOk) {
    $r = mysqli_query($conn,
        "SELECT COUNT(*) AS total, ROUND(AVG(rating),1) AS rata_rata,
                SUM(rating=5) AS bintang5, SUM(rating=4) AS bintang4,
                SUM(rating=3) AS bintang3, SUM(rating=2) AS bintang2,
                SUM(rating=1) AS bintang1
           FROM ulasan WHERE buku_id = $buku_id");
    if ($r) $stat = mysqli_fetch_assoc($r) + $stat;
}

// ── Helper: render bintang read-only ────────────────────────
function renderBintang(float $val, string $extra = ''): string {
    $html = "<span class=\"stars-display $extra\">";
    for ($i = 1; $i <= 5; $i++) {
        if ($val >= $i)           $html .= '<span class="s-full">★</span>';
        elseif ($val >= $i - 0.5) $html .= '<span class="s-half">★</span>';
        else                       $html .= '<span class="s-empty">☆</span>';
    }
    return $html . '</span>';
}

// ── SVG ICON HELPER (same as dashboard.php) ─────────────────
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
        'lock'         => '<path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>',
        'user-plus'    => '<path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>',
        'sign-in'      => '<path d="M11 7L9.6 8.4l2.6 2.6H2v2h10.2l-2.6 2.6L11 17l5-5-5-5zm9 12h-8v2h8c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-8v2h8v14z"/>',
        'sign-out'     => '<path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>',
        'user'         => '<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>',
        'check-circle' => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>',
        'bell'         => '<path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/>',
        'bars'         => '<path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>',
        'arrow-left'   => '<path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>',
        'pencil'       => '<path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>',
        'trash'        => '<path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>',
        'message-sq'   => '<path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>',
        'user-lock'    => '<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-1.5 0-4.5.75-5.99 2.25C7.18 17.38 9.32 18 12 18c.36 0 .71-.02 1.05-.05-.03-.3-.05-.6-.05-.95 0-1.87.85-3.54 2.18-4.67C14.08 12.11 12.63 14 12 14zm6 1c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3zm0 4.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>',
    ];
    $path = $icons[$name] ?? '<circle cx="12" cy="12" r="10"/>';
    return "<svg xmlns='http://www.w3.org/2000/svg' width='{$size}' height='{$size}' viewBox='0 0 24 24' fill='currentColor'{$s}>{$path}</svg>";
}

$active_menu = 'katalog'; // sorot Katalog di sidebar
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ulasan — <?= htmlspecialchars($buku['judul']) ?> | Pojok Baca</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/anggota/dashboard.css">
<link rel="stylesheet" href="../assets/css/anggota/sidebar.css">
<link rel="stylesheet" href="../assets/css/anggota/ulasan_buku.css">
<style>
/* ══ RATING BINTANG ══════════════════════════════════════════ */
.rating-stars {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    gap: 4px !important;
    margin: 8px 0 2px !important;
}
.rating-stars input[type="radio"] {
    display: none !important;
}
.rating-stars label {
    font-size: 34px !important;
    line-height: 1 !important;
    color: #374151 !important;
    cursor: pointer !important;
    transition: color 0.1s, transform 0.1s !important;
    user-select: none !important;
    float: none !important;
    display: inline-block !important;
}
.rating-stars label:hover {
    transform: scale(1.2) !important;
}
.rating-stars label.lit {
    color: #fbbf24 !important;
    text-shadow: 0 0 8px rgba(251,191,36,.4) !important;
}

/* ══ FORM AREA ════════════════════════════════════════════════ */
.rating-input-group {
    margin-bottom: 20px !important;
}
.form-label {
    display: block !important;
    font-size: 13px !important;
    font-weight: 600 !important;
    color: var(--muted, #9ca3af) !important;
    letter-spacing: .5px !important;
    text-transform: uppercase !important;
    margin-bottom: 6px !important;
}
.rating-hint-txt {
    font-size: 12.5px !important;
    color: var(--muted, #9ca3af) !important;
    margin: 6px 0 0 !important;
    min-height: 18px !important;
}
.form-group {
    margin-bottom: 18px !important;
}
.form-group textarea,
#komentar {
    width: 100% !important;
    background: rgba(255,255,255,.05) !important;
    border: 1.5px solid rgba(255,255,255,.12) !important;
    border-radius: 10px !important;
    color: #e5e7eb !important;
    font-family: 'Poppins', sans-serif !important;
    font-size: 13.5px !important;
    padding: 12px 14px !important;
    resize: vertical !important;
    outline: none !important;
    transition: border-color .2s !important;
    box-sizing: border-box !important;
}
.form-group textarea:focus,
#komentar:focus {
    border-color: var(--accent, #6366f1) !important;
    background: rgba(255,255,255,.08) !important;
}
.form-group textarea::placeholder,
#komentar::placeholder {
    color: #6b7280 !important;
}

/* ══ TOMBOL ══════════════════════════════════════════════════ */
.btn-row {
    display: flex !important;
    gap: 10px !important;
    align-items: center !important;
    flex-wrap: wrap !important;
    margin-top: 4px !important;
}
.btn-save {
    display: inline-flex !important;
    align-items: center !important;
    gap: 7px !important;
    padding: 10px 22px !important;
    background: var(--accent, #6366f1) !important;
    color: #fff !important;
    border: none !important;
    border-radius: 9px !important;
    font-family: 'Poppins', sans-serif !important;
    font-size: 13.5px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    transition: background .18s, transform .1s, box-shadow .18s !important;
    text-decoration: none !important;
    box-shadow: 0 4px 14px rgba(99,102,241,.3) !important;
}
.btn-save:hover {
    background: #4f46e5 !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 6px 18px rgba(99,102,241,.4) !important;
}
.btn-save:active {
    transform: translateY(0) !important;
}
.btn-danger {
    display: inline-flex !important;
    align-items: center !important;
    gap: 7px !important;
    padding: 9px 18px !important;
    background: transparent !important;
    color: #f87171 !important;
    border: 1.5px solid rgba(248,113,113,.35) !important;
    border-radius: 9px !important;
    font-family: 'Poppins', sans-serif !important;
    font-size: 13px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    transition: background .18s, border-color .18s !important;
}
.btn-danger:hover {
    background: rgba(248,113,113,.1) !important;
    border-color: #f87171 !important;
}

/* ══ NOTIFIKASI ══════════════════════════════════════════════ */
.notif {
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
    padding: 12px 16px !important;
    border-radius: 10px !important;
    font-size: 13.5px !important;
    font-weight: 500 !important;
    margin-bottom: 16px !important;
}
.notif.sukses {
    background: rgba(52,211,153,.12) !important;
    border: 1px solid rgba(52,211,153,.3) !important;
    color: #34d399 !important;
}
.notif.error {
    background: rgba(248,113,113,.12) !important;
    border: 1px solid rgba(248,113,113,.3) !important;
    color: #f87171 !important;
}
</style>
</head>
<body>

<?php require_once '../includes/anggota/sidebar.php'; ?>

<!-- ══ MAIN ════════════════════════════════════════════════════ -->
<div class="main">

    <!-- ── TOPBAR ── -->
    <div class="topbar">
        <div class="topbar-left">
            <button class="menu-btn" onclick="toggleSidebar()">
                <?= icon('bars', 20) ?>
            </button>
            <div>
                <div class="topbar-title">Ulasan Buku</div>
                <div class="topbar-breadcrumb">
                    Pojok Baca /
                    <a href="katalog_ebook.php" style="color:var(--muted)">Katalog</a> /
                    <span><?= htmlspecialchars(mb_strimwidth($buku['judul'], 0, 28, '…')) ?></span>
                </div>
            </div>
        </div>
        <div class="topbar-right">
            <div class="user-chip">
                <div class="chip-ava" style="overflow:hidden;">
                    <?php if (!empty($user['foto_profil']) && is_file(__DIR__ . '/../uploads/profil/' . $user['foto_profil'])): ?>
                        <img src="../uploads/profil/<?= htmlspecialchars($user['foto_profil']) ?>?v=<?= time() ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                        <?= strtoupper(substr($user['nama_lengkap'], 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="chip-name"><?= htmlspecialchars(explode(' ', $user['nama_lengkap'])[0]) ?></div>
                    <div class="chip-role"><?= $isGuest ? 'Tamu' : 'Member' ?></div>
                </div>
            </div>
        </div>
    </div><!-- /topbar -->

    <!-- ── CONTENT ── -->
    <div class="content">

        <!-- ① KARTU INFO BUKU -->
        <div class="buku-card anim anim-d1">
            <div class="buku-cover-thumb"
                 style="background:linear-gradient(135deg,#1a1a2e,#0f3460);">
                <?php if (!empty($buku['cover_img'])): ?>
                    <img src="../<?= htmlspecialchars($buku['cover_img']) ?>"
                         alt="<?= htmlspecialchars($buku['judul']) ?>">
                <?php else: ?>
                    <?= htmlspecialchars($buku['cover_emoji'] ?? '📚') ?>
                <?php endif; ?>
            </div>
            <div class="buku-info">
                <h1><?= htmlspecialchars($buku['judul']) ?></h1>
                <?php if (!empty($buku['kategori'])): ?>
                <span class="buku-badge-genre"><?= htmlspecialchars($buku['kategori']) ?></span>
                <?php endif; ?>
                <div class="buku-meta-row">
                    <?php if (!empty($buku['pengarang'])): ?>
                    <span class="buku-meta-item">
                        <?= icon('pencil', 13) ?>
                        <?= htmlspecialchars($buku['pengarang']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($buku['tahun_terbit'])): ?>
                    <span class="buku-meta-item">
                        <?= icon('history', 13) ?>
                        <?= htmlspecialchars($buku['tahun_terbit']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($stat['total'] > 0): ?>
                    <span class="buku-meta-item">
                        <?= icon('star', 13, 'color:#fbbf24') ?>
                        <?= number_format((float)$stat['rata_rata'], 1) ?>
                        (<?= $stat['total'] ?> ulasan)
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <a href="katalog_ebook.php?search=<?= urlencode($buku['judul']) ?>"
               class="btn-secondary"
               style="margin-left:auto;white-space:nowrap;flex-shrink:0;">
                <?= icon('arrow-left', 14) ?> Katalog
            </a>
        </div>

        <!-- ② NOTIFIKASI -->
        <?php if ($pesan): ?>
        <div class="notif <?= htmlspecialchars($tipe) ?>">
            <?= $tipe === 'sukses' ? icon('check-circle', 18) : icon('lock', 18) ?>
            <?= htmlspecialchars($pesan) ?>
        </div>
        <?php endif; ?>

        <!-- ④ FORM TULIS / EDIT ULASAN -->
        <?php if (!$isGuest): ?>
        <div class="panel-card anim anim-d3">
            <div class="panel-heading">
                <?= icon('pencil', 16) ?>
                <?= $ulasanSaya ? 'Edit Ulasan Anda' : 'Tulis Ulasan' ?>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="aksi" value="simpan">

                <!-- Rating bintang interaktif -->
                <div class="rating-input-group">
                    <span class="form-label">Rating</span>
                    <div class="rating-stars" id="ratingStars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <input type="radio" name="rating" id="b<?= $i ?>" value="<?= $i ?>"
                            <?= (isset($ulasanSaya['rating']) && (int)$ulasanSaya['rating'] === $i) ? 'checked' : '' ?>>
                        <label for="b<?= $i ?>" title="<?= $i ?> Bintang">★</label>
                        <?php endfor; ?>
                    </div>
                    <p class="rating-hint-txt" id="ratingHint">
                        <?= $ulasanSaya
                            ? (['','Sangat Buruk','Buruk','Cukup','Bagus','Sangat Bagus'][(int)$ulasanSaya['rating']])
                            : 'Pilih rating Anda' ?>
                    </p>
                </div>

                <div class="form-group">
                    <label class="form-label" for="komentar">
                        Komentar <span style="font-weight:400;color:var(--muted)">(opsional)</span>
                    </label>
                    <textarea name="komentar" id="komentar" rows="4" maxlength="1000"
                              placeholder="Bagikan pendapat Anda tentang buku ini…"
                    ><?= htmlspecialchars($ulasanSaya['komentar'] ?? '') ?></textarea>
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn-save">
                        <?= icon('check-circle', 15) ?>
                        <?= $ulasanSaya ? 'Simpan Perubahan' : 'Kirim Ulasan' ?>
                    </button>
                </div>
            </form>

            <?php if ($ulasanSaya): ?>
            <form method="POST" action="" style="margin-top:10px"
                  onsubmit="return confirm('Yakin ingin menghapus ulasan Anda?')">
                <input type="hidden" name="aksi" value="hapus">
                <button type="submit" class="btn-danger">
                    <?= icon('trash', 14) ?> Hapus Ulasan Saya
                </button>
            </form>
            <?php endif; ?>
        </div>

        <?php else: /* guest */ ?>
        <div class="panel-card anim anim-d3"
             style="background:linear-gradient(135deg,rgba(201,168,76,.08),rgba(201,168,76,.03));
                    border-color:rgba(201,168,76,.2);">
            <div class="panel-heading">
                <?= icon('lock', 16, 'color:var(--gold)') ?>
                <span style="color:var(--gold)">Tulis Ulasan</span>
            </div>
            <p style="font-size:13.5px;color:var(--muted);margin-bottom:16px;">
                Daftar atau masuk untuk menulis ulasan dan memberikan rating.
            </p>
            <div class="btn-row">
                <a href="../auth/register.php" class="btn-save">
                    <?= icon('user-plus', 15) ?> Daftar Gratis
                </a>
                <a href="../index.php"
                   style="display:inline-flex;align-items:center;gap:6px;
                          padding:9px 20px;border-radius:8px;font-size:13px;font-weight:600;
                          color:var(--muted);border:1px solid var(--border);">
                    <?= icon('sign-in', 14) ?> Login
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- ⑤ SEMUA ULASAN -->
        <div class="panel-card anim anim-d4">
            <div class="panel-heading">
                <?= icon('message-sq', 16) ?> Semua Ulasan
                <?php if ($stat['total'] > 0): ?>
                <span style="font-size:12px;font-weight:500;color:var(--muted);margin-left:4px;">
                    (<?= $stat['total'] ?>)
                </span>
                <?php endif; ?>
            </div>

            <?php if (empty($semuaUlasan)): ?>
            <div class="ulasan-empty">
                Belum ada ulasan untuk buku ini. Jadilah yang pertama! ✨
            </div>
            <?php else: ?>
            <div class="ulasan-list">
                <?php foreach ($semuaUlasan as $u):
                    $mine    = (!$isGuest && (int)$u['user_id'] === $user_id);
                    $inisial = strtoupper(mb_substr($u['nama_user'], 0, 1));
                    $tgl     = date('d M Y, H:i', strtotime($u['created_at']));
                ?>
                <div class="ulasan-item <?= $mine ? 'mine' : '' ?>">
                    <?php if ($mine): ?>
                    <span class="mine-tag">Ulasan Anda</span>
                    <?php endif; ?>

                    <div class="ulasan-header">
                        <div class="u-avatar"><?= htmlspecialchars($inisial) ?></div>
                        <div class="u-meta">
                            <div class="u-nama"><?= htmlspecialchars($u['nama_user']) ?></div>
                            <div class="u-tgl"><?= $tgl ?></div>
                        </div>
                        <?= renderBintang((float)$u['rating']) ?>
                    </div>

                    <?php if (!empty($u['komentar'])): ?>
                    <p class="u-komentar"><?= nl2br(htmlspecialchars($u['komentar'])) ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /content -->
</div><!-- /main -->

<!-- TOAST (notifikasi guest, sama seperti dashboard) -->
<div class="toast" id="toast">
    <?= icon('user-lock', 18, 'color:var(--accent)') ?>
    <span>Fitur ini membutuhkan akun.
        <a href="../auth/register.php">Daftar</a> atau
        <a href="../index.php">Login</a>.
    </span>
</div>

<script>
// ── Sidebar toggle (sama persis dashboard) ──────────────────
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

// ── Toast ────────────────────────────────────────────────────
let toastTimer;
function showToast(e) {
    e && e.preventDefault();
    const t = document.getElementById('toast');
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 4000);
}

// ── Rating bintang ────────────────────────────────────────────
(function () {
    const labels = ['','Sangat Buruk 😞','Buruk 😕','Cukup 😐','Bagus 😊','Sangat Bagus 🤩'];
    const hint   = document.getElementById('ratingHint');
    const wrap   = document.getElementById('ratingStars');
    if (!wrap) return;

    const lbls = Array.from(wrap.querySelectorAll('label'));

    function getSelected() {
        const checked = wrap.querySelector('input[type="radio"]:checked');
        return checked ? parseInt(checked.value) : 0;
    }

    function paint(n) {
        lbls.forEach(lbl => {
            const v = parseInt(lbl.getAttribute('for').replace('b', ''));
            if (n > 0 && v <= n) lbl.classList.add('lit');
            else                  lbl.classList.remove('lit');
        });
    }

    paint(getSelected());

    lbls.forEach(lbl => {
        const val = parseInt(lbl.getAttribute('for').replace('b', ''));

        lbl.addEventListener('mouseenter', () => {
            paint(val);
            if (hint) hint.textContent = labels[val];
        });
        lbl.addEventListener('mouseleave', () => {
            const sel = getSelected();
            paint(sel);
            if (hint) hint.textContent = sel ? labels[sel] : 'Pilih rating Anda';
        });
        lbl.addEventListener('click', () => {
            setTimeout(() => {
                const sel = getSelected();
                paint(sel);
                if (hint) hint.textContent = sel ? labels[sel] : 'Pilih rating Anda';
            }, 0);
        });
    });
})();
</script>
</body>
</html>