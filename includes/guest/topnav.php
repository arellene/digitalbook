<?php
// includes/guest/topnav.php
// PENGGANTI LANGSUNG sidebar.php (guest). Cukup ganti `sidebar.php` menjadi `topnav.php`
// pada baris include di halaman. Sudah termasuk toast "Fitur ini butuh akun" + showLoginToast()
// yang dulu ada di sidebar.php.
// Variabel opsional sebelum include: $topbar_title, $topbar_breadcrumb, $topbar_search (true/false)

$isGuest     = $isGuest ?? true;
$active_menu = $active_menu ?? '';
$_tn_titles = [
    'dashboard' => ['Beranda',       'Beranda'],
    'katalog'   => ['Katalog eBook', 'Katalog'],
    'kategori'  => ['Kategori',      'Kategori'],
];
$_tn_default    = $_tn_titles[$active_menu] ?? ['Pojok Baca', 'Pojok Baca'];
$_tn_title      = $topbar_title      ?? $_tn_default[0];
$_tn_breadcrumb = $topbar_breadcrumb ?? $_tn_default[1];
$_tn_search     = $topbar_search     ?? false;

// Cadangan: kalau halaman belum mendefinisikan icon(), pakai yang ini
if (!function_exists('icon')) {
    function icon($name, $size = 16, $style = '') {
        $icons = [
            'house'     => '<path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>',
            'book-open' => '<path d="M21 4H3a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h18a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1zM3 6h8v12H3V6zm10 12V6h8v12h-8z"/>',
            'tag'       => '<path d="M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58s1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41s-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z"/>',
            'user-plus' => '<path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>',
            'sign-out'  => '<path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>',
            'bars'      => '<path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>',
            'search'    => '<path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>',
        ];
        $path = $icons[$name] ?? '<circle cx="12" cy="12" r="10"/>';
        $st = $style ? " style=\"$style\"" : '';
        return "<svg xmlns='http://www.w3.org/2000/svg' width='{$size}' height='{$size}' viewBox='0 0 24 24' fill='currentColor'{$st}>{$path}</svg>";
    }
}

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
aside.sidebar, .sidebar-overlay, #overlay, .topbar{ display:none !important; }
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
</script>

<!-- Dipertahankan dari sidebar.php lama (hapus baris ini kalau tidak ada icon Font Awesome di halaman) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- Toast "Fitur ini butuh akun" (dipakai showLoginToast) -->
<style>
#loginToast{
  position:fixed; left:50%; bottom:24px; transform:translate(-50%, 20px);
  display:flex; align-items:center; gap:10px; max-width:calc(100vw - 32px);
  padding:12px 16px; border-radius:12px; background:#141b2f; color:#f2f4fb;
  border:1px solid #2c3757; box-shadow:0 12px 30px rgba(0,0,0,.4);
  font-size:13px; font-family:'Poppins',sans-serif; z-index:300;
  opacity:0; visibility:hidden; transition:opacity .2s, transform .2s, visibility .2s;
}
#loginToast.show{ opacity:1; visibility:visible; transform:translate(-50%, 0); }
#loginToast a{ color:#8b95ff; font-weight:600; text-decoration:none; }
#loginToast a:hover{ text-decoration:underline; }
#loginToast svg{ flex-shrink:0; color:#e6b93a; }
</style>

<div id="loginToast" class="login-toast">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
  <span>Fitur ini butuh akun. <a href="../index.php">Login</a> atau <a href="../auth/register.php">Daftar</a>.</span>
</div>
<script>
function showLoginToast() {
  var t = document.getElementById('loginToast');
  if (!t) return;
  t.classList.add('show');
  setTimeout(function(){ t.classList.remove('show'); }, 3500);
}
</script>