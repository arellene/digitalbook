<?php
// includes/anggota/topnav.php
// Include SETELAH $conn, $user, $isGuest, $active_menu tersedia (sama seperti sidebar.php).
// Variabel opsional sebelum include: $topbar_title, $topbar_breadcrumb, $topbar_search (true/false)

$isGuest     = $isGuest ?? false;
$active_menu = $active_menu ?? '';
$_tn_title      = $topbar_title      ?? 'Beranda';
$_tn_breadcrumb = $topbar_breadcrumb ?? 'Beranda';
$_tn_search     = $topbar_search     ?? false;

// ── Hitung notifikasi belum dibaca ─────────────────────────────────────────
$_tnUnread = 0;
$_tnUid = (int) ($uid ?? $_SESSION['user_id'] ?? 0);
if (!$isGuest && $_tnUid > 0 && isset($conn) && $conn instanceof mysqli) {
    $tblCheck = mysqli_query($conn, "SHOW TABLES LIKE 'notifikasi'");
    if ($tblCheck && mysqli_num_rows($tblCheck) > 0) {
        $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM notifikasi WHERE user_id = $_tnUid AND is_read = 0");
        if ($r) $_tnUnread = (int) mysqli_fetch_assoc($r)['c'];
    }
}

$_tnFotoPath  = (!$isGuest && !empty($user['foto_profil']) && is_file(__DIR__ . '/../../uploads/profil/' . $user['foto_profil']))
    ? '../uploads/profil/' . $user['foto_profil'] . '?v=' . time()
    : null;
$_tnInisial   = strtoupper(substr($user['nama_lengkap'] ?? 'T', 0, 1));
$_tnNamaDepan = htmlspecialchars(explode(' ', $user['nama_lengkap'] ?? 'Tamu')[0]);
?>
<div class="tn-overlay" id="tnOverlay"></div>

