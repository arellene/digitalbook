<?php
// includes/guest/sidebar.php
?>

<link rel="stylesheet" href="../assets/css/guest/sidebar.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">

  <!-- Brand -->
  <div class="sidebar-brand">
    <div class="brand-icon"><i class="fa-solid fa-book-open"></i></div>
    <div class="brand-text">
      <span class="brand-name">Pojok Baca</span>
      <span class="brand-sub">Portal Tamu</span>
    </div>
  </d<?php
// includes/guest/topnav.php
// Pengganti sidebar untuk halaman guest. Include SETELAH $user, $isGuest, $active_menu
// dan fungsi icon() tersedia (sama seperti sidebar.php).
// Variabel opsional sebelum include: $topbar_title, $topbar_breadcrumb, $topbar_search (true/false)

$isGuest     = $isGuest ?? true;
$active_menu = $active_menu ?? '';
$_tn_title      = $topbar_title      ?? 'Beranda';
$_tn_breadcrumb = $topbar_breadcrumb ?? 'Beranda';
$_tn_search     = $topbar_search     ?? false;

$_tnInisial   = strtoupper(substr($user['nama_lengkap'] ?? 'T', 0, 1));
$_tnNamaDepan = htmlspecialchars(explode(' ', $user['nama_lengkap'] ?? 'Tamu')[0]);
?>
<nav class="topnav">
  <div class="topnav-inner">

    <a href="dashboard.php" class="topnav-brand" aria-label="Pojok Baca">
      <img src="../assets/img/logo/logo-pojokbaca-dark.svg" alt="Pojok Baca" class="topnav-logo" width="162" height="32">
    </a>

    <div class="topnav-links">
      <a href="dashboard.php" class="topnav-link <?= $active_menu === 'dashboard' ? 'is-active' : '' ?>">
        <?= icon('house', 15) ?> Beranda
      </a>
      <a href="katalog.php" class="topnav-link <?= $active_menu === 'katalog' ? 'is-active' : '' ?>">
        <?= icon('book-open', 15) ?> Katalog eBook
      </a>
      <a href="kategori.php" class="topnav-link <?= $active_menu === 'kategori' ? 'is-active' : '' ?>">
        <?= icon('tag', 15) ?> Kategori
      </a>
    </div>

    <div class="topnav-right">

      <?php if ($_tn_search): ?>
      <div class="topnav-search">
        <?= icon('search', 15) ?>
        <input type="text" id="topnavSearch" placeholder="Cari eBook...">
      </div>
      <?php endif; ?>

      <?php if ($isGuest): ?>
      <!-- Tombol Daftar Sekarang (langsung di navbar) -->
      <a href="../auth/register.php" class="topnav-cta">
        <?= icon('user-plus', 15) ?> Daftar Sekarang
      </a>
      <?php endif; ?>

      <div class="topnav-profile">
        <button type="button" class="topnav-profile-toggle" id="tnProfileToggle" aria-haspopup="true" aria-expanded="false">
          <span class="topnav-avatar"><?= $isGuest ? 'T' : $_tnInisial ?></span>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" class="topnav-chevron"><path d="M7 10l5 5 5-5z"/></svg>
        </button>

        <!-- Menu profil: muncul saat avatar diklik -->
        <div class="topnav-profile-menu" id="tnProfileMenu">
          <div class="topnav-profile-head">
            <span class="topnav-avatar topnav-avatar-lg"><?= $isGuest ? 'T' : $_tnInisial ?></span>
            <div>
              <div class="topnav-profile-name"><?= $isGuest ? 'Tamu' : $_tnNamaDepan ?></div>
              <div class="topnav-profile-role"><?= $isGuest ? 'Guest' : 'Member' ?></div>
            </div>
          </div>
          <div class="topnav-dd-sep"></div>
          <a href="../auth/logout.php" class="topnav-profile-item danger"><?= icon('sign-out', 15) ?> Keluar</a>
        </div>
      </div>

      <button type="button" class="topnav-hamburger" id="tnHamburger" aria-label="Menu">
        <?= icon('bars', 20) ?>
      </button>
    </div>

  </div>

  <!-- ── MOBILE DRAWER ── -->
  <div class="topnav-mobile" id="tnMobile">
    <a href="dashboard.php" class="topnav-m-link <?= $active_menu === 'dashboard' ? 'is-active' : '' ?>"><?= icon('house', 16) ?> Beranda</a>
    <a href="katalog.php" class="topnav-m-link <?= $active_menu === 'katalog' ? 'is-active' : '' ?>"><?= icon('book-open', 16) ?> Katalog eBook</a>
    <a href="kategori.php" class="topnav-m-link <?= $active_menu === 'kategori' ? 'is-active' : '' ?>"><?= icon('tag', 16) ?> Kategori</a>
    <div class="topnav-dd-sep"></div>
    <?php if ($isGuest): ?>
    <a href="../auth/register.php" class="topnav-m-link cta"><?= icon('user-plus', 16) ?> Daftar Sekarang</a>
    <?php endif; ?>
    <a href="../auth/logout.php" class="topnav-m-link danger"><?= icon('sign-out', 16) ?> Keluar</a>
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

