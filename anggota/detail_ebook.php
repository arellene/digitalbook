<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['role']) || !isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$dbOk = isset($conn) && $conn instanceof mysqli;
$uid  = (int) $_SESSION['user_id'];

// ── Data user ──────────────────────────────────────────────────────────────────
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
        'foto_profil'  => null,
    ];
}

// ── Data buku ─────────────────────────────────────────────────────────────────
$bookId = (int)($_GET['id'] ?? 0);
$book   = null;

if ($bookId > 0 && $dbOk) {
    $sql = "SELECT id, judul,
                   COALESCE(penulis, pengarang, '') AS pengarang,
                   penerbit,
                   kategori,
                   COALESCE(tahun, tahun_terbit, '') AS tahun_terbit,
                   COALESCE(deskripsi, '')            AS deskripsi,
                   cover_emoji,
                   cover_img,
                   COALESCE(rating, 0.0)              AS rating,
                   COALESCE(total_baca, 0)            AS total_baca,
                   file_pdf,
                   COALESCE(isbn, '')                 AS isbn,
                   COALESCE(bahasa, 'Indonesia')      AS bahasa,
                   COALESCE(halaman, 0)               AS halaman
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

// ── Cek koleksi ───────────────────────────────────────────────────────────────
$inKoleksi = false;
if (!$isNotFound && $dbOk) {
    $k = mysqli_query($conn, "SELECT id FROM koleksi WHERE id_anggota=$uid AND id_buku=$bookId LIMIT 1");
    $inKoleksi = $k && mysqli_num_rows($k) > 0;
}

// ── Cek akses baca via riwayat_baca ──────────────────────────────────────────
// Status 'sedang_dibaca' atau 'dipinjam' = punya akses
$hasAkses = false;
$statusBaca = null;
$progressBaca = 0;
if (!$isNotFound && $dbOk) {
    $rb = mysqli_query($conn,
        "SELECT status, progress FROM riwayat_baca
         WHERE id_anggota=$uid AND id_buku=$bookId
         ORDER BY created_at DESC LIMIT 1");
    if ($rb && mysqli_num_rows($rb) > 0) {
        $rRow        = mysqli_fetch_assoc($rb);
        $statusBaca  = $rRow['status'];
        $progressBaca= (int)$rRow['progress'];
        $hasAkses    = in_array($statusBaca, ['sedang_dibaca','dipinjam']);
    }
}

// ── Ulasan ────────────────────────────────────────────────────────────────────
$ulasanList = [];
$ulasanSaya = null;
$ulasanPesan = '';
$ulasanTipe  = '';

if (!$isNotFound && $dbOk) {
    // Proses submit/hapus ulasan
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_ulasan'])) {
        if ($_POST['aksi_ulasan'] === 'simpan') {
            $ratingBaru  = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
            $komentarBaru= isset($_POST['komentar']) ? trim($_POST['komentar']) : '';
            if ($ratingBaru < 1 || $ratingBaru > 5) {
                $ulasanPesan = 'Rating harus antara 1–5 bintang.';
                $ulasanTipe  = 'error';
            } else {
                $komEsc = mysqli_real_escape_string($conn, $komentarBaru);
                $komVal = $komentarBaru ? "'$komEsc'" : "NULL";
                $sql = "INSERT INTO ulasan (buku_id, user_id, rating, komentar)
                        VALUES ($bookId, $uid, $ratingBaru, $komVal)
                        ON DUPLICATE KEY UPDATE rating=VALUES(rating), komentar=VALUES(komentar), created_at=NOW()";
                if (mysqli_query($conn, $sql)) {
                    $ulasanPesan = 'Ulasan berhasil disimpan!';
                    $ulasanTipe  = 'success';
                } else {
                    $ulasanPesan = 'Gagal menyimpan ulasan.';
                    $ulasanTipe  = 'error';
                }
            }
        } elseif ($_POST['aksi_ulasan'] === 'hapus') {
            if (mysqli_query($conn, "DELETE FROM ulasan WHERE buku_id=$bookId AND user_id=$uid")) {
                $ulasanPesan = 'Ulasan kamu berhasil dihapus.';
                $ulasanTipe  = 'success';
            } else {
                $ulasanPesan = 'Gagal menghapus ulasan.';
                $ulasanTipe  = 'error';
            }
        }
    }

    // Ulasan milik user (untuk prefill)
    $rSaya = mysqli_query($conn, "SELECT rating, komentar FROM ulasan WHERE buku_id=$bookId AND user_id=$uid LIMIT 1");
    $ulasanSaya = $rSaya ? mysqli_fetch_assoc($rSaya) : null;

    // Semua ulasan
    $rUlasan = mysqli_query($conn, "
        SELECT ul.rating, ul.komentar, ul.created_at, ul.user_id,
               us.nama_lengkap, us.foto_profil
        FROM ulasan ul
        JOIN users us ON us.id = ul.user_id
        WHERE ul.buku_id = $bookId
        ORDER BY ul.created_at DESC");
    if ($rUlasan) while ($row = mysqli_fetch_assoc($rUlasan)) $ulasanList[] = $row;
}

// ── Ukuran file PDF ───────────────────────────────────────────────────────────
$fileSizeLabel = null;
if (!$isNotFound && !empty($book['file_pdf'])) {
    $path = realpath(__DIR__ . '/../' . ltrim($book['file_pdf'], '/'));
    if ($path && file_exists($path)) {
        $bytes = filesize($path);
        $fileSizeLabel = $bytes >= 1048576
            ? number_format($bytes / 1048576, 1) . ' MB'
            : number_format($bytes / 1024, 1) . ' KB';
    }
}

// ── Cover image path ──────────────────────────────────────────────────────────
function coverUrl($img) {
    $img = trim($img ?? '');
    if ($img === '') return null;
    if (preg_match('#^https?://#', $img)) return $img;
    return '../' . ltrim($img, '/');
}

// ── Buku terkait ──────────────────────────────────────────────────────────────
$terkait = [];
if (!$isNotFound && $dbOk && !empty($book['kategori'])) {
    $katEsc = mysqli_real_escape_string($conn, $book['kategori']);
    $rTer   = mysqli_query($conn,
        "SELECT id, judul, cover_emoji, cover_img,
                COALESCE(penulis, pengarang,'') AS pengarang
         FROM buku WHERE kategori='$katEsc' AND id<>$bookId
         ORDER BY total_baca DESC LIMIT 4");
    if ($rTer) while ($r = mysqli_fetch_assoc($rTer)) $terkait[] = $r;
}

// ── Flash message ─────────────────────────────────────────────────────────────
$flash = null;
foreach (['flash_wishlist','flash_koleksi'] as $fk) {
    if (!empty($_SESSION[$fk])) { $flash = $_SESSION[$fk]; unset($_SESSION[$fk]); break; }
}

// ── Rating rata-rata dari tabel ulasan ───────────────────────────────────────
$avgRating   = (float)($book['rating'] ?? 0);
$jmlUlasan   = count($ulasanList);
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
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

/* ── LAYOUT ──────────────────────────────────────────────────────── */
.detail-wrap{display:grid;grid-template-columns:260px 1fr;gap:32px;align-items:start}
@media(max-width:860px){.detail-wrap{grid-template-columns:1fr}}

/* ── COVER COL ───────────────────────────────────────────────────── */
.cover-col{position:sticky;top:20px}
.cover-box{
    aspect-ratio:3/4;border-radius:16px;overflow:hidden;
    display:flex;align-items:center;justify-content:center;
    background:linear-gradient(135deg,rgba(15,23,42,.95),rgba(30,41,59,.95));
    border:1px solid rgba(255,255,255,.07);
    box-shadow:0 20px 60px rgba(0,0,0,.5);
    font-size:90px;
}
.cover-box img{width:100%;height:100%;object-fit:cover;display:block}
.cover-actions{margin-top:13px;display:flex;flex-direction:column;gap:8px}

.btn-read{
    display:flex;align-items:center;justify-content:center;gap:8px;
    padding:13px;border-radius:12px;
    background:var(--accent,#6366f1);color:#fff;
    font-size:14px;font-weight:700;text-decoration:none;
    font-family:'Sora',sans-serif;transition:opacity .2s;
}
.btn-read:hover{opacity:.88}
.btn-read.locked{
    background:rgba(148,163,184,.1);color:var(--muted,#94a3b8);
    pointer-events:none;cursor:default;border:1px solid rgba(255,255,255,.07);
}
.btn-out{
    display:flex;align-items:center;justify-content:center;gap:7px;
    padding:10px;border-radius:12px;
    border:1px solid rgba(255,255,255,.1);background:transparent;
    color:var(--muted,#94a3b8);font-size:13px;font-weight:600;
    text-decoration:none;font-family:'Sora',sans-serif;transition:all .2s;
}
.btn-out:hover,.btn-out.on{
    background:rgba(99,102,241,.13);border-color:var(--accent,#6366f1);
    color:var(--accent,#6366f1);
}

/* ── PROGRESS BAR ────────────────────────────────────────────────── */
.progress-wrap{margin-top:4px}
.progress-label{display:flex;justify-content:space-between;font-size:11px;color:var(--muted,#94a3b8);margin-bottom:5px;font-family:'Sora',sans-serif}
.progress-bar{height:5px;border-radius:99px;background:rgba(255,255,255,.08);overflow:hidden}
.progress-fill{height:100%;border-radius:99px;background:var(--accent,#6366f1);transition:width .4s}

/* ── DETAIL BODY ─────────────────────────────────────────────────── */
.detail-body{display:flex;flex-direction:column;gap:18px}

.cat-badge{
    display:inline-flex;align-items:center;gap:5px;
    padding:5px 12px;border-radius:999px;
    background:rgba(99,102,241,.12);color:var(--accent,#6366f1);
    font-size:11px;font-weight:700;letter-spacing:.4px;
    width:fit-content;font-family:'Sora',sans-serif;
}
.detail-title{
    font-family:'Crimson Pro',serif;font-size:2.4rem;
    line-height:1.1;color:var(--text,#f1f5f9);font-weight:600;
}
.detail-author{font-size:14px;color:var(--muted,#94a3b8);font-family:'Sora',sans-serif}
.detail-author strong{color:var(--text,#f1f5f9);font-weight:500}

/* ── RATING ──────────────────────────────────────────────────────── */
.rating-row{display:flex;align-items:center;gap:9px;flex-wrap:wrap}
.stars{font-size:16px;letter-spacing:1px;line-height:1}
.rating-num{font-size:15px;font-weight:700;color:var(--text,#f1f5f9);font-family:'Sora',sans-serif}
.rating-sep{color:rgba(255,255,255,.2)}
.rating-sub{font-size:12px;color:var(--muted,#94a3b8);font-family:'Sora',sans-serif}

/* ── PILLS ───────────────────────────────────────────────────────── */
.pills{display:flex;gap:9px;flex-wrap:wrap}
.pill{
    display:flex;align-items:center;gap:6px;padding:7px 13px;
    border-radius:10px;background:rgba(148,163,184,.07);
    border:1px solid rgba(255,255,255,.07);
    font-size:12px;color:var(--muted,#94a3b8);font-family:'Sora',sans-serif;
}
.pill strong{color:var(--text,#f1f5f9)}

/* ── STATUS NOTICE ───────────────────────────────────────────────── */
.notice{
    display:flex;align-items:center;gap:10px;padding:11px 15px;
    border-radius:10px;font-size:13px;font-family:'Sora',sans-serif;font-weight:500;
}
.notice.ok {background:rgba(52,211,153,.09);border:1px solid rgba(52,211,153,.22);color:#34d399}
.notice.warn{background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.2);color:#fbbf24}
.notice.done{background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.2);color:#818cf8}

/* ── TABS ────────────────────────────────────────────────────────── */
.tabs{display:flex;border-bottom:1px solid rgba(255,255,255,.08)}
.tab-btn{
    background:none;border:none;padding:11px 18px;font-size:13px;font-weight:600;
    color:var(--muted,#94a3b8);cursor:pointer;border-bottom:2px solid transparent;
    margin-bottom:-1px;font-family:'Sora',sans-serif;transition:color .2s;
}
.tab-btn.active{color:var(--accent,#6366f1);border-bottom-color:var(--accent,#6366f1)}
.tab-btn:hover:not(.active){color:var(--text,#f1f5f9)}
.tab-panel{display:none;padding-top:18px}
.tab-panel.active{display:block}

/* ── DESC ────────────────────────────────────────────────────────── */
.desc-text{font-size:14px;line-height:1.85;color:var(--muted,#94a3b8);max-width:700px;font-family:'Sora',sans-serif}

/* ── INFO TABLE ──────────────────────────────────────────────────── */
.info-tbl{width:100%;border-collapse:collapse;max-width:580px}
.info-tbl td{padding:10px 0;border-bottom:1px solid rgba(255,255,255,.06);font-size:13px;vertical-align:top;font-family:'Sora',sans-serif}
.info-tbl td:first-child{color:var(--muted,#94a3b8);width:150px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding-right:14px}
.info-tbl td:last-child{color:var(--text,#f1f5f9)}

/* ── ULASAN FORM ─────────────────────────────────────────────────── */
.ulasan-form-box{
    border:1px solid rgba(255,255,255,.08);border-radius:12px;
    padding:18px 20px;margin-bottom:20px;
    background:rgba(148,163,184,.04);
}
.ulasan-form-title{font-size:13.5px;font-weight:700;color:var(--text,#f1f5f9);margin-bottom:12px;font-family:'Sora',sans-serif}
.star-picker{display:flex;flex-direction:row-reverse;justify-content:flex-end;gap:3px;margin-bottom:12px}
.star-picker input{display:none}
.star-picker label{font-size:26px;color:rgba(255,255,255,.15);cursor:pointer;transition:color .15s;line-height:1}
.star-picker input:checked ~ label,
.star-picker label:hover,
.star-picker label:hover ~ label{color:#fbbf24}
.ulasan-ta{
    width:100%;min-height:78px;padding:10px 12px;border-radius:8px;
    border:1px solid rgba(255,255,255,.1);background:rgba(15,23,42,.6);
    color:var(--text,#f1f5f9);font-family:'Sora',sans-serif;font-size:13.5px;
    resize:vertical;margin-bottom:12px;outline:none;
}
.ulasan-ta:focus{border-color:var(--accent,#6366f1)}
.ulasan-msg{font-size:12.5px;padding:8px 12px;border-radius:8px;margin-bottom:12px;font-family:'Sora',sans-serif}
.ulasan-msg.success{background:rgba(52,211,153,.1);color:#4ade80}
.ulasan-msg.error{background:rgba(239,68,68,.1);color:#f87171}
.ulasan-actions{display:flex;gap:9px;align-items:center;flex-wrap:wrap}
.btn-save-ulasan{
    display:inline-flex;align-items:center;gap:6px;padding:9px 18px;
    border-radius:8px;border:none;background:var(--accent,#6366f1);
    color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:'Sora',sans-serif;
}
.btn-del-ulasan{
    display:inline-flex;align-items:center;gap:6px;padding:9px 15px;
    border-radius:8px;border:1px solid rgba(248,113,113,.3);background:transparent;
    color:#f87171;font-size:13px;font-weight:600;cursor:pointer;font-family:'Sora',sans-serif;
}

/* ── ULASAN LIST ─────────────────────────────────────────────────── */
.ulasan-item{display:flex;gap:13px;padding:16px 0;border-bottom:1px solid rgba(255,255,255,.06)}
.ulasan-item:last-child{border-bottom:none}
.u-ava{
    width:36px;height:36px;border-radius:50%;
    background:var(--accent,#6366f1);color:#fff;
    display:flex;align-items:center;justify-content:center;
    font-weight:700;flex-shrink:0;font-size:13px;
    overflow:hidden;
}
.u-ava img{width:100%;height:100%;object-fit:cover}
.u-body{flex:1;min-width:0}
.u-head{display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-bottom:3px}
.u-name{font-size:13px;font-weight:700;color:var(--text,#f1f5f9);font-family:'Sora',sans-serif}
.u-mine{font-size:10px;font-weight:700;color:var(--accent,#6366f1);background:rgba(99,102,241,.12);padding:2px 8px;border-radius:999px}
.u-stars{font-size:12px;letter-spacing:1px}
.u-date{font-size:11.5px;color:var(--muted,#94a3b8);margin-bottom:6px;font-family:'Sora',sans-serif}
.u-text{font-size:13.5px;color:var(--text,#f1f5f9);line-height:1.65;margin:0;font-family:'Sora',sans-serif}

/* ── TERKAIT GRID ────────────────────────────────────────────────── */
.terkait-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px}
.terkait-card{
    background:rgba(148,163,184,.06);border:1px solid rgba(255,255,255,.07);
    border-radius:12px;overflow:hidden;text-decoration:none;
    transition:transform .2s,border-color .2s;display:block;
}
.terkait-card:hover{transform:translateY(-3px);border-color:rgba(99,102,241,.4)}
.terkait-cover{height:95px;display:flex;align-items:center;justify-content:center;font-size:42px;background:rgba(15,23,42,.6)}
.terkait-cover img{width:100%;height:100%;object-fit:cover}
.terkait-info{padding:9px 11px}
.terkait-title{font-size:12px;font-weight:600;color:var(--text,#f1f5f9);line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;font-family:'Sora',sans-serif}
.terkait-author{font-size:11px;color:var(--muted,#94a3b8);margin-top:2px;font-family:'Sora',sans-serif}

/* ── FLASH ───────────────────────────────────────────────────────── */
.flash-bar{padding:11px 15px;border-radius:10px;font-size:13px;margin-bottom:18px;font-family:'Sora',sans-serif}
.flash-bar.success{background:rgba(52,211,153,.1);color:#34d399;border:1px solid rgba(52,211,153,.22)}
.flash-bar.info{background:rgba(99,102,241,.1);color:#818cf8;border:1px solid rgba(99,102,241,.2)}

/* ── NOT FOUND ───────────────────────────────────────────────────── */
.nf-box{text-align:center;padding:80px 20px;color:var(--muted,#94a3b8);font-family:'Sora',sans-serif}
.nf-box h3{font-size:20px;font-weight:600;color:var(--text,#f1f5f9);margin:14px 0 8px}
.nf-box p{font-size:14px;max-width:340px;margin:0 auto 22px;line-height:1.7}
.back-link{
    display:inline-flex;align-items:center;gap:7px;padding:10px 20px;
    border-radius:10px;background:rgba(99,102,241,.14);color:var(--accent,#6366f1);
    text-decoration:none;font-size:13px;font-weight:600;font-family:'Sora',sans-serif;
}

/* ── SEC LABEL ───────────────────────────────────────────────────── */
.sec-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--muted,#94a3b8);margin-bottom:11px;font-family:'Sora',sans-serif}
.empty-ul{font-size:13.5px;color:var(--muted,#94a3b8);padding:8px 0;font-family:'Sora',sans-serif}
</style>
</head>
<body>

<?php include '../includes/anggota/sidebar.php'; ?>

<div class="main">
  <!-- TOPBAR -->
  <div class="topbar">
    <div class="topbar-left">
      <button class="menu-btn" onclick="toggleSidebar()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
      </button>
      <div>
        <div class="topbar-title">Detail eBook</div>
        <div class="topbar-breadcrumb">
          Pojok Baca /
          <a href="katalog_ebook.php" style="color:inherit;text-decoration:none">Katalog</a> /
          <span><?= $isNotFound ? 'Tidak ditemukan' : htmlspecialchars(mb_substr($book['judul'],0,30)) ?></span>
        </div>
      </div>
    </div>
    <div class="topbar-right">
      <div class="user-chip">
        <div class="chip-ava">
          <?php if (!empty($user['foto_profil'])): ?>
            <img src="../uploads/profil/<?= htmlspecialchars($user['foto_profil']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%">
          <?php else: ?>
            <?= strtoupper(substr($user['nama_lengkap'],0,1)) ?>
          <?php endif; ?>
        </div>
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
    <!-- NOT FOUND -->
    <div class="nf-box">
      <div style="font-size:60px;opacity:.22">📚</div>
      <h3>eBook Tidak Ditemukan</h3>
      <p>eBook yang kamu cari tidak tersedia atau telah dihapus dari koleksi.</p>
      <a href="katalog_ebook.php" class="back-link">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M14 7l-5 5 5 5V7z"/></svg>
        Kembali ke Katalog
      </a>
    </div>

    <?php else: ?>

    <div class="detail-wrap">

      <!-- ── KIRI: Cover + Tombol ── -->
      <div class="cover-col">
        <div class="cover-box">
          <?php $cUrl = coverUrl($book['cover_img']); ?>
          <?php if ($cUrl): ?>
            <img src="<?= htmlspecialchars($cUrl) ?>" alt="<?= htmlspecialchars($book['judul']) ?>">
          <?php else: ?>
            <?= htmlspecialchars($book['cover_emoji'] ?? '📚') ?>
          <?php endif; ?>
        </div>

        <!-- Progress bar (kalau sedang dibaca) -->
        <?php if ($statusBaca === 'sedang_dibaca' && $progressBaca > 0): ?>
        <div class="progress-wrap" style="margin-top:12px">
          <div class="progress-label">
            <span>Progress Baca</span><span><?= $progressBaca ?>%</span>
          </div>
          <div class="progress-bar">
            <div class="progress-fill" style="width:<?= $progressBaca ?>%"></div>
          </div>
        </div>
        <?php endif; ?>

        <div class="cover-actions">
          <?php if ($hasAkses): ?>
          <a href="baca.php?id=<?= $bookId ?>" class="btn-read">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M21 4H3a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h18a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1zM3 6h8v12H3V6zm10 12V6h8v12h-8z"/></svg>
            <?= $statusBaca === 'sedang_dibaca' ? 'Lanjut Membaca' : 'Baca Sekarang' ?>
          </a>
          <?php else: ?>
          <span class="btn-read locked">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
            Belum Ada Akses
          </span>
          <?php endif; ?>

          <a href="ajax/tambah_wishlist.php?id=<?= $bookId ?>&redirect=detail_ebook.php%3Fid%3D<?= $bookId ?>"
             class="btn-out <?= $inWishlist ? 'on' : '' ?>">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17 3H7c-1.1 0-1.99.9-1.99 2L5 21l7-3 7 3V5c0-1.1-.9-2-2-2z"/></svg>
            <?= $inWishlist ? 'Di Wishlist ✓' : 'Tambah Wishlist' ?>
          </a>

          <a href="ajax/tambah_koleksi.php?id=<?= $bookId ?>&redirect=detail_ebook.php%3Fid%3D<?= $bookId ?>"
             class="btn-out <?= $inKoleksi ? 'on' : '' ?>">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M11.99 18.54l-7.37-5.73L3 14.07l9 7 9-7-1.63-1.27-7.38 5.74zM12 16l7.36-5.73L21 9l-9-7-9 7 1.63 1.27L12 16z"/></svg>
            <?= $inKoleksi ? 'Di Koleksi ✓' : 'Tambah Koleksi' ?>
          </a>

          <?php if (!empty($book['file_pdf']) && $hasAkses): ?>
          <a href="../<?= htmlspecialchars(ltrim($book['file_pdf'],'/')) ?>" target="_blank" class="btn-out">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M5 20h14v-2H5v2zm7-14l5 5h-3v4h-4v-4H7l5-5z"/></svg>
            Unduh PDF <?= $fileSizeLabel ? "($fileSizeLabel)" : '' ?>
          </a>
          <?php endif; ?>

          <a href="katalog_ebook.php" class="btn-out">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M14 7l-5 5 5 5V7z"/></svg>
            Kembali ke Katalog
          </a>
        </div>
      </div>

      <!-- ── KANAN: Info ── -->
      <div class="detail-body">

        <div class="cat-badge">
          <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58s1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41s-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z"/></svg>
          <?= htmlspecialchars($book['kategori'] ?? '—') ?>
        </div>

        <h1 class="detail-title"><?= htmlspecialchars($book['judul']) ?></h1>

        <div class="detail-author">
          Oleh <strong><?= htmlspecialchars($book['pengarang'] ?: '—') ?></strong>
          <?php if(!empty($book['tahun_terbit'])): ?> &nbsp;·&nbsp; <?= htmlspecialchars($book['tahun_terbit']) ?><?php endif; ?>
        </div>

        <!-- Rating -->
        <div class="rating-row">
          <?php
            $rv = (float)$avgRating;
            $st = '';
            for($i=1;$i<=5;$i++) $st .= $i<=$rv ? '⭐' : '☆';
          ?>
          <span class="stars"><?= $st ?></span>
          <span class="rating-num"><?= number_format($rv,1) ?></span>
          <span class="rating-sep">·</span>
          <span class="rating-sub"><?= $jmlUlasan ?> ulasan</span>
        </div>

        <!-- Pills -->
        <div class="pills">
          <div class="pill">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
            <strong><?= number_format((int)$book['total_baca']) ?></strong> kali dibaca
          </div>
          <?php if(!empty($book['halaman'])&&(int)$book['halaman']>0): ?>
          <div class="pill">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/></svg>
            <strong><?= number_format((int)$book['halaman']) ?></strong> halaman
          </div>
          <?php endif; ?>
          <?php if(!empty($book['bahasa'])): ?>
          <div class="pill">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12.87 15.07l-2.54-2.51.03-.03c1.74-1.94 2.98-4.17 3.71-6.53H17V4h-7V2H8v2H1v1.99h11.17C11.5 7.92 10.44 9.75 9 11.35 8.07 10.32 7.3 9.19 6.69 8h-2c.73 1.63 1.73 3.17 2.98 4.56l-5.09 5.02L4 19l5-5 3.11 3.11.76-2.04zM18.5 10h-2L12 22h2l1.12-3h4.75L21 22h2l-4.5-12zm-2.62 7l1.62-4.33L19.12 17h-3.24z"/></svg>
            <strong><?= htmlspecialchars($book['bahasa']) ?></strong>
          </div>
          <?php endif; ?>
          <?php if($fileSizeLabel): ?>
          <div class="pill">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm-1 7V3.5L18.5 9H13z"/></svg>
            <strong><?= $fileSizeLabel ?></strong>
          </div>
          <?php endif; ?>
        </div>

        <!-- Status akses -->
        <?php if ($statusBaca === 'sedang_dibaca'): ?>
        <div class="notice ok">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
          Kamu sedang membaca ebook ini · Progress <?= $progressBaca ?>%
        </div>
        <?php elseif ($statusBaca === 'selesai'): ?>
        <div class="notice done">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
          Kamu sudah selesai membaca ebook ini 🎉
        </div>
        <?php elseif ($statusBaca === 'dipinjam'): ?>
        <div class="notice ok">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1C8.676 1 6 3.676 6 7h2c0-2.206 1.794-4 4-4s4 1.794 4 4v3H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V12c0-1.1-.9-2-2-2h-2V7c0-3.324-2.676-6-6-6zm0 13c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2z"/></svg>
          Ebook ini sedang kamu pinjam — silakan baca sekarang
        </div>
        <?php else: ?>
        <div class="notice warn">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
          Kamu belum memiliki akses — hubungi admin untuk mendapatkan akses
        </div>
        <?php endif; ?>

        <!-- TABS -->
        <div class="tabs">
          <button class="tab-btn active" data-tab="deskripsi">Deskripsi</button>
          <button class="tab-btn" data-tab="detail">Detail Buku</button>
          <button class="tab-btn" data-tab="ulasan">
            Ulasan<?= $jmlUlasan ? " ($jmlUlasan)" : '' ?>
          </button>
          <?php if(!empty($terkait)): ?>
          <button class="tab-btn" data-tab="terkait">Buku Terkait</button>
          <?php endif; ?>
        </div>

        <!-- Tab Deskripsi -->
        <div class="tab-panel active" id="tab-deskripsi">
          <p class="desc-text">
            <?= nl2br(htmlspecialchars($book['deskripsi'] ?: 'Deskripsi belum tersedia untuk eBook ini.')) ?>
          </p>
        </div>

        <!-- Tab Detail -->
        <div class="tab-panel" id="tab-detail">
          <table class="info-tbl">
            <tr><td>Judul</td><td><?= htmlspecialchars($book['judul']) ?></td></tr>
            <tr><td>Penulis</td><td><?= htmlspecialchars($book['pengarang'] ?: '—') ?></td></tr>
            <tr><td>Penerbit</td><td><?= htmlspecialchars($book['penerbit'] ?: '—') ?></td></tr>
            <tr><td>Kategori</td><td><?= htmlspecialchars($book['kategori'] ?: '—') ?></td></tr>
            <tr><td>Tahun Terbit</td><td><?= htmlspecialchars($book['tahun_terbit'] ?: '—') ?></td></tr>
            <tr><td>ISBN</td><td><?= htmlspecialchars($book['isbn'] ?: '—') ?></td></tr>
            <tr><td>Bahasa</td><td><?= htmlspecialchars($book['bahasa'] ?: '—') ?></td></tr>
            <tr><td>Jumlah Halaman</td><td><?= (int)$book['halaman'] > 0 ? number_format((int)$book['halaman']).' halaman' : '—' ?></td></tr>
            <tr><td>File PDF</td><td>
              <?php if (!empty($book['file_pdf'])): ?>
                <span style="color:#34d399">✓ Tersedia</span>
                <?php if ($fileSizeLabel): ?><span style="color:var(--muted,#94a3b8);font-size:12px"> · <?= $fileSizeLabel ?></span><?php endif; ?>
              <?php else: ?>
                <span style="color:var(--muted,#94a3b8)">Belum diupload</span>
              <?php endif; ?>
            </td></tr>
          </table>
        </div>

        <!-- Tab Ulasan -->
        <div class="tab-panel" id="tab-ulasan">
          <!-- Form ulasan -->
          <div class="ulasan-form-box">
            <div class="ulasan-form-title">
              <?= $ulasanSaya ? 'Edit Ulasan Kamu' : 'Tulis Ulasan' ?>
            </div>
            <?php if ($ulasanPesan): ?>
            <div class="ulasan-msg <?= $ulasanTipe ?>"><?= htmlspecialchars($ulasanPesan) ?></div>
            <?php endif; ?>
            <form method="POST" action="detail_ebook.php?id=<?= $bookId ?>#tab-ulasan">
              <input type="hidden" name="aksi_ulasan" value="simpan">
              <div class="star-picker" id="starPicker">
                <?php for($i=5;$i>=1;$i--): ?>
                <input type="radio" name="rating" id="s<?= $i ?>" value="<?= $i ?>"
                  <?= (!empty($ulasanSaya)&&(int)$ulasanSaya['rating']===$i)?'checked':'' ?>>
                <label for="s<?= $i ?>">★</label>
                <?php endfor; ?>
              </div>
              <textarea name="komentar" class="ulasan-ta"
                placeholder="Bagikan pendapatmu tentang buku ini..."><?= htmlspecialchars($ulasanSaya['komentar'] ?? '') ?></textarea>
              <div class="ulasan-actions">
                <button type="submit" class="btn-save-ulasan">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                  <?= $ulasanSaya ? 'Simpan Perubahan' : 'Kirim Ulasan' ?>
                </button>
              </div>
            </form>
            <?php if ($ulasanSaya): ?>
            <form method="POST" action="detail_ebook.php?id=<?= $bookId ?>#tab-ulasan" style="margin-top:9px"
                  onsubmit="return confirm('Yakin hapus ulasan kamu?')">
              <input type="hidden" name="aksi_ulasan" value="hapus">
              <button type="submit" class="btn-del-ulasan">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                Hapus Ulasan
              </button>
            </form>
            <?php endif; ?>
          </div>

          <!-- Daftar ulasan -->
          <?php if (empty($ulasanList)): ?>
          <p class="empty-ul">Belum ada ulasan. Jadilah yang pertama! 😊</p>
          <?php else: ?>
            <?php foreach ($ulasanList as $u):
              $mine = ((int)$u['user_id'] === $uid);
              $uSt  = '';
              for($i=1;$i<=5;$i++) $uSt .= $i<=(int)$u['rating'] ? '⭐' : '☆';
            ?>
            <div class="ulasan-item">
              <div class="u-ava">
                <?php
                  $fp = $u['foto_profil'] ?? '';
                  $fpPath = __DIR__ . '/../uploads/profil/' . $fp;
                  if ($fp && is_file($fpPath)):
                ?>
                  <img src="../uploads/profil/<?= htmlspecialchars($fp) ?>" alt="">
                <?php else: ?>
                  <?= strtoupper(substr($u['nama_lengkap'],0,1)) ?>
                <?php endif; ?>
              </div>
              <div class="u-body">
                <div class="u-head">
                  <span class="u-name"><?= htmlspecialchars($u['nama_lengkap']) ?></span>
                  <?php if ($mine): ?><span class="u-mine">Ulasan Anda</span><?php endif; ?>
                  <span class="u-stars"><?= $uSt ?></span>
                </div>
                <div class="u-date"><?= date('d F Y · H:i', strtotime($u['created_at'])) ?> WIB</div>
                <?php if (!empty($u['komentar'])): ?>
                <p class="u-text"><?= nl2br(htmlspecialchars($u['komentar'])) ?></p>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Tab Terkait -->
        <?php if (!empty($terkait)): ?>
        <div class="tab-panel" id="tab-terkait">
          <p class="sec-label">Ebook dengan kategori sama</p>
          <div class="terkait-grid">
            <?php foreach ($terkait as $t):
              $tcUrl = coverUrl($t['cover_img'] ?? '');
            ?>
            <a href="detail_ebook.php?id=<?= $t['id'] ?>" class="terkait-card">
              <div class="terkait-cover">
                <?php if ($tcUrl): ?>
                  <img src="<?= htmlspecialchars($tcUrl) ?>" alt="">
                <?php else: ?>
                  <?= htmlspecialchars($t['cover_emoji'] ?? '📚') ?>
                <?php endif; ?>
              </div>
              <div class="terkait-info">
                <div class="terkait-title"><?= htmlspecialchars($t['judul']) ?></div>
                <div class="terkait-author"><?= htmlspecialchars($t['pengarang'] ?? '') ?></div>
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
  const sb = document.getElementById('sidebar');
  const ov = document.getElementById('overlay');
  if (sb) sb.classList.toggle('open');
  if (ov) ov.classList.toggle('show');
}

// Tab switch
document.querySelectorAll('.tab-btn').forEach(function(btn) {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    const panel = document.getElementById('tab-' + btn.dataset.tab);
    if (panel) panel.classList.add('active');
  });
});

// Buka tab ulasan otomatis setelah submit
if (window.location.hash === '#tab-ulasan') {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  const btn = document.querySelector('.tab-btn[data-tab="ulasan"]');
  const panel = document.getElementById('tab-ulasan');
  if (btn) btn.classList.add('active');
  if (panel) panel.classList.add('active');
}
</script>
</body>
</html>