<nav class="topnav">
  <div class="topnav-inner">

    <a href="dashboard.php" class="topnav-brand" aria-label="Pojok Baca">
      <img src="../assets/img/logo/logo-pojokbaca-dark.svg" alt="Pojok Baca" class="topnav-logo" width="162" height="32">
    </a>

    <div class="topnav-links">
      <a href="dashboard.php" class="topnav-link <?= $active_menu === 'dashboard' ? 'is-active' : '' ?>">
        <?= icon('house', 15) ?> Beranda
      </a>
      <a href="katalog_ebook.php" class="topnav-link <?= $active_menu === 'katalog' ? 'is-active' : '' ?>">
        <?= icon('book-open', 15) ?> Katalog eBook
      </a>
      <a href="kategori.php" class="topnav-link <?= $active_menu === 'kategori' ? 'is-active' : '' ?>">
        <?= icon('tag', 15) ?> Kategori
      </a>

      <?php if (!$isGuest): ?>
      <button type="button" id="tnActToggle" aria-expanded="false" aria-controls="tnActPanel"
              class="topnav-link topnav-act-toggle <?= in_array($active_menu, ['koleksi','riwayat','wishlist']) ? 'is-active' : '' ?>">
        <?= icon('layers', 15) ?> Aktivitas
        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" class="topnav-chevron"><path d="M7 10l5 5 5-5z"/></svg>
      </button>
      <?php endif; ?>
    </div>

    <div class="topnav-right">

      <?php if ($_tn_search): ?>
      <div class="topnav-search">
        <?= icon('search', 15) ?>
        <input type="text" id="topnavSearch" placeholder="Cari eBook...">
      </div>
      <?php endif; ?>

      <?php if (!$isGuest): ?>
      <a href="notifikasi.php" class="topnav-bell" title="Notifikasi">
        <?= icon('bell', 19) ?>
        <?php if ($_tnUnread > 0): ?>
        <span class="topnav-bell-badge"><?= $_tnUnread > 9 ? '9+' : $_tnUnread ?></span>
        <?php endif; ?>
      </a>
      <?php endif; ?>

      <div class="topnav-profile">
        <button type="button" class="topnav-profile-toggle" id="tnProfileToggle">
          <span class="topnav-avatar" style="overflow:hidden;">
            <?php if ($_tnFotoPath): ?>
              <img src="<?= htmlspecialchars($_tnFotoPath) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
            <?php else: ?>
              <?= $isGuest ? 'T' : $_tnInisial ?>
            <?php endif; ?>
          </span>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" class="topnav-chevron"><path d="M7 10l5 5 5-5z"/></svg>
        </button>

        <div class="topnav-profile-menu" id="tnProfileMenu">
          <div class="topnav-profile-head">
            <span class="topnav-avatar topnav-avatar-lg" style="overflow:hidden;">
              <?php if ($_tnFotoPath): ?>
                <img src="<?= htmlspecialchars($_tnFotoPath) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
              <?php else: ?>
                <?= $isGuest ? 'T' : $_tnInisial ?>
              <?php endif; ?>
            </span>
            <div>
              <div class="topnav-profile-name"><?= $isGuest ? 'Tamu' : $_tnNamaDepan ?></div>
              <div class="topnav-profile-role"><?= $isGuest ? 'Guest' : 'Member' ?></div>
            </div>
          </div>
          <div class="topnav-dd-sep"></div>
          <?php if (!$isGuest): ?>
          <a href="profil.php" class="topnav-profile-item"><?= icon('user', 15) ?> Profil Saya</a>
          <a href="../auth/logout.php" class="topnav-profile-item danger"><?= icon('sign-out', 15) ?> Keluar</a>
          <?php else: ?>
          <a href="../index.php" class="topnav-profile-item"><?= icon('sign-in', 15) ?> Login</a>
          <a href="../auth/register.php" class="topnav-profile-item"><?= icon('user-plus', 15) ?> Daftar</a>
          <?php endif; ?>
        </div>
      </div>

      <button type="button" class="topnav-hamburger" id="tnHamburger">
        <?= icon('bars', 20) ?>
      </button>
    </div>

  </div>

  <?php if (!$isGuest): ?>
  <!-- ── BAR AKTIVITAS: muncul di bawah navbar saat tombol Aktivitas diklik, menimpa konten (tidak mendorong halaman) ── -->
  <div class="topnav-sub" id="tnActPanel">
    <div class="topnav-inner topnav-sub-inner">
      <span class="topnav-sub-label">Aktivitas</span>
      <a href="koleksi.php" class="topnav-sub-link <?= $active_menu === 'koleksi' ? 'is-active' : '' ?>"><?= icon('layers', 14) ?> Koleksi Saya</a>
      <a href="riwayat.php" class="topnav-sub-link <?= $active_menu === 'riwayat' ? 'is-active' : '' ?>"><?= icon('history', 14) ?> Riwayat Baca</a>
      <a href="wishlist.php" class="topnav-sub-link <?= $active_menu === 'wishlist' ? 'is-active' : '' ?>"><?= icon('star', 14) ?> Wishlist</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── MOBILE DRAWER ── -->
  <div class="topnav-mobile" id="tnMobile">
    <a href="dashboard.php" class="topnav-m-link <?= $active_menu === 'dashboard' ? 'is-active' : '' ?>"><?= icon('house', 16) ?> Beranda</a>
    <a href="katalog_ebook.php" class="topnav-m-link <?= $active_menu === 'katalog' ? 'is-active' : '' ?>"><?= icon('book-open', 16) ?> Katalog eBook</a>
    <a href="kategori.php" class="topnav-m-link <?= $active_menu === 'kategori' ? 'is-active' : '' ?>"><?= icon('tag', 16) ?> Kategori</a>
    <?php if (!$isGuest): ?>
    <div class="topnav-m-label">Aktivitas</div>
    <a href="koleksi.php" class="topnav-m-link <?= $active_menu === 'koleksi' ? 'is-active' : '' ?>"><?= icon('layers', 16) ?> Koleksi Saya</a>
    <a href="riwayat.php" class="topnav-m-link <?= $active_menu === 'riwayat' ? 'is-active' : '' ?>"><?= icon('history', 16) ?> Riwayat Baca</a>
    <a href="wishlist.php" class="topnav-m-link <?= $active_menu === 'wishlist' ? 'is-active' : '' ?>"><?= icon('star', 16) ?> Wishlist</a>
    <div class="topnav-dd-sep"></div>
    <a href="profil.php" class="topnav-m-link"><?= icon('user', 16) ?> Profil Saya</a>
    <a href="../auth/logout.php" class="topnav-m-link danger"><?= icon('sign-out', 16) ?> Keluar</a>
    <?php else: ?>
    <div class="topnav-dd-sep"></div>
    <a href="../index.php" class="topnav-m-link"><?= icon('sign-in', 16) ?> Login</a>
    <a href="../auth/register.php" class="topnav-m-link"><?= icon('user-plus', 16) ?> Daftar</a>
    <?php endif; ?>
  </div>
</nav>

<!-- ── HEADER HALAMAN (judul + breadcrumb, tampil di bawah navbar) ── -->
<div class="page-head">
  <div class="page-head-title"><?= htmlspecialchars($_tn_title) ?></div>
  <div class="page-head-crumb">Pojok Baca / <span><?= htmlspecialchars($_tn_breadcrumb) ?></span></div>
</div>

