<?php
session_start();
require_once '../config/database.php';

// Cek login & role admin
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../anggota/dashboard.php");
    exit();
}

$user_id    = $_SESSION['user_id'];
$toast_msg  = '';
$toast_type = '';
$active_tab = $_GET['tab'] ?? 'profil';

// Ambil pesan dari session (setelah PRG redirect)
if (isset($_SESSION['toast_msg'])) {
    $toast_msg  = $_SESSION['toast_msg'];
    $toast_type = $_SESSION['toast_type'] ?? 'success';
    unset($_SESSION['toast_msg'], $_SESSION['toast_type']);
}

// ─── Helper: ambil data user terbaru ─────────────────────────────────────────
function getUser($conn, $user_id) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $user;
}

// Ambil data user awal
$user = getUser($conn, $user_id);

// ─── POST: Semua aksi form ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    $action = $_POST['action'];

    // ── Ganti Password ───────────────────────────────────────────────────────
    if ($action === 'ganti_password') {
        $old_pass  = md5(trim($_POST['old_password'] ?? ''));
        $new_pass  = trim($_POST['new_password'] ?? '');
        $conf_pass = trim($_POST['confirm_password'] ?? '');

        if ($old_pass !== $user['password']) {
            $_SESSION['toast_msg']  = "Password lama tidak sesuai.";
            $_SESSION['toast_type'] = "error";
        } elseif (strlen($new_pass) < 6) {
            $_SESSION['toast_msg']  = "Password baru minimal 6 karakter.";
            $_SESSION['toast_type'] = "error";
        } elseif ($new_pass !== $conf_pass) {
            $_SESSION['toast_msg']  = "Konfirmasi password tidak cocok.";
            $_SESSION['toast_type'] = "error";
        } else {
            $hashed = md5($new_pass);
            $upd = mysqli_prepare($conn, "UPDATE users SET password=? WHERE id=?");
            mysqli_stmt_bind_param($upd, "si", $hashed, $user_id);
            if (mysqli_stmt_execute($upd)) {
                $_SESSION['toast_msg']  = "Password berhasil diubah!";
                $_SESSION['toast_type'] = "success";
            } else {
                $_SESSION['toast_msg']  = "Gagal mengubah password: " . mysqli_error($conn);
                $_SESSION['toast_type'] = "error";
            }
            mysqli_stmt_close($upd);
        }
        header("Location: pengaturan.php?tab=keamanan");
        exit();
    }

    // ── Simpan Profil ────────────────────────────────────────────────────────
    elseif ($action === 'simpan_profil') {
        $nama     = trim($_POST['nama_lengkap'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $telp     = trim($_POST['no_telepon'] ?? '');

        if ($nama && $username && $email) {
            // Cek username duplikat
            $cek = mysqli_prepare($conn, "SELECT id FROM users WHERE username=? AND id != ? LIMIT 1");
            mysqli_stmt_bind_param($cek, "si", $username, $user_id);
            mysqli_stmt_execute($cek);
            mysqli_stmt_store_result($cek);
            $dup = mysqli_stmt_num_rows($cek) > 0;
            mysqli_stmt_close($cek);

            if ($dup) {
                $_SESSION['toast_msg']  = "Username sudah dipakai akun lain.";
                $_SESSION['toast_type'] = "error";
            } else {
                $upd = mysqli_prepare($conn, "UPDATE users SET nama_lengkap=?, username=?, email=?, no_telepon=? WHERE id=?");
                mysqli_stmt_bind_param($upd, "ssssi", $nama, $username, $email, $telp, $user_id);
                if (mysqli_stmt_execute($upd)) {
                    // Update session supaya topbar langsung ikut berubah
                    $_SESSION['nama_lengkap'] = $nama;
                    $_SESSION['username']     = $username;
                    $_SESSION['toast_msg']    = "Profil berhasil disimpan!";
                    $_SESSION['toast_type']   = "success";
                } else {
                    $_SESSION['toast_msg']  = "Gagal menyimpan profil: " . mysqli_error($conn);
                    $_SESSION['toast_type'] = "error";
                }
                mysqli_stmt_close($upd);
            }
        } else {
            $_SESSION['toast_msg']  = "Nama, username, dan email wajib diisi.";
            $_SESSION['toast_type'] = "error";
        }
        header("Location: pengaturan.php?tab=profil");
        exit();
    }

    // ── Upload Foto Profil ───────────────────────────────────────────────────
    elseif ($action === 'upload_foto') {
        if (!empty($_FILES['foto_profil']['tmp_name'])) {
            $file     = $_FILES['foto_profil'];
            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed  = ['jpg', 'jpeg', 'png', 'webp'];
            $max_size = 2 * 1024 * 1024; // 2 MB

            if (!in_array($ext, $allowed)) {
                $_SESSION['toast_msg']  = "Format foto tidak didukung. Gunakan JPG, PNG, atau WebP.";
                $_SESSION['toast_type'] = "error";
            } elseif ($file['size'] > $max_size) {
                $_SESSION['toast_msg']  = "Ukuran foto maksimal 2 MB.";
                $_SESSION['toast_type'] = "error";
            } else {
                $upload_dir = '../assets/img/profil/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                // Hapus foto lama jika ada
                if (!empty($user['foto_profil'])) {
                    $old_file = $upload_dir . $user['foto_profil'];
                    if (file_exists($old_file)) unlink($old_file);
                }

                $filename = 'admin_' . $user_id . '_' . time() . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                    $upd = mysqli_prepare($conn, "UPDATE users SET foto_profil=? WHERE id=?");
                    mysqli_stmt_bind_param($upd, "si", $filename, $user_id);
                    if (mysqli_stmt_execute($upd)) {
                        $_SESSION['toast_msg']  = "Foto profil berhasil diperbarui!";
                        $_SESSION['toast_type'] = "success";
                    } else {
                        $_SESSION['toast_msg']  = "Foto terupload tapi gagal disimpan ke DB.";
                        $_SESSION['toast_type'] = "error";
                        unlink($upload_dir . $filename);
                    }
                    mysqli_stmt_close($upd);
                } else {
                    $_SESSION['toast_msg']  = "Gagal mengupload foto. Coba lagi.";
                    $_SESSION['toast_type'] = "error";
                }
            }
        } else {
            $_SESSION['toast_msg']  = "Tidak ada file yang dipilih.";
            $_SESSION['toast_type'] = "error";
        }
        header("Location: pengaturan.php?tab=profil");
        exit();
    }

    // ── Hapus Foto Profil ────────────────────────────────────────────────────
    elseif ($action === 'hapus_foto') {
        if (!empty($user['foto_profil'])) {
            $old_file = '../assets/img/profil/' . $user['foto_profil'];
            if (file_exists($old_file)) unlink($old_file);
        }
        $upd = mysqli_prepare($conn, "UPDATE users SET foto_profil=NULL WHERE id=?");
        mysqli_stmt_bind_param($upd, "i", $user_id);
        if (mysqli_stmt_execute($upd)) {
            $_SESSION['toast_msg']  = "Foto profil berhasil dihapus.";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_msg']  = "Gagal menghapus foto.";
            $_SESSION['toast_type'] = "error";
        }
        mysqli_stmt_close($upd);
        header("Location: pengaturan.php?tab=profil");
        exit();
    }

    // ── Simpan eBook ─────────────────────────────────────────────────────────────
    elseif ($action === 'logout_semua') {
        session_destroy();
        header("Location: ../index.php?msg=logout_all");
        exit();
    }
}

