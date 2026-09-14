<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['role']) || !isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
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

// ── Data buku ─────────────────────────────────────────────────────────────────
$bookId = (int)($_GET['id'] ?? 0);
$book   = null;

if ($bookId > 0 && $dbOk) {
    $sql = "SELECT id, judul,
                   COALESCE(penulis, pengarang) AS pengarang,
                   kategori,
                   COALESCE(tahun, tahun_terbit) AS tahun_terbit,
                   COALESCE(deskripsi, '') AS deskripsi,
                   cover_emoji,
                   COALESCE(rating, 0.0) AS rating,
                   COALESCE(total_baca, 0) AS total_baca,
                   file_pdf,
                   COALESCE(isbn, '') AS isbn,
                   COALESCE(bahasa, 'Indonesia') AS bahasa,
                   COALESCE(halaman, 0) AS halaman
            FROM buku
            WHERE id = $bookId
            LIMIT 1";
    $r    = mysqli_query($conn, $sql);
    $book = $r ? mysqli_fetch_assoc($r) : null;
}

$isNotFound = empty($book);

// ── Cek wishlist ──────────────────────────────────────────────────────────────
$inWishlist = false;
if (!$isNotFound && $dbOk) {
    $w = mysqli_query($conn, "SELECT id FROM wishlist WHERE id_anggota=$uid AND id_buku=$bookId LIMIT 1");
    $inWishlist = $w && mysqli_num_rows($w) > 0;
}

// ── Cek akses aktif (peminjaman) ──────────────────────────────────────────────
$hasAkses = false;
if (!$isNotFound && $dbOk) {
    $pa = mysqli_query($conn, "SELECT id FROM peminjaman WHERE id_anggota=$uid AND id_buku=$bookId AND status='aktif' LIMIT 1");
    $hasAkses = $pa && mysqli_num_rows($pa) > 0;
}

// ── Ukuran file PDF ───────────────────────────────────────────────────────────
$fileSizeLabel = '—';
if (!$isNotFound && !empty($book['file_pdf'])) {
    $path = realpath(__DIR__ . '/../' . ltrim($book['file_pdf'], '/'));
    if ($path && file_exists($path)) {
        $bytes = filesize($path);
        $fileSizeLabel = $bytes >= 1048576
            ? number_format($bytes / 1048576, 1) . ' MB'
            : number_format($bytes / 1024, 1)    . ' KB';
    }
}

// ── Flash message ─────────────────────────────────────────────────────────────
$flash = null;
if (!empty($_SESSION['flash_wishlist'])) {
    $flash = $_SESSION['flash_wishlist'];
    unset($_SESSION['flash_wishlist']);
}