/* Sidebar sudah diganti navbar atas: konten selalu selebar layar */
body{ display:block !important; }
.main{ margin-left:0 !important; width:100% !important; max-width:none !important; }

nav.topnav{ overflow:visible !important; height:auto !important; max-height:none !important; }
.topnav{ position:sticky; top:0; z-index:100; background:var(--tn-bg-deep); border-bottom:1px solid var(--tn-border); }
.topnav-inner{ display:flex; align-items:center; gap:10px; padding:10px 20px; max-width:1400px; margin:0 auto; }
.topnav-brand{ display:flex; align-items:center; gap:8px; margin-right:10px; text-decoration:none; flex-shrink:0; }
.topnav-logo{ display:block; height:32px; width:auto; }

.topnav-links{ display:flex; align-items:center; gap:2px; }
.topnav-link{ display:flex; align-items:center; gap:6px; padding:7px 11px; border-radius:8px; color:var(--tn-text-2); font-size:13px; font-weight:500; text-decoration:none; white-space:nowrap; background:none; border:none; cursor:pointer; font-family:'Poppins',sans-serif; }
.topnav-link:hover{ color:var(--tn-text); background:rgba(255,255,255,.04); }
.topnav-link.is-active{ background:#1e2247; color:var(--tn-text); }

.topnav-chevron{ margin-left:1px; opacity:.8; transition:transform .15s; }

.topnav-right{ display:flex; align-items:center; gap:8px; margin-left:auto; }
.topnav-search{ display:flex; align-items:center; gap:6px; padding:7px 11px; border-radius:8px; background:var(--tn-bg-pill); border:1px solid var(--tn-border-2); color:var(--tn-muted); }
.topnav-search svg{ color:var(--tn-gold); flex-shrink:0; }
.topnav-search input{ background:none; border:none; outline:none; color:var(--tn-text); font-size:13px; width:140px; font-family:'Poppins',sans-serif; }
.topnav-search input::placeholder{ color:var(--tn-muted); }

/* Tombol Daftar Sekarang */
.topnav-cta{ display:flex; align-items:center; gap:6px; padding:7px 14px; border-radius:8px; background:var(--tn-accent); color:#fff; font-size:13px; font-weight:600; text-decoration:none; white-space:nowrap; font-family:'Poppins',sans-serif; transition:background .15s, transform .15s; }
.topnav-cta:hover{ background:#7d70ff; transform:translateY(-1px); }

.topnav-profile{ position:relative; }
.topnav-profile-toggle{ display:flex; align-items:center; gap:4px; padding:3px 6px 3px 3px; border-radius:20px; background:var(--tn-bg-pill); border:1px solid var(--tn-border-2); cursor:pointer; }
.topnav-avatar{ width:26px; height:26px; border-radius:50%; background:var(--tn-accent); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:600; color:#fff; font-family:'Poppins',sans-serif; }
.topnav-avatar-lg{ width:32px; height:32px; font-size:13px; }
.topnav-profile-toggle .topnav-chevron{ color:var(--tn-text-2); }
.topnav-profile-toggle[aria-expanded="true"] .topnav-chevron{ transform:rotate(180deg); }
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

.topnav-mobile{ display:none; flex-direction:column; padding:8px 14px 14px; border-top:1px solid var(--tn-border); background:var(--tn-bg-deep); }
.topnav-mobile.open{ display:flex; }
.topnav-m-link{ display:flex; align-items:center; gap:10px; padding:10px 10px; border-radius:8px; color:var(--tn-text-2); font-size:13.5px; text-decoration:none; font-family:'Poppins',sans-serif; }
.topnav-m-link.is-active{ background:#1e2247; color:var(--tn-text); font-weight:600; }
.topnav-m-link.cta{ background:var(--tn-accent); color:#fff; font-weight:600; margin-bottom:4px; }
.topnav-m-link.danger{ color:var(--tn-pink); }

.page-head{ max-width:1400px; margin:0 auto; padding:18px 20px 0; }
.page-head-title{ font-size:19px; font-weight:600; color:var(--tn-text); font-family:'Poppins',sans-serif; }
.page-head-crumb{ font-size:12px; color:var(--tn-muted); margin-top:2px; }
.page-head-crumb span{ color:var(--tn-accent-2); }

@media (max-width: 900px){
  .topnav-links, .topnav-search{ display:none; }
  .topnav-hamburger{ display:flex; }
}
@media (max-width: 600px){
  .topnav-cta{ display:none; }   /* di layar kecil, tombol Daftar ada di menu hamburger */
}
</style>

<script>
(function(){
  // Dropdown profil: klik avatar → muncul menu "Keluar"; klik di luar / Esc → tutup
  var profileToggle = document.getElementById('tnProfileToggle');
  var profileMenu   = document.getElementById('tnProfileMenu');
  function setProfile(open){
    if (!profileMenu) return;
    profileMenu.classList.toggle('open', open);
    profileToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  if (profileToggle) {
    profileToggle.addEventListener('click', function(e){
      e.stopPropagation();
      setProfile(!profileMenu.classList.contains('open'));
    });
    profileMenu.addEventListener('click', function(e){ e.stopPropagation(); });
  }
  document.addEventListener('click', function(){ setProfile(false); });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') setProfile(false); });

  // Hamburger mobile
  var hamburger = document.getElementById('tnHamburger');
  var mobile = document.getElementById('tnMobile');
  if (hamburger) {
    hamburger.addEventListener('click', function(){
      mobile.classList.toggle('open');
    });
  }

  // Search (opsional, aktif jika $topbar_search = true)
  var tnSearch = document.getElementById('topnavSearch');
  if (tnSearch) {
    tnSearch.addEventListener('keydown', function(e){
      if (e.key === 'Enter' && this.value.trim()) {
        window.location.href = 'katalog.php?search=' + encodeURIComponent(this.value.trim());
      }
    });
  }
})();
</script>iv>

  <!-- User Info -->
  <div class="sidebar-user">
    <div class="sidebar-avatar">T</div>
    <div class="sidebar-user-info">
      <div class="sidebar-user-name">Tamu</div>
      <div class="sidebar-user-role">Guest</div>
    </div>
  </div>

  <div class="sidebar-divider"></div>

  <nav class="sidebar-nav">
    <div class="sidebar-section-label">Menu Utama</div>

    <?php $m = isset($active_menu) ? $active_menu : ''; ?>

    <a href="../guest/dashboard.php" class="sidebar-item <?php echo $m === 'dashboard' ? 'active' : ''; ?>">
      <span class="sidebar-item-icon"><i class="fa-solid fa-house"></i></span>
      <span class="sidebar-item-label">Dashboard</span>
      <?php if ($m === 'dashboard'): ?><span class="sidebar-item-indicator"></span><?php endif; ?>
    </a>

    <a href="katalog.php" class="sidebar-item <?php echo $m === 'katalog' ? 'active' : ''; ?>">
      <span class="sidebar-item-icon"><i class="fa-solid fa-books"></i></span>
      <span class="sidebar-item-label">Katalog eBook</span>
      <?php if ($m === 'katalog'): ?><span class="sidebar-item-indicator"></span><?php endif; ?>
    </a>

  </nav>

  <div class="sidebar-divider"></div>

  <div class="sidebar-bottom">
    <a href="../auth/register.php" class="sidebar-item sidebar-register">
      <span class="sidebar-item-icon"><i class="fa-solid fa-user-plus"></i></span>
      <span class="sidebar-item-label">Daftar Sekarang</span>
    </a>
    <a href="../index.php" class="sidebar-item sidebar-login">
      <span class="sidebar-item-icon"><i class="fa-solid fa-right-to-bracket"></i></span>
      <span class="sidebar-item-label">Login</span>
    </a>
  </div>

</aside>

<div id="loginToast" class="login-toast">
  <span><i class="fa-solid fa-lock"></i></span>
  <span>Fitur ini butuh akun. <a href="../index.php">Login</a> atau <a href="../auth/register.php">Daftar</a>.</span>
</div>

<script>
const overlay = document.getElementById('sidebarOverlay');
if (overlay) {
  overlay.addEventListener('click', () => {
    document.getElementById('sidebar').classList.remove('active');
    overlay.classList.remove('active');
    const toggle = document.getElementById('sidebarToggle');
    if (toggle) toggle.innerHTML = '&#9776;';
  });
}
function showLoginToast() {
  const t = document.getElementById('loginToast');
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3500);
}
</script>