// ─── Load config JSON ────────────────────────────────────────────────────────
$config_path = '../config/app_config.json';
$app_config  = [];
if (file_exists($config_path)) {
    $app_config = json_decode(file_get_contents($config_path), true) ?? [];
}

// ─── Reload user SETELAH semua POST diproses (fresh dari DB) ─────────────────
// Ini yang membuat topbar pojok kanan atas selalu up-to-date
$user = getUser($conn, $user_id);

// Variabel sidebar
$active_menu = 'pengaturan';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pengaturan — Pojok Baca Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/admin/dashboard.css">
  <link rel="stylesheet" href="../assets/css/admin/pengaturan.css">
  <link rel="stylesheet" href="../assets/css/admin/sidebar.css">
  <style>
    /* ── Foto Profil ── */
    .pg-avatar-wrap {
      position: relative;
      width: 88px;
      height: 88px;
      border-radius: 50%;
      flex-shrink: 0;
    }
    .pg-avatar-img {
      width: 88px;
      height: 88px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid var(--border, #e8e0d5);
      display: block;
    }
    .pg-avatar-big {
      width: 88px;
      height: 88px;
      border-radius: 50%;
      background: linear-gradient(135deg, #4f46e5, #7c3aed);
      color: #fff;
      font-size: 1.6rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .pg-avatar-overlay {
      position: absolute;
      inset: 0;
      border-radius: 50%;
      background: rgba(0,0,0,.45);
      color: #fff;
      font-size: 1.3rem;
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      transition: opacity .2s;
      cursor: pointer;
    }
    .pg-avatar-wrap:hover .pg-avatar-overlay { opacity: 1; }
    .pg-avatar-info { display: flex; flex-direction: column; gap: 4px; }
    .pg-avatar-btns { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 4px; }
    .pg-avatar-hint { font-size: .75rem; color: var(--text3, #9b9087); margin-top: 2px; }
    .pg-btn-ghost-danger { color: #e05252 !important; border-color: #fecaca !important; }
    .pg-btn-ghost-danger:hover { background: #fef2f2 !important; }

    /* ── Topbar avatar foto ── */
    .topbar-user-foto {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid rgba(255,255,255,0.3);
    }

    /* ── Tabel list di Backup ── */
    .pg-tabel-list {
      display: flex;
      flex-direction: column;
      gap: 6px;
      margin: 14px 0 20px;
    }
    .pg-tabel-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 9px 14px;
      background: var(--input-bg, #faf9f7);
      border: 1px solid var(--border, #e8e0d5);
      border-radius: 8px;
      font-size: .87rem;
    }
    .pg-tabel-icon { font-size: 1rem; }
    .pg-tabel-name { flex: 1; font-weight: 500; color: var(--text1, #1a1612); font-family: monospace; }
    .pg-tabel-count { font-size: .78rem; color: var(--text3, #9b9087); }

    .pg-export-rows {
      font-size: .75rem;
      color: var(--text3, #9b9087);
      margin-bottom: 6px;
    }

    /* Dark mode */
    .dark-mode .pg-tabel-item { background: var(--input-bg); border-color: var(--border); }
    .dark-mode .pg-avatar-img { border-color: var(--border); }
  </style>
</head>
<body>

<?php include '../includes/admin/sidebar.php'; ?>

<main class="main-content" id="mainContent">

  <!-- TOPBAR -->
  <header class="topbar">
    <div class="topbar-left">
      <button class="sidebar-toggle" id="sidebarToggle">&#9776;</button>
      <div>
        <div class="topbar-title">Pengaturan</div>
        <div class="breadcrumb">Pojok Baca / <span>Pengaturan</span></div>
      </div>
    </div>
    <div class="topbar-actions">
      <button class="topbar-icon-btn" id="darkModeToggle" title="Toggle Dark Mode">&#9790;</button>
      <a href="../admin/pengaturan.php?tab=profil" class="user-chip">
        <?php
          // Topbar: selalu pakai $user yang sudah di-reload dari DB
          $topbar_foto = !empty($user['foto_profil'])
              ? '../assets/img/profil/' . $user['foto_profil']
              : null;
        ?>
        <div class="user-avatar">
          <?php if ($topbar_foto && file_exists($topbar_foto)): ?>
            <img src="<?php echo htmlspecialchars($topbar_foto); ?>?v=<?php echo time(); ?>"
                 class="topbar-user-foto" alt="Foto Profil">
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

  <!-- CONTENT -->
  <div class="content-area">
    <div class="pg-wrapper">

      <!-- TAB NAV -->
      <nav class="pg-tabs">
        <a href="?tab=profil"   class="pg-tab <?php echo $active_tab==='profil'  ?'active':''; ?>"><span>👤</span> Profil Admin</a>
        <a href="?tab=keamanan" class="pg-tab <?php echo $active_tab==='keamanan'?'active':''; ?>"><span>🔒</span> Keamanan</a>
        <a href="?tab=backup"   class="pg-tab <?php echo $active_tab==='backup'  ?'active':''; ?>"><span>💾</span> Backup</a>
      </nav>

      <!-- ═══ TAB: PROFIL ═══ -->
      <?php if ($active_tab === 'profil'): ?>
      <?php
        $foto_path = !empty($user['foto_profil'])
            ? '../assets/img/profil/' . htmlspecialchars($user['foto_profil'])
            : null;
      ?>
      <div class="pg-card">
        <div class="pg-card-header">
          <div class="pg-card-icon">👤</div>
          <div>
            <div class="pg-card-title">Profil Admin</div>
            <div class="pg-card-sub">Data pribadi dan foto akun administrator</div>
          </div>
        </div>

        <!-- ── Foto Profil ── -->
        <div class="pg-avatar-section">
          <div class="pg-avatar-wrap" id="avatarWrap">
            <?php if ($foto_path && file_exists($foto_path)): ?>
              <img src="<?php echo $foto_path; ?>?v=<?php echo time(); ?>"
                   class="pg-avatar-img" id="avatarImg" alt="Foto Profil">
            <?php else: ?>
              <div class="pg-avatar-big" id="avatarInitial">
                <?php echo strtoupper(substr($user['nama_lengkap'], 0, 2)); ?>
              </div>
            <?php endif; ?>
            <label class="pg-avatar-overlay" for="fotoInput" title="Ganti Foto">
              📷
            </label>
          </div>
          <div class="pg-avatar-info">
            <div class="pg-avatar-name"><?php echo htmlspecialchars($user['nama_lengkap']); ?></div>
            <div class="pg-avatar-role">Administrator · Pojok Baca</div>
            <div class="pg-avatar-btns">
              <label for="fotoInput" class="pg-btn-ghost" style="cursor:pointer">📷 Ubah Foto</label>
              <?php if ($foto_path && file_exists($foto_path)): ?>
              <form method="post" style="display:inline"
                    onsubmit="return confirm('Hapus foto profil?')">
                <input type="hidden" name="action" value="hapus_foto">
                <button type="submit" class="pg-btn-ghost pg-btn-ghost-danger">🗑️ Hapus Foto</button>
              </form>
              <?php endif; ?>
            </div>
            <div class="pg-avatar-hint">JPG, PNG, atau WebP · Maks. 2 MB</div>
          </div>
        </div>

        <!-- Form upload foto (tersembunyi, auto-submit saat file dipilih) -->
        <form method="post" enctype="multipart/form-data" id="fotoForm">
          <input type="hidden" name="action" value="upload_foto">
          <input type="file" name="foto_profil" id="fotoInput"
                 accept=".jpg,.jpeg,.png,.webp" style="display:none">
        </form>

        <div class="pg-divider"></div>

        <!-- ── Data Profil ── -->
        <div class="pg-section-title" style="margin-bottom:16px">✏️ Edit Data Profil</div>
        <form class="pg-form" method="post">
          <input type="hidden" name="action" value="simpan_profil">
          <div class="pg-form-grid">
            <div class="pg-field">
              <label class="pg-label">Nama Lengkap <span class="pg-req">*</span></label>
              <input type="text" class="pg-input" name="nama_lengkap"
                     value="<?php echo htmlspecialchars($user['nama_lengkap']); ?>" required>
            </div>
            <div class="pg-field">
              <label class="pg-label">Username <span class="pg-req">*</span></label>
              <div class="pg-input-prefix-wrap">
                <span class="pg-input-prefix">@</span>
                <input type="text" class="pg-input pg-input-has-prefix" name="username"
                       value="<?php echo htmlspecialchars($user['username']); ?>" required>
              </div>
            </div>
            <div class="pg-field">
              <label class="pg-label">Email <span class="pg-req">*</span></label>
              <input type="email" class="pg-input" name="email"
                     value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            <div class="pg-field">
              <label class="pg-label">No. Telepon</label>
              <input type="text" class="pg-input" name="no_telepon"
                     value="<?php echo htmlspecialchars($user['no_telepon'] ?? ''); ?>"
                     placeholder="+62...">
            </div>
          </div>
          <div class="pg-form-footer">
            <button type="submit" class="pg-btn-primary">💾 Simpan Profil</button>
          </div>
        </form>
      </div>
      <?php endif; ?>

      <!-- ═══ TAB: KEAMANAN ═══ -->
      <?php if ($active_tab === 'keamanan'): ?>
      <div class="pg-card">
        <div class="pg-card-header">
          <div class="pg-card-icon">🔒</div>
          <div>
            <div class="pg-card-title">Keamanan Akun</div>
            <div class="pg-card-sub">Kelola password dan sesi login</div>
          </div>
        </div>
        <div class="pg-section">
          <div class="pg-section-title">🔑 Ganti Password</div>
          <form class="pg-form" method="post">
            <input type="hidden" name="action" value="ganti_password">
            <div class="pg-field">
              <label class="pg-label">Password Lama <span class="pg-req">*</span></label>
              <div class="pg-pw-wrap">
                <input type="password" class="pg-input" name="old_password" id="oldPw" required>
                <button type="button" class="pg-pw-toggle" data-target="oldPw">👁️</button>
              </div>
            </div>
            <div class="pg-form-grid">
              <div class="pg-field">
                <label class="pg-label">Password Baru <span class="pg-req">*</span></label>
                <div class="pg-pw-wrap">
                  <input type="password" class="pg-input" name="new_password" id="newPw" required minlength="6">
                  <button type="button" class="pg-pw-toggle" data-target="newPw">👁️</button>
                </div>
                <div class="pg-strength-bar"><div class="pg-strength-fill" id="strengthFill"></div></div>
                <div class="pg-hint" id="strengthText">Masukkan password baru</div>
              </div>
              <div class="pg-field">
                <label class="pg-label">Konfirmasi Password <span class="pg-req">*</span></label>
                <div class="pg-pw-wrap">
                  <input type="password" class="pg-input" name="confirm_password" id="confPw" required>
                  <button type="button" class="pg-pw-toggle" data-target="confPw">👁️</button>
                </div>
                <div class="pg-hint" id="matchHint"></div>
              </div>
            </div>
            <div class="pg-form-footer">
              <button type="submit" class="pg-btn-primary">🔐 Ubah Password</button>
            </div>
          </form>
        </div>
        <div class="pg-divider"></div>
        <div class="pg-section">
          <div class="pg-section-title">🚪 Sesi Aktif</div>
          <div class="pg-info-box">
            <div class="pg-info-icon">ℹ️</div>
            <div>Logout dari semua perangkat akan mengakhiri semua sesi aktif, termasuk sesi saat ini. Kamu harus login kembali.</div>
          </div>
          <form method="post" onsubmit="return confirm('Yakin logout dari semua perangkat?')">
            <input type="hidden" name="action" value="logout_semua">
            <button type="submit" class="pg-btn-danger">🚪 Logout Semua Perangkat</button>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <!-- ═══ TAB: BACKUP ═══ -->
      <?php if ($active_tab === 'backup'):
        $tabel_list = [];
        $res_tables = mysqli_query($conn, "SHOW TABLES");
        while ($row_t = mysqli_fetch_row($res_tables)) {
            $tabel_list[] = $row_t[0];
        }
        $jumlah_tabel = count($tabel_list);

        $total_baris = 0;
        foreach ($tabel_list as $tbl) {
            $cnt = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM `$tbl`"));
            $total_baris += (int)$cnt[0];
        }

        $db_name_row = mysqli_fetch_row(mysqli_query($conn, "SELECT DATABASE()"));
        $db_name = $db_name_row[0];
      ?>
      <div class="pg-card">
        <div class="pg-card-header">
          <div class="pg-card-icon">💾</div>
          <div>
            <div class="pg-card-title">Backup &amp; Export</div>
            <div class="pg-card-sub">Unduh cadangan database dan export data CSV</div>
          </div>
        </div>

        <!-- ── Backup Database ── -->
        <div class="pg-section">
          <div class="pg-section-title">🗄️ Backup Database</div>
          <div class="pg-info-box">
            <div class="pg-info-icon">💡</div>
            <div>Backup mengunduh file <code>.sql</code> yang berisi struktur dan seluruh data database. Simpan di tempat yang aman dan lakukan secara rutin.</div>
          </div>

          <div class="pg-backup-meta">
            <div class="pg-backup-meta-item">
              <span class="pg-backup-meta-label">Database</span>
              <span class="pg-backup-meta-val"><strong><?php echo htmlspecialchars($db_name); ?></strong></span>
            </div>
            <div class="pg-backup-meta-item">
              <span class="pg-backup-meta-label">Tanggal</span>
              <span class="pg-backup-meta-val"><?php echo date('d M Y, H:i'); ?></span>
            </div>
            <div class="pg-backup-meta-item">
              <span class="pg-backup-meta-label">Jumlah Tabel</span>
              <span class="pg-backup-meta-val"><?php echo $jumlah_tabel; ?> tabel</span>
            </div>
            <div class="pg-backup-meta-item">
              <span class="pg-backup-meta-label">Total Baris</span>
              <span class="pg-backup-meta-val"><?php echo number_format($total_baris); ?> baris</span>
            </div>
          </div>

          <div class="pg-tabel-list">
            <?php foreach ($tabel_list as $tbl): ?>
            <?php
              $cnt_r = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM `$tbl`"));
              $cnt_n = (int)$cnt_r[0];
            ?>
            <div class="pg-tabel-item">
              <span class="pg-tabel-icon">🗃️</span>
              <span class="pg-tabel-name"><?php echo htmlspecialchars($tbl); ?></span>
              <span class="pg-tabel-count"><?php echo number_format($cnt_n); ?> baris</span>
            </div>
            <?php endforeach; ?>
          </div>

          <a href="backup_db.php" class="pg-btn-primary pg-btn-inline">⬇️ Unduh Backup SQL</a>
        </div>

        <div class="pg-divider"></div>

        <!-- ── Export CSV ── -->
        <div class="pg-section">
          <div class="pg-section-title">📊 Export CSV</div>
          <div class="pg-info-box">
            <div class="pg-info-icon">💡</div>
            <div>Export data per tabel ke format CSV. File bisa dibuka di Excel atau Google Sheets.</div>
          </div>
          <div class="pg-export-grid">
            <?php
            $export_map = [
              'buku'         => ['📚', 'Data Buku',    'Seluruh koleksi eBook'],
              'users'        => ['👥', 'Data Anggota', 'User role anggota'],
              'riwayat_baca' => ['📖', 'Riwayat Baca', 'Semua akses eBook'],
              'wishlist'     => ['❤️', 'Wishlist',      'Data wishlist anggota'],
            ];
            foreach ($export_map as $tbl => $meta):
              if (!in_array($tbl, $tabel_list)) continue;
              $cnt_r = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM `$tbl`"));
            ?>
            <div class="pg-export-card">
              <div class="pg-export-emoji"><?php echo $meta[0]; ?></div>
              <div class="pg-export-name"><?php echo $meta[1]; ?></div>
              <div class="pg-export-desc"><?php echo $meta[2]; ?></div>
              <div class="pg-export-rows"><?php echo number_format((int)$cnt_r[0]); ?> baris</div>
              <a href="export_csv.php?tabel=<?php echo urlencode($tbl); ?>" class="pg-btn-outline">⬇️ Export</a>
            </div>
            <?php endforeach; ?>

            <?php
            foreach ($tabel_list as $tbl):
              if (array_key_exists($tbl, $export_map)) continue;
              $cnt_r = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM `$tbl`"));
            ?>
            <div class="pg-export-card">
              <div class="pg-export-emoji">🗃️</div>
              <div class="pg-export-name"><?php echo htmlspecialchars($tbl); ?></div>
              <div class="pg-export-desc">Tabel database</div>
              <div class="pg-export-rows"><?php echo number_format((int)$cnt_r[0]); ?> baris</div>
              <a href="export_csv.php?tabel=<?php echo urlencode($tbl); ?>" class="pg-btn-outline">⬇️ Export</a>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</main>

<!-- TOAST -->
<div class="toast" id="toast">
  <span class="toast-icon" id="toast-icon">&#10003;</span>
  <span id="toast-msg">Berhasil!</span>
</div>

<script>
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebar       = document.getElementById('sidebar');
const mainContent   = document.getElementById('mainContent');
if (sidebarToggle && sidebar) {
  sidebarToggle.addEventListener('click', () => {
    sidebar.classList.toggle('active');
    mainContent.classList.toggle('sidebar-open');
    sidebarToggle.innerHTML = sidebar.classList.contains('active') ? '&#10005;' : '&#9776;';
  });
}

const darkBtn = document.getElementById('darkModeToggle');
if (darkBtn) {
  darkBtn.addEventListener('click', () => {
    document.body.classList.toggle('dark-mode');
    const isDark = document.body.classList.contains('dark-mode');
    darkBtn.innerHTML = isDark ? '&#9728;' : '&#9790;';
    darkBtn.title     = isDark ? 'Light Mode' : 'Dark Mode';
    localStorage.setItem('darkMode', isDark ? 'enabled' : 'disabled');
  });
  if (localStorage.getItem('darkMode') === 'enabled') {
    document.body.classList.add('dark-mode');
    darkBtn.innerHTML = '&#9728;';
    darkBtn.title = 'Light Mode';
  }
}

<?php if ($toast_msg): ?>
showToast(<?php echo json_encode($toast_msg); ?>, <?php echo $toast_type==='success' ? '"✓"' : '"✕"'; ?>);
<?php endif; ?>

let toastTimer;
function showToast(msg, icon = '✓') {
  document.getElementById('toast-msg').textContent  = msg;
  document.getElementById('toast-icon').textContent = icon;
  const t = document.getElementById('toast');
  t.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.classList.remove('show'), 3500);
}

document.querySelectorAll('.pg-pw-toggle').forEach(btn => {
  btn.addEventListener('click', () => {
    const inp = document.getElementById(btn.dataset.target);
    if (!inp) return;
    inp.type = inp.type === 'password' ? 'text' : 'password';
    btn.textContent = inp.type === 'password' ? '👁️' : '🙈';
  });
});

const newPw        = document.getElementById('newPw');
const strengthFill = document.getElementById('strengthFill');
const strengthTxt  = document.getElementById('strengthText');
if (newPw && strengthFill) {
  newPw.addEventListener('input', function() {
    const v = this.value;
    let score = 0;
    if (v.length >= 6)  score++;
    if (v.length >= 10) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^a-zA-Z0-9]/.test(v)) score++;
    strengthFill.style.width = (score / 5 * 100) + '%';
    strengthFill.className = 'pg-strength-fill';
    if (score <= 1) { strengthFill.classList.add('weak');   strengthTxt.textContent = 'Lemah'; }
    else if (score <= 3) { strengthFill.classList.add('medium'); strengthTxt.textContent = 'Sedang'; }
    else { strengthFill.classList.add('strong'); strengthTxt.textContent = 'Kuat 💪'; }
  });
}

const confPw    = document.getElementById('confPw');
const matchHint = document.getElementById('matchHint');
if (confPw && newPw && matchHint) {
  confPw.addEventListener('input', function() {
    if (this.value && newPw.value) {
      const ok = this.value === newPw.value;
      matchHint.textContent = ok ? '✅ Password cocok' : '❌ Tidak cocok';
      matchHint.style.color = ok ? 'var(--success,#22c55e)' : 'var(--danger,#ef4444)';
    }
  });
}

// ─── Upload Foto Profil ───────────────────────────────────────────────────────
const fotoInput = document.getElementById('fotoInput');
const fotoForm  = document.getElementById('fotoForm');
if (fotoInput && fotoForm) {
  fotoInput.addEventListener('change', function() {
    if (!this.files[0]) return;
    const file = this.files[0];
    const maxSize = 2 * 1024 * 1024;
    const allowed = ['image/jpeg', 'image/png', 'image/webp'];

    if (!allowed.includes(file.type)) {
      showToast('Format tidak didukung. Gunakan JPG, PNG, atau WebP.', '✕');
      return;
    }
    if (file.size > maxSize) {
      showToast('Ukuran foto maksimal 2 MB.', '✕');
      return;
    }

    // Preview langsung sebelum upload
    const reader = new FileReader();
    reader.onload = e => {
      const wrap = document.getElementById('avatarWrap');
      if (wrap) {
        wrap.querySelector('.pg-avatar-big, .pg-avatar-img')?.remove();
        const img = document.createElement('img');
        img.src       = e.target.result;
        img.className = 'pg-avatar-img';
        img.id        = 'avatarImg';
        img.alt       = 'Foto Profil';
        wrap.prepend(img);
      }
    };
    reader.readAsDataURL(file);

    fotoForm.submit();
  });
}
</script>
</body>
</html>