// ── Buku terkait (kategori sama) ──────────────────────────────────────────────
$terkait = [];
if (!$isNotFound && $dbOk) {
    $kat  = mysqli_real_escape_string($conn, $book['kategori'] ?? '');
    $rTer = mysqli_query($conn,
        "SELECT id, judul, cover_emoji, COALESCE(penulis,pengarang) AS pengarang
         FROM buku
         WHERE kategori='$kat' AND id<>$bookId
         ORDER BY total_baca DESC LIMIT 4");
    if ($rTer) while ($row = mysqli_fetch_assoc($rTer)) $terkait[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $isNotFound ? 'Tidak Ditemukan' : htmlspecialchars($book['judul']) ?> — Pojok Baca</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=Crimson+Pro:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/anggota/sidebar.css">
<link rel="stylesheet" href="../assets/css/anggota/katalog_ebook.css">
<style>
/* ── RESET & BASE ─────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ── LAYOUT DETAIL ────────────────────────────────────────────────── */
.detail-wrap {
    display: grid;
    grid-template-columns: 260px 1fr;
    gap: 32px;
    align-items: start;
}
@media(max-width:900px) { .detail-wrap { grid-template-columns: 1fr; } }

/* ── COVER ────────────────────────────────────────────────────────── */
.detail-cover-col { position: sticky; top: 24px; }
.detail-cover-box {
    aspect-ratio: 3/4;
    border-radius: 16px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, rgba(15,23,42,.95), rgba(30,41,59,.95));
    border: 1px solid rgba(255,255,255,.07);
    box-shadow: 0 20px 60px rgba(0,0,0,.5);
    font-size: 90px;
}

/* ── COVER QUICK ACTIONS ──────────────────────────────────────────── */
.cover-actions { margin-top: 14px; display: flex; flex-direction: column; gap: 9px; }
.cta-read {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 13px;
    border-radius: 12px;
    background: var(--accent, #6366f1);
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    font-family: 'Sora', sans-serif;
    transition: opacity .2s;
}
.cta-read:hover { opacity: .88; }
.cta-read.locked {
    background: rgba(148,163,184,.15);
    color: var(--muted, #94a3b8);
    pointer-events: none;
    cursor: default;
}
.cta-wish {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 10px;
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,.1);
    background: transparent;
    color: var(--muted, #94a3b8);
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    font-family: 'Sora', sans-serif;
    transition: all .2s;
}
.cta-wish:hover, .cta-wish.active {
    background: rgba(99,102,241,.15);
    border-color: var(--accent, #6366f1);
    color: var(--accent, #6366f1);
}

/* ── DETAIL BODY ──────────────────────────────────────────────────── */
.detail-body { display: flex; flex-direction: column; gap: 20px; }

.detail-cat-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 999px;
    background: rgba(99,102,241,.12);
    color: var(--accent, #6366f1);
    font-size: 11.5px;
    font-weight: 700;
    letter-spacing: .4px;
    width: fit-content;
    font-family: 'Sora', sans-serif;
}

.detail-title {
    font-family: 'Crimson Pro', serif;
    font-size: 2.5rem;
    line-height: 1.1;
    color: var(--text, #f1f5f9);
    font-weight: 600;
}

.detail-author {
    font-size: 14.5px;
    color: var(--muted, #94a3b8);
    font-family: 'Sora', sans-serif;
}
.detail-author strong { color: var(--text, #f1f5f9); font-weight: 500; }

/* ── RATING ROW ───────────────────────────────────────────────────── */
.rating-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.stars-display { font-size: 16px; letter-spacing: 1px; line-height: 1; }
.rating-num { font-size: 15px; font-weight: 700; color: var(--text, #f1f5f9); font-family: 'Sora', sans-serif; }
.rating-sep { color: rgba(255,255,255,.2); }
.rating-count { font-size: 12.5px; color: var(--muted, #94a3b8); font-family: 'Sora', sans-serif; }

/* ── STAT PILLS ───────────────────────────────────────────────────── */
.stat-pills {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.pill {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    border-radius: 10px;
    background: rgba(148,163,184,.07);
    border: 1px solid rgba(255,255,255,.07);
    font-size: 12.5px;
    color: var(--muted, #94a3b8);
    font-family: 'Sora', sans-serif;
}
.pill span { font-weight: 600; color: var(--text, #f1f5f9); }

/* ── ACCESS NOTICE ────────────────────────────────────────────────── */
.access-notice {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 13px;
    font-family: 'Sora', sans-serif;
    font-weight: 500;
}
.access-notice.ok  { background: rgba(52,211,153,.1); border: 1px solid rgba(52,211,153,.25); color: #34d399; }
.access-notice.no  { background: rgba(251,191,36,.08); border: 1px solid rgba(251,191,36,.2); color: #fbbf24; }

/* ── TABS ─────────────────────────────────────────────────────────── */
.detail-tabs {
    display: flex;
    gap: 0;
    border-bottom: 1px solid rgba(255,255,255,.08);
    margin-top: 8px;
}
.tab-btn {
    background: none;
    border: none;
    padding: 12px 20px;
    font-size: 13.5px;
    font-weight: 600;
    color: var(--muted, #94a3b8);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
    font-family: 'Sora', sans-serif;
    transition: color .2s;
}
.tab-btn.active { color: var(--accent, #6366f1); border-bottom-color: var(--accent, #6366f1); }
.tab-btn:hover:not(.active) { color: var(--text, #f1f5f9); }

.tab-panel { display: none; padding-top: 20px; }
.tab-panel.active { display: block; }

/* ── DESKRIPSI ────────────────────────────────────────────────────── */
.desc-text {
    font-size: 14px;
    line-height: 1.85;
    color: var(--muted, #94a3b8);
    max-width: 720px;
    font-family: 'Sora', sans-serif;
}

/* ── DETAIL TABLE ─────────────────────────────────────────────────── */
.info-table { width: 100%; border-collapse: collapse; max-width: 600px; }
.info-table td {
    padding: 11px 0;
    border-bottom: 1px solid rgba(255,255,255,.06);
    font-size: 13.5px;
    vertical-align: top;
    font-family: 'Sora', sans-serif;
}
.info-table td:first-child {
    color: var(--muted, #94a3b8);
    width: 160px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    padding-right: 16px;
}
.info-table td:last-child { color: var(--text, #f1f5f9); }

/* ── BUKU TERKAIT ─────────────────────────────────────────────────── */
.terkait-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 14px;
}
.terkait-card {
    background: rgba(148,163,184,.06);
    border: 1px solid rgba(255,255,255,.07);
    border-radius: 12px;
    overflow: hidden;
    text-decoration: none;
    transition: transform .2s, border-color .2s;
    display: block;
}
.terkait-card:hover { transform: translateY(-3px); border-color: rgba(99,102,241,.4); }
.terkait-cover {
    height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 44px;
    background: rgba(15,23,42,.6);
}
.terkait-info { padding: 10px 12px; }
.terkait-title {
    font-size: 12.5px;
    font-weight: 600;
    color: var(--text, #f1f5f9);
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    font-family: 'Sora', sans-serif;
}
.terkait-author { font-size: 11px; color: var(--muted, #94a3b8); margin-top: 3px; font-family: 'Sora', sans-serif; }

/* ── SECTION HEADER ───────────────────────────────────────────────── */
.sec-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .1em;
    color: var(--muted, #94a3b8);
    margin-bottom: 12px;
    font-family: 'Sora', sans-serif;
}

/* ── FLASH ────────────────────────────────────────────────────────── */
.flash-bar {
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 13px;
    margin-bottom: 20px;
    font-family: 'Sora', sans-serif;
}
.flash-bar.success { background: rgba(52,211,153,.1); color: #34d399; border: 1px solid rgba(52,211,153,.25); }
.flash-bar.info    { background: rgba(99,102,241,.1); color: #818cf8; border: 1px solid rgba(99,102,241,.2); }

/* ── NOT FOUND ────────────────────────────────────────────────────── */
.nf-box { text-align: center; padding: 80px 20px; color: var(--muted, #94a3b8); font-family: 'Sora', sans-serif; }
.nf-box h3 { font-size: 20px; font-weight: 600; color: var(--text, #f1f5f9); margin: 16px 0 8px; }
.nf-box p  { font-size: 14px; max-width: 360px; margin: 0 auto 24px; line-height: 1.7; }
.back-link {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 10px 20px;
    border-radius: 10px;
    background: rgba(99,102,241,.15);
    color: var(--accent, #6366f1);
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    font-family: 'Sora', sans-serif;
}
</style>
</head>
<body>

<?php include '../includes/anggota/sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div class="topbar-left">
      <button class="menu-btn" onclick="toggleSidebar()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
      </button>
      <div>
        <div class="topbar-title">Detail eBook</div>
        <div class="topbar-breadcrumb">Pojok Baca /
          <a href="katalog_ebook.php" style="color:inherit;text-decoration:none">Katalog</a> /
          <span><?= $isNotFound ? 'Tidak Ditemukan' : htmlspecialchars(substr($book['judul'],0,28)) ?></span>
        </div>
      </div>
    </div>
    <div class="topbar-right">
      <div class="user-chip">
        <div class="chip-ava"><?= strtoupper(substr($user['nama_lengkap'],0,1)) ?></div>
        <div>
          <div class="chip-name"><?= htmlspecialchars(explode(' ',$user['nama_lengkap'])[0]) ?></div>
          <div class="chip-role">Member</div>
        </div>
      </div>
    </div>
  </div>

  <div class="content">

    <?php if ($flash): ?>
    <div class="flash-bar <?= htmlspecialchars($flash['tipe']) ?>">
      <?= $flash['tipe']==='success' ? '✅' : 'ℹ️' ?> <?= htmlspecialchars($flash['pesan']) ?>
    </div>
    <?php endif; ?>

    <?php if ($isNotFound): ?>
    <div class="nf-box">
      <div style="font-size:64px;opacity:.25">📚</div>
      <h3>eBook Tidak Ditemukan</h3>
      <p>eBook yang kamu cari tidak tersedia atau telah dihapus dari koleksi.</p>
      <a href="katalog_ebook.php" class="back-link">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M14 7l-5 5 5 5V7z"/></svg>
        Kembali ke Katalog
      </a>
    </div>

    <?php else: ?>

    <div class="detail-wrap">

      <!-- ── KOLOM KIRI: Cover ── -->
      <div class="detail-cover-col">
        <div class="detail-cover-box">
          <?= htmlspecialchars($book['cover_emoji'] ?? '📚') ?>
        </div>

        <div class="cover-actions">
          <?php if ($hasAkses): ?>
          <a href="baca.php?id=<?= $bookId ?>" class="cta-read">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M21 4H3a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h18a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1zM3 6h8v12H3V6zm10 12V6h8v12h-8z"/></svg>
            Baca Sekarang
          </a>
          <?php else: ?>
          <span class="cta-read locked">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
            Perlu Akses
          </span>
          <?php endif; ?>

          <a href="ajax/tambah_wishlist.php?id=<?= $bookId ?>&redirect=detail_ebook.php%3Fid%3D<?= $bookId ?>"
             class="cta-wish <?= $inWishlist ? 'active' : '' ?>">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M17 3H7c-1.1 0-1.99.9-1.99 2L5 21l7-3 7 3V5c0-1.1-.9-2-2-2z"/></svg>
            <?= $inWishlist ? 'Di Wishlist ✓' : 'Tambah Wishlist' ?>
          </a>

          <?php if (!empty($book['file_pdf']) && $hasAkses): ?>
          <a href="../<?= htmlspecialchars(ltrim($book['file_pdf'],'/')) ?>" target="_blank" class="cta-wish">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M5 20h14v-2H5v2zm7-14l5 5h-3v4h-4v-4H7l5-5z"/></svg>
            Unduh PDF
          </a>
          <?php endif; ?>

          <a href="katalog_ebook.php" class="cta-wish">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M14 7l-5 5 5 5V7z"/></svg>
            Kembali ke Katalog
          </a>
        </div>
      </div>

      <!-- ── KOLOM KANAN: Info ── -->
      <div class="detail-body">

        <div class="detail-cat-badge">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58s1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41s-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z"/></svg>
          <?= htmlspecialchars($book['kategori'] ?? '—') ?>
        </div>

        <h1 class="detail-title"><?= htmlspecialchars($book['judul']) ?></h1>

        <div class="detail-author">
          Oleh <strong><?= htmlspecialchars($book['pengarang'] ?? '—') ?></strong>
          <?php if(!empty($book['tahun_terbit'])): ?> · <?= htmlspecialchars($book['tahun_terbit']) ?><?php endif; ?>
        </div>

        <!-- Rating -->
        <div class="rating-row">
          <?php
            $r = (float)($book['rating'] ?? 0);
            $stars = '';
            for ($i=1;$i<=5;$i++) $stars .= $i<=$r ? '⭐' : '☆';
          ?>
          <span class="stars-display"><?= $stars ?></span>
          <span class="rating-num"><?= number_format($r,1) ?></span>
          <span class="rating-sep">·</span>
          <span class="rating-count">dari 5 bintang</span>
        </div>

        <!-- Stat Pills -->
        <div class="stat-pills">
          <div class="pill">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
            <span><?= number_format((int)$book['total_baca']) ?></span> kali dibaca
          </div>
          <?php if(!empty($book['halaman'])&&$book['halaman']>0): ?>
          <div class="pill">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zM6 4h5v8l-2.5-1.5L6 12V4zm0 16V14l2.5-1.5L11 14v6H6zm12 0h-5v-6l2.5 1.5L18 14v6zm0-8h-5V4h5v8z"/></svg>
            <span><?= htmlspecialchars($book['halaman']) ?></span> halaman
          </div>
          <?php endif; ?>
          <?php if(!empty($book['bahasa'])): ?>
          <div class="pill">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12.87 15.07l-2.54-2.51.03-.03c1.74-1.94 2.98-4.17 3.71-6.53H17V4h-7V2H8v2H1v1.99h11.17C11.5 7.92 10.44 9.75 9 11.35 8.07 10.32 7.3 9.19 6.69 8h-2c.73 1.63 1.73 3.17 2.98 4.56l-5.09 5.02L4 19l5-5 3.11 3.11.76-2.04zM18.5 10h-2L12 22h2l1.12-3h4.75L21 22h2l-4.5-12zm-2.62 7l1.62-4.33L19.12 17h-3.24z"/></svg>
            <span><?= htmlspecialchars($book['bahasa']) ?></span>
          </div>
          <?php endif; ?>
          <?php if($fileSizeLabel!=='—'): ?>
          <div class="pill">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm-1 7V3.5L18.5 9H13z"/></svg>
            <span><?= $fileSizeLabel ?></span>
          </div>
          <?php endif; ?>
        </div>

        <!-- Status akses -->
        <?php if($hasAkses): ?>
        <div class="access-notice ok">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1C8.676 1 6 3.676 6 7h2c0-2.206 1.794-4 4-4s4 1.794 4 4v3H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V12c0-1.1-.9-2-2-2h-2V7c0-3.324-2.676-6-6-6zm0 13c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2z"/></svg>
          Kamu memiliki akses aktif — silakan baca sekarang
        </div>
        <?php else: ?>
        <div class="access-notice no">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
          Kamu belum memiliki akses — hubungi admin untuk mendapatkan akses
        </div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="detail-tabs">
          <button class="tab-btn active" data-tab="deskripsi">Deskripsi</button>
          <button class="tab-btn" data-tab="detail">Detail Buku</button>
          <?php if(!empty($terkait)): ?>
          <button class="tab-btn" data-tab="terkait">Buku Terkait</button>
          <?php endif; ?>
        </div>

        <!-- Tab: Deskripsi -->
        <div class="tab-panel active" id="tab-deskripsi">
          <p class="desc-text">
            <?= nl2br(htmlspecialchars($book['deskripsi'] ?: 'Deskripsi belum tersedia untuk eBook ini.')) ?>
          </p>
        </div>

        <!-- Tab: Detail -->
        <div class="tab-panel" id="tab-detail">
          <table class="info-table">
            <tr><td>Judul</td><td><?= htmlspecialchars($book['judul']) ?></td></tr>
            <tr><td>Penulis</td><td><?= htmlspecialchars($book['pengarang'] ?? '—') ?></td></tr>
            <tr><td>Kategori</td><td><?= htmlspecialchars($book['kategori'] ?? '—') ?></td></tr>
            <tr><td>Tahun Terbit</td><td><?= htmlspecialchars($book['tahun_terbit'] ?? '—') ?></td></tr>
            <tr><td>ISBN</td><td><?= htmlspecialchars($book['isbn'] ?: '—') ?></td></tr>
            <tr><td>Bahasa</td><td><?= htmlspecialchars($book['bahasa'] ?: '—') ?></td></tr>
            <tr><td>Jumlah Halaman</td><td><?= $book['halaman'] ? number_format((int)$book['halaman']).' halaman' : '—' ?></td></tr>
            <tr><td>File PDF</td><td><?= !empty($book['file_pdf']) ? '<span style="color:#34d399">✓ Tersedia</span>' : '<span style="color:var(--muted)">Belum diupload</span>' ?></td></tr>
          </table>
        </div>

        <!-- Tab: Terkait -->
        <?php if(!empty($terkait)): ?>
        <div class="tab-panel" id="tab-terkait">
          <p class="sec-label">Ebook dengan kategori sama</p>
          <div class="terkait-grid">
            <?php foreach($terkait as $t): ?>
            <a href="detail_ebook.php?id=<?= $t['id'] ?>" class="terkait-card">
              <div class="terkait-cover"><?= htmlspecialchars($t['cover_emoji']??'📚') ?></div>
              <div class="terkait-info">
                <div class="terkait-title"><?= htmlspecialchars($t['judul']) ?></div>
                <div class="terkait-author"><?= htmlspecialchars($t['pengarang']??'') ?></div>
              </div>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

      </div><!-- /.detail-body -->
    </div><!-- /.detail-wrap -->

    <?php endif; ?>
  </div><!-- /.content -->
</div><!-- /.main -->

<script>
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  const ov = document.getElementById('overlay');
  if (ov) ov.classList.toggle('show');
}

document.querySelectorAll('.tab-btn').forEach(function(btn) {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    const panel = document.getElementById('tab-' + btn.dataset.tab);
    if (panel) panel.classList.add('active');
  });
});
</script>
</body>
</html>