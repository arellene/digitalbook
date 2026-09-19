<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../anggota/dashboard.php");
    exit();
}

$pesan = '';
$pesan_type = '';

// ─── PULIHKAN BUKU ────────────────────────────────────────────────────────────
if (isset($_GET['pulihkan']) && is_numeric($_GET['pulihkan'])) {
    $pid = (int) $_GET['pulihkan'];
    mysqli_query($conn, "UPDATE buku SET deleted_at = NULL WHERE id = $pid");
    $pesan = 'Buku berhasil dipulihkan.';
    $pesan_type = 'success';
}

// ─── HAPUS PERMANEN ───────────────────────────────────────────────────────────
if (isset($_GET['hapus_permanen']) && is_numeric($_GET['hapus_permanen'])) {
    $hid = (int) $_GET['hapus_permanen'];
    $row_del = mysqli_fetch_assoc(mysqli_query($conn, "SELECT file_pdf, cover_img FROM buku WHERE id = $hid"));
    if ($row_del) {
        if (!empty($row_del['file_pdf']) && file_exists('../' . $row_del['file_pdf'])) unlink('../' . $row_del['file_pdf']);
        if (!empty($row_del['cover_img']) && file_exists('../' . $row_del['cover_img'])) unlink('../' . $row_del['cover_img']);
    }
    mysqli_query($conn, "DELETE FROM buku WHERE id = $hid");
    $pesan = 'Buku dihapus permanen dan tidak bisa dikembalikan.';
    $pesan_type = 'danger';
}

// ─── KOSONGKAN SEMUA ──────────────────────────────────────────────────────────
if (isset($_POST['kosongkan_semua'])) {
    $result_all = mysqli_query($conn, "SELECT id, file_pdf, cover_img FROM buku WHERE deleted_at IS NOT NULL");
    while ($row_del = mysqli_fetch_assoc($result_all)) {
        if (!empty($row_del['file_pdf']) && file_exists('../' . $row_del['file_pdf'])) unlink('../' . $row_del['file_pdf']);
        if (!empty($row_del['cover_img']) && file_exists('../' . $row_del['cover_img'])) unlink('../' . $row_del['cover_img']);
    }
    mysqli_query($conn, "DELETE FROM buku WHERE deleted_at IS NOT NULL");
    $pesan = 'Recycle Bin berhasil dikosongkan.';
    $pesan_type = 'danger';
}

