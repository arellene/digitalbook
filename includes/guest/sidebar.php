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
  </div>

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

    <div class="sidebar-section-label">Transaksi</div>

    <span class="sidebar-item sidebar-item-locked" onclick="showLoginToast()">
      <span class="sidebar-item-icon"><i class="fa-solid fa-book-reader"></i></span>
      <span class="sidebar-item-label">Sedang Dibaca</span>
      <span class="sidebar-item-lock"><i class="fa-solid fa-lock"></i></span>
    </span>

    <span class="sidebar-item sidebar-item-locked" onclick="showLoginToast()">
      <span class="sidebar-item-icon"><i class="fa-solid fa-heart"></i></span>
      <span class="sidebar-item-label">Wishlist</span>
      <span class="sidebar-item-lock"><i class="fa-solid fa-lock"></i></span>
    </span>

    <span class="sidebar-item sidebar-item-locked" onclick="showLoginToast()">
      <span class="sidebar-item-icon"><i class="fa-solid fa-layer-group"></i></span>
      <span class="sidebar-item-label">Koleksi Saya</span>
      <span class="sidebar-item-lock"><i class="fa-solid fa-lock"></i></span>
    </span>

    <span class="sidebar-item sidebar-item-locked" onclick="showLoginToast()">
      <span class="sidebar-item-icon"><i class="fa-solid fa-clock-rotate-left"></i></span>
      <span class="sidebar-item-label">Riwayat Baca</span>
      <span class="sidebar-item-lock"><i class="fa-solid fa-lock"></i></span>
    </span>

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