<style>
:root{
  --tn-bg-deep:#0b1120; --tn-bg-panel:#0e1424; --tn-bg-elev:#141b2f; --tn-bg-pill:#161d33;
  --tn-border:#262e47; --tn-border-2:#2c3757;
  --tn-text:#f2f4fb; --tn-text-2:#c5cae0; --tn-muted:#7d86a6;
  --tn-accent:#6d5ef0; --tn-accent-2:#8b95ff; --tn-gold:#e6b93a; --tn-danger:#e5484d; --tn-pink:#f0a0b0;
}
nav.topnav{ overflow:visible !important; height:auto !important; max-height:none !important; }
.topnav{ position:sticky; top:0; z-index:100; background:var(--tn-bg-deep); border-bottom:1px solid var(--tn-border); }
.topnav-inner{ display:flex; align-items:center; gap:10px; padding:10px 20px; max-width:1400px; margin:0 auto; }
.topnav-brand{ display:flex; align-items:center; gap:8px; margin-right:10px; text-decoration:none; flex-shrink:0; }
.topnav-logo{ display:block; height:32px; width:auto; }

.topnav-links{ display:flex; align-items:center; gap:2px; }
.topnav-link{ display:flex; align-items:center; gap:6px; padding:7px 11px; border-radius:8px; color:var(--tn-text-2); font-size:13px; font-weight:500; text-decoration:none; white-space:nowrap; background:none; border:none; font-family:'Poppins',sans-serif; cursor:pointer; }
.topnav-link:hover{ color:var(--tn-text); background:rgba(255,255,255,.04); }
.topnav-link.is-active{ background:#1e2247; color:var(--tn-text); }

.topnav-chevron{ margin-left:1px; opacity:.8; transition:transform .15s; }

/* Bar Aktivitas: melayang tepat di bawah navbar, menimpa konten */
.topnav-sub{
  position:absolute; top:100%; left:0; right:0;
  background:var(--tn-bg-panel); border-top:1px solid var(--tn-border); border-bottom:1px solid var(--tn-border-2);
  box-shadow:0 14px 28px rgba(0,0,0,.35);
  opacity:0; visibility:hidden; transform:translateY(-6px);
  transition:opacity .15s, transform .15s, visibility .15s;
}
.topnav.act-open .topnav-sub{ opacity:1; visibility:visible; transform:translateY(0); }
.topnav-act-toggle[aria-expanded="true"]{ background:rgba(255,255,255,.06); color:var(--tn-text); }
.topnav-act-toggle[aria-expanded="true"] .topnav-chevron{ transform:rotate(180deg); }
.topnav-sub-inner{ gap:2px; padding-top:8px; padding-bottom:8px; overflow-x:auto; scrollbar-width:none; }
.topnav-sub-inner::-webkit-scrollbar{ display:none; }
.topnav-sub-label{ font-size:11.5px; color:var(--tn-muted); font-family:'Poppins',sans-serif; margin-right:8px; padding-right:12px; border-right:1px solid var(--tn-border-2); white-space:nowrap; }
.topnav-sub-link{ display:flex; align-items:center; gap:6px; padding:6px 12px; border-radius:7px; color:var(--tn-text-2); font-size:12.5px; font-weight:500; text-decoration:none; white-space:nowrap; font-family:'Poppins',sans-serif; }
.topnav-sub-link:hover{ color:var(--tn-text); background:rgba(255,255,255,.04); }
.topnav-sub-link.is-active{ background:#1e2247; color:var(--tn-text); }
.topnav-sub-link svg{ color:var(--tn-accent-2); flex-shrink:0; }

.topnav-right{ display:flex; align-items:center; gap:8px; margin-left:auto; }
.topnav-search{ display:flex; align-items:center; gap:6px; padding:7px 11px; border-radius:8px; background:var(--tn-bg-pill); border:1px solid var(--tn-border-2); color:var(--tn-muted); }
.topnav-search svg{ color:var(--tn-gold); flex-shrink:0; }
.topnav-search input{ background:none; border:none; outline:none; color:var(--tn-text); font-size:13px; width:140px; font-family:'Poppins',sans-serif; }
.topnav-search input::placeholder{ color:var(--tn-muted); }

.topnav-bell{ position:relative; display:flex; color:var(--tn-text-2); text-decoration:none; padding:4px; }
.topnav-bell:hover{ color:var(--tn-text); }
.topnav-bell-badge{ position:absolute; top:-4px; right:-6px; background:var(--tn-danger); color:#fff; font-size:10px; font-weight:700; line-height:1; padding:2px 4px; border-radius:10px; }

.topnav-profile{ position:relative; }
.topnav-profile-toggle{ display:flex; align-items:center; gap:4px; padding:3px 6px 3px 3px; border-radius:20px; background:var(--tn-bg-pill); border:1px solid var(--tn-border-2); cursor:pointer; }
.topnav-avatar{ width:26px; height:26px; border-radius:50%; background:var(--tn-accent); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:600; color:#fff; font-family:'Poppins',sans-serif; }
.topnav-avatar-lg{ width:32px; height:32px; font-size:13px; }
.topnav-profile-toggle .topnav-chevron{ color:var(--tn-text-2); }
.topnav-profile-menu{
  position:absolute; top:calc(100% + 8px); right:0; width:220px; background:var(--tn-bg-elev);
  border:1px solid var(--tn-border-2); border-radius:10px; padding:6px;
  opacity:0; visibility:hidden; transform:translateY(-6px); transition:opacity .15s, transform .15s;
  box-shadow:0 12px 30px rgba(0,0,0,.35); z-index:60;
}
.topnav-profile-menu.open{ opacity:1; visibility:visible; transform:translateY(0); }
.topnav-profile-head{ display:flex; align-items:center; gap:10px; padding:8px 10px; }
.topnav-profile-name{ font-size:13px; font-weight:600; color:var(--tn-text); font-family:'Poppins',sans-serif; }
.topnav-profile-role{ font-size:11px; color:var(--tn-accent-2); }
.topnav-dd-sep{ height:1px; background:var(--tn-border); margin:4px 0; }
.topnav-profile-item{ display:flex; align-items:center; gap:8px; padding:8px 10px; border-radius:6px; color:var(--tn-text); font-size:13px; text-decoration:none; font-family:'Poppins',sans-serif; }
.topnav-profile-item:hover{ background:rgba(255,255,255,.05); }
.topnav-profile-item.danger{ color:var(--tn-pink); }
.topnav-profile-item.danger:hover{ background:#2a1a2a; }

.topnav-hamburger{ display:none; background:none; border:none; color:var(--tn-text-2); cursor:pointer; padding:4px; }

.tn-overlay{ display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:45; }
.tn-overlay.show{ display:block; }

.topnav-mobile{ display:none; flex-direction:column; padding:8px 14px 14px; border-top:1px solid var(--tn-border); background:var(--tn-bg-deep); }
.topnav-mobile.open{ display:flex; }
.topnav-m-link{ display:flex; align-items:center; gap:10px; padding:10px 10px; border-radius:8px; color:var(--tn-text-2); font-size:13.5px; text-decoration:none; font-family:'Poppins',sans-serif; }
.topnav-m-link.is-active{ background:#1e2247; color:var(--tn-text); font-weight:600; }
.topnav-m-link.danger{ color:var(--tn-pink); }
.topnav-m-label{ font-size:11px; color:var(--tn-muted); padding:10px 10px 2px; text-transform:none; }

.page-head{ max-width:1400px; margin:0 auto; padding:18px 20px 0; }
.page-head-title{ font-size:19px; font-weight:600; color:var(--tn-text); font-family:'Poppins',sans-serif; }
.page-head-crumb{ font-size:12px; color:var(--tn-muted); margin-top:2px; }
.page-head-crumb span{ color:var(--tn-accent-2); }

@media (max-width: 900px){
  .topnav-links, .topnav-search, .topnav-sub{ display:none; }
  .topnav-hamburger{ display:flex; }
}
</style>

<script>
(function(){
  // Bar Aktivitas (klik tombol → muncul di bawah navbar, klik di luar / Esc → tutup)
  var nav = document.querySelector('.topnav');
  var actToggle = document.getElementById('tnActToggle');
  function setAct(open){
    if (!actToggle) return;
    nav.classList.toggle('act-open', open);
    actToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  if (actToggle) {
    actToggle.addEventListener('click', function(e){
      e.stopPropagation();
      if (profileMenu) profileMenu.classList.remove('open');
      setAct(!nav.classList.contains('act-open'));
    });
    document.getElementById('tnActPanel').addEventListener('click', function(e){ e.stopPropagation(); });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') setAct(false); });
  }

  // Dropdown Profil
  var profileToggle = document.getElementById('tnProfileToggle');
  var profileMenu = document.getElementById('tnProfileMenu');
  if (profileToggle) {
    profileToggle.addEventListener('click', function(e){
      e.stopPropagation();
      setAct(false);
      profileMenu.classList.toggle('open');
    });
  }

  document.addEventListener('click', function(){
    if (profileMenu) profileMenu.classList.remove('open');
    setAct(false);
  });

  // Hamburger mobile
  var hamburger = document.getElementById('tnHamburger');
  var mobile = document.getElementById('tnMobile');
  if (hamburger) {
    hamburger.addEventListener('click', function(){
      mobile.classList.toggle('open');
    });
  }

  // Search (opsional, per halaman bisa override)
  var tnSearch = document.getElementById('topnavSearch');
  if (tnSearch) {
    tnSearch.addEventListener('keydown', function(e){
      if (e.key === 'Enter' && this.value.trim()) {
        window.location.href = 'katalog_ebook.php?search=' + encodeURIComponent(this.value.trim());
      }
    });
  }
})();
</script>