// ─── AMBIL DAFTAR BUKU DI RECYCLE BIN ─────────────────────────────────────────
$result_sampah = mysqli_query($conn, "
    SELECT id, judul, penulis, kategori, cover_img, cover_emoji, deleted_at
    FROM buku
    WHERE deleted_at IS NOT NULL
    ORDER BY deleted_at DESC
");
$total_sampah = mysqli_num_rows($result_sampah);

$active_menu = 'recycle_bin';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Recycle Bin — Pojok Baca</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/admin/dashboard.css">
  <link rel="stylesheet" href="../assets/css/admin/kelola_buku.css">
  <link rel="stylesheet" href="../assets/css/admin/sidebar.css">
</head>
<body>

<?php include '../includes/admin/sidebar.php'; ?>

<main class="main-content" id="mainContent">

  <!-- TOPBAR -->
  <?php
    $topbar_title      = 'Recycle Bin';
    $topbar_breadcrumb = 'Kelola Buku / Recycle Bin';
    $topbar_search     = false;
    include '../includes/admin/topbar.php';
  ?>

  <div class="content-area">

    <!-- TOOLBAR -->
    <div class="card" style="margin-bottom:20px">
      <div class="toolbar">
        <div>
          <div style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:4px;">
            🗑️ Recycle Bin — <?php echo $total_sampah; ?> buku terhapus
          </div>
          <div style="font-size:12.5px;color:var(--text3);">
            Buku di sini bisa dipulihkan kapan saja, atau dihapus permanen.
          </div>
        </div>

        <div style="display:flex;gap:10px;margin-left:auto;">
          <a href="kelola_buku.php" class="btn-ghost">
            ← Kembali ke Kelola Buku
          </a>
          <?php if ($total_sampah > 0): ?>
          <button type="button" class="btn-primary" id="btnKosongkanSemua"
                  style="background:#ef4444;border-color:#ef4444;">
            🗑️ Kosongkan Semua
          </button>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if ($pesan): ?>
    <div class="alert alert-<?php echo $pesan_type; ?>" id="alertMsg" style="margin-bottom:16px;">
      <?php echo $pesan_type === 'success' ? '✓' : '🗑'; ?>
      <?php echo htmlspecialchars($pesan); ?>
    </div>
    <?php endif; ?>

    <!-- TABEL BUKU TERHAPUS -->
    <div class="card" style="margin-bottom:40px">
      <div class="card-title">Daftar Buku di Recycle Bin</div>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Cover</th>
              <th>Judul & Penulis</th>
              <th>Kategori</th>
              <th>Dihapus Pada</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php $no = 1; while ($row = mysqli_fetch_assoc($result_sampah)): ?>
            <tr>
              <td style="color:var(--text3)"><?php echo $no++; ?></td>
              <td>
                <div class="book-cover-cell">
                  <?php if (!empty($row['cover_img']) && file_exists('../' . $row['cover_img'])): ?>
                    <img src="../<?php echo htmlspecialchars($row['cover_img']); ?>"
                         alt="Cover <?php echo htmlspecialchars($row['judul']); ?>"
                         style="width:40px;height:54px;object-fit:cover;border-radius:4px;display:block;opacity:.6;">
                  <?php else: ?>
                    <div style="width:40px;height:54px;border-radius:4px;background:#1e2533;display:flex;align-items:center;justify-content:center;opacity:.6;">
                      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="#4b5563"><path d="M18 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/></svg>
                    </div>
                  <?php endif; ?>
                </div>
              </td>
              <td>
                <div class="book-title-cell">
                  <strong style="opacity:.75;text-decoration:line-through;"><?php echo htmlspecialchars($row['judul']); ?></strong>
                  <span><?php echo htmlspecialchars($row['penulis']); ?></span>
                </div>
              </td>
              <td>
                <span class="badge badge-info"><?php echo htmlspecialchars($row['kategori'] ?? '—'); ?></span>
              </td>
              <td style="color:var(--text2)"><?php echo date('d M Y, H:i', strtotime($row['deleted_at'])); ?></td>
              <td>
                <div class="action-btns">
                  <a href="recycle_bin.php?pulihkan=<?php echo $row['id']; ?>"
                     class="btn-icon"
                     title="Pulihkan buku ini">
                    ♻️ Pulihkan
                  </a>
                  <a href="recycle_bin.php?hapus_permanen=<?php echo $row['id']; ?>"
                     class="btn-icon del"
                     onclick="return confirm('Hapus PERMANEN buku \'<?php echo addslashes($row['judul']); ?>\'? Aksi ini TIDAK BISA dibatalkan.')">
                    🗑 Hapus Permanen
                  </a>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
            <?php if ($total_sampah === 0): ?>
            <tr>
              <td colspan="6" class="empty-state">
                Recycle Bin kosong. Buku yang dihapus dari halaman Kelola Buku akan muncul di sini.
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</main>

<!-- TOAST -->
<div class="toast" id="toast">
  <span class="toast-icon" id="toast-icon">✓</span>
  <span id="toast-msg">Berhasil!</span>
</div>

<!-- FORM TERSEMBUNYI UNTUK KOSONGKAN SEMUA -->
<form method="POST" id="formKosongkan" style="display:none;">
  <input type="hidden" name="kosongkan_semua" value="1">
</form>

<script>
// ─── Sidebar Toggle ───────────────────────────────────────────────────────────
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebar       = document.getElementById('sidebar');
if (sidebarToggle && sidebar) {
  sidebarToggle.addEventListener('click', () => {
    sidebar.classList.toggle('active');
    sidebarToggle.innerHTML = sidebar.classList.contains('active') ? '✕' : '☰';
  });
}

// ─── Dark Mode ────────────────────────────────────────────────────────────────
const darkBtn = document.getElementById('darkModeToggle');
if (darkBtn) {
  darkBtn.addEventListener('click', () => {
    document.body.classList.toggle('dark-mode');
    const isDark = document.body.classList.contains('dark-mode');
    darkBtn.innerHTML = isDark ? '☀' : '☾';
    localStorage.setItem('darkMode', isDark ? 'enabled' : 'disabled');
  });
  if (localStorage.getItem('darkMode') === 'enabled') {
    document.body.classList.add('dark-mode');
    darkBtn.innerHTML = '☀';
  }
}

// ─── FITUR BARU (RIZKY): Kosongkan Semua ─────────────────────────────────────
const btnKosongkan = document.getElementById('btnKosongkanSemua');
if (btnKosongkan) {
  btnKosongkan.addEventListener('click', () => {
    if (confirm('Hapus PERMANEN semua buku di Recycle Bin? Aksi ini TIDAK BISA dibatalkan.')) {
      document.getElementById('formKosongkan').submit();
    }
  });
}

// ─── Toast ────────────────────────────────────────────────────────────────────
let toastTimer;
function showToast(msg, icon = '✓') {
  document.getElementById('toast-msg').textContent = msg;
  document.getElementById('toast-icon').textContent = icon;
  const t = document.getElementById('toast');
  t.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.classList.remove('show'), 3500);
}

<?php if ($pesan): ?>
showToast(<?php echo json_encode($pesan); ?>, '<?php echo $pesan_type === "success" ? "✓" : "🗑"; ?>');
if (window.history.replaceState) {
  const url = new URL(window.location.href);
  url.searchParams.delete('pulihkan');
  url.searchParams.delete('hapus_permanen');
  window.history.replaceState({}, '', url.toString());
}
<?php endif; ?>

const alertEl = document.getElementById('alertMsg');
if (alertEl) setTimeout(() => alertEl.style.display = 'none', 4000);
</script>

</body>
</html>