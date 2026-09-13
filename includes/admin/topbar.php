<?php
/**
 * includes/admin/topbar.php
 * Include file ini di setiap halaman admin SETELAH $conn dan $user_id tersedia.
 *
 * Variabel yang harus tersedia sebelum include:
 *   - $conn       : koneksi mysqli
 *   - $user_id    : ID user dari session
 *   - $topbar_title  (opsional) : judul halaman, default "Dashboard"
 *   - $topbar_breadcrumb (opsional) : breadcrumb, default "Dashboard"
 *   - $topbar_search (opsional) : true/false tampilkan search box, default false
 *
 * File ini juga men-set variabel $user (fresh dari DB) yang bisa dipakai halaman.
 */

// Reload user fresh dari DB supaya nama & foto selalu up-to-date
$_topbar_stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($_topbar_stmt, "i", $user_id);
mysqli_stmt_execute($_topbar_stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($_topbar_stmt));
mysqli_stmt_close($_topbar_stmt);

$_tb_title      = $topbar_title      ?? 'Dashboard';
$_tb_breadcrumb = $topbar_breadcrumb ?? 'Dashboard';
$_tb_search     = $topbar_search     ?? false;

// Path foto profil
$_tb_foto_path = !empty($user['foto_profil'])
    ? '../assets/img/profil/' . $user['foto_profil']
    : null;
$_tb_punya_foto = $_tb_foto_path && file_exists($_tb_foto_path);
?>
<header class="topbar">
  <div class="topbar-left">
    <button class="sidebar-toggle" id="sidebarToggle">&#9776;</button>
    <div>
      <div class="topbar-title"><?php echo htmlspecialchars($_tb_title); ?></div>
      <div class="breadcrumb">Pojok Baca / <span><?php echo htmlspecialchars($_tb_breadcrumb); ?></span></div>
    </div>
  </div>
  <div class="topbar-actions">
    <?php if ($_tb_search): ?>
    <div class="search-box">
      <span>&#128269;</span>
      <input type="text" id="topbarSearch" placeholder="Cari buku atau anggota...">
    </div>
    <?php endif; ?>
    <button class="topbar-icon-btn" id="darkModeToggle" title="Toggle Dark Mode">&#9790;</button>
    <a href="../admin/pengaturan.php?tab=profil" class="user-chip">
      <div class="user-avatar" style="<?php echo $_tb_punya_foto ? 'padding:0;overflow:hidden;' : ''; ?>">
        <?php if ($_tb_punya_foto): ?>
          <img src="<?php echo htmlspecialchars($_tb_foto_path); ?>?v=<?php echo filemtime($_tb_foto_path); ?>"
               alt="Foto Profil"
               style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;">
        <?php else: ?>
          <?php echo strtoupper(substr($user['nama_lengkap'], 0, 2)); ?>
        <?php endif; ?>
      </div>
      <div>
        <div class="user-name"><?php echo htmlspecialchars($user['nama_lengkap']); ?></div>
        <div class="user-role">Administrator</div>
      </div>
    </a>
  </div>
</header>