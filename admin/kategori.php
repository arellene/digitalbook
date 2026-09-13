<?php
session_start();
require_once '../config/database.php';

// Cek login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Hanya admin yang bisa akses
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../anggota/dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];


$msg_sukses = '';
$msg_error  = '';

// ─── TAMBAH KATEGORI ──────────────────────────────────────────────────────────
if (isset($_POST['tambah'])) {
    $nama = trim($_POST['nama_kategori']);
    $deskripsi = trim($_POST['deskripsi']);
    if ($nama !== '') {
        $check = mysqli_prepare($conn, "SELECT id FROM kategori WHERE nama_kategori = ?");
        mysqli_stmt_bind_param($check, "s", $nama);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);
        if (mysqli_stmt_num_rows($check) > 0) {
            $msg_error = "Kategori \"$nama\" sudah ada.";
        } else {
            $ins = mysqli_prepare($conn, "INSERT INTO kategori (nama_kategori, deskripsi) VALUES (?, ?)");
            mysqli_stmt_bind_param($ins, "ss", $nama, $deskripsi);
            if (mysqli_stmt_execute($ins)) {
                $msg_sukses = "Kategori berhasil ditambahkan.";
            } else {
                $msg_error = "Gagal menambahkan kategori.";
            }
        }
    } else {
        $msg_error = "Nama kategori tidak boleh kosong.";
    }
}

// ─── EDIT KATEGORI ────────────────────────────────────────────────────────────
if (isset($_POST['edit'])) {
    $id_edit   = (int)$_POST['id'];
    $nama_edit = trim($_POST['nama_kategori']);
    $desk_edit = trim($_POST['deskripsi']);
    if ($nama_edit !== '' && $id_edit > 0) {
        $upd = mysqli_prepare($conn, "UPDATE kategori SET nama_kategori=?, deskripsi=? WHERE id=?");
        mysqli_stmt_bind_param($upd, "ssi", $nama_edit, $desk_edit, $id_edit);
        if (mysqli_stmt_execute($upd)) {
            $msg_sukses = "Kategori berhasil diperbarui.";
        } else {
            $msg_error = "Gagal memperbarui kategori.";
        }
    }
}

// ─── HAPUS KATEGORI ───────────────────────────────────────────────────────────
if (isset($_GET['hapus'])) {
    $id_hapus = (int)$_GET['hapus'];
    // Cek apakah masih dipakai buku
    $cek = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS c FROM buku b
         JOIN kategori k ON b.kategori = k.nama_kategori
         WHERE k.id = $id_hapus"
    ));
    if ($cek['c'] > 0) {
        $msg_error = "Tidak bisa dihapus, kategori masih digunakan oleh {$cek['c']} buku.";
    } else {
        mysqli_query($conn, "DELETE FROM kategori WHERE id = $id_hapus");
        $msg_sukses = "Kategori berhasil dihapus.";
    }
}

// ─── AMBIL DATA EDIT (jika ada ?edit=id) ─────────────────────────────────────
$edit_data = null;
if (isset($_GET['edit'])) {
    $id_get = (int)$_GET['edit'];
    $res = mysqli_query($conn, "SELECT * FROM kategori WHERE id = $id_get");
    $edit_data = mysqli_fetch_assoc($res);
}

// ─── SEARCH & PAGINATION ──────────────────────────────────────────────────────
$search   = isset($_GET['search']) ? trim($_GET['search']) : '';
$per_page = 10;
$page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset   = ($page - 1) * $per_page;

$where = $search
    ? "WHERE k.nama_kategori LIKE '%" . mysqli_real_escape_string($conn, $search) . "%'
          OR k.deskripsi      LIKE '%" . mysqli_real_escape_string($conn, $search) . "%'"
    : '';

$total_rows = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total FROM kategori k $where"
))['total'];
$total_pages = max(1, ceil($total_rows / $per_page));

$result = mysqli_query($conn, "
    SELECT k.id, k.nama_kategori, k.deskripsi,
           COUNT(b.id) AS jumlah_buku
    FROM kategori k
    LEFT JOIN buku b ON b.kategori = k.nama_kategori
    $where
    GROUP BY k.id, k.nama_kategori, k.deskripsi
    ORDER BY k.nama_kategori ASC
    LIMIT $per_page OFFSET $offset
");

// ─── Stat cards ───────────────────────────────────────────────────────────────
$total_kategori = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM kategori"))['total'];
$kategori_aktif = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(DISTINCT k.id) AS total
     FROM kategori k JOIN buku b ON b.kategori = k.nama_kategori"
))['total'];
$top_kat = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT k.nama_kategori, COUNT(b.id) AS jml
     FROM kategori k LEFT JOIN buku b ON b.kategori = k.nama_kategori
     GROUP BY k.id ORDER BY jml DESC LIMIT 1"
));

$active_menu = 'kategori';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kelola Kategori — Pojok Baca</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/admin/dashboard.css">
  <link rel="stylesheet" href="../assets/css/admin/sidebar.css">
  <link rel="stylesheet" href="../assets/css/admin/kategori.css">
</head>
<body>

<!-- ========== SIDEBAR ========== -->
<?php include '../includes/admin/sidebar.php'; ?>

<!-- ========== MAIN CONTENT ========== -->
<main class="main-content" id="mainContent">

  <!-- TOPBAR -->
  <?php
    $topbar_title      = 'Kelola Kategori';
    $topbar_breadcrumb = 'Kelola Kategori';
    $topbar_search     = true;
    include '../includes/admin/topbar.php';
  ?>

  <!-- CONTENT -->
  <div class="content-area">

    <!-- ALERT -->
    <?php if ($msg_sukses): ?>
    <div class="alert alert-success">&#10003; <?php echo htmlspecialchars($msg_sukses); ?></div>
    <?php endif; ?>
    <?php if ($msg_error): ?>
    <div class="alert alert-danger">&#9888; <?php echo htmlspecialchars($msg_error); ?></div>
    <?php endif; ?>

    <!-- KPI CARDS -->
    <div class="kpi-grid kpi-grid-3">

      <div class="kpi-card">
        <div class="kpi-header">
          <span class="kpi-label">Total Kategori</span>
          <div class="kpi-icon-wrap yellow">&#127991;</div>
        </div>
        <div class="kpi-value"><?php echo $total_kategori; ?></div>
        <div class="kpi-footer">
          <span class="kpi-change">&#9650; Kategori terdaftar</span>
          <span class="kpi-period"><?php echo date('M Y'); ?></span>
        </div>
      </div>

      <div class="kpi-card">
        <div class="kpi-header">
          <span class="kpi-label">Kategori Aktif</span>
          <div class="kpi-icon-wrap green">&#9989;</div>
        </div>
        <div class="kpi-value"><?php echo $kategori_aktif; ?></div>
        <div class="kpi-footer">
          <span class="kpi-change">&#9650; Memiliki buku</span>
          <span class="kpi-period"><?php echo date('M Y'); ?></span>
        </div>
      </div>

      <div class="kpi-card">
        <div class="kpi-header">
          <span class="kpi-label">Terpopuler</span>
          <div class="kpi-icon-wrap blue">&#128081;</div>
        </div>
        <div class="kpi-value kpi-value-sm">
          <?php echo $top_kat ? htmlspecialchars($top_kat['nama_kategori']) : '—'; ?>
        </div>
        <div class="kpi-footer">
          <span class="kpi-change">&#9650; <?php echo $top_kat ? $top_kat['jml'] . ' buku' : '0 buku'; ?></span>
          <span class="kpi-period"><?php echo date('M Y'); ?></span>
        </div>
      </div>

    </div>
    <!-- END KPI CARDS -->


    <!-- ROW: FORM + TABEL -->
    <div class="kat-layout">

      <!-- FORM PANEL -->
      <div class="card kat-form-panel">
        <div class="card-title">
          <?php echo $edit_data ? '&#9998; Edit Kategori' : '&#43; Tambah Kategori'; ?>
        </div>

        <form method="POST" action="kategori.php<?php echo $edit_data ? '?edit='.$edit_data['id'] : ''; ?>">
          <?php if ($edit_data): ?>
            <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
          <?php endif; ?>

          <div class="form-group">
            <label class="form-label">Nama Kategori <span class="req">*</span></label>
            <input type="text" name="nama_kategori" class="form-control"
                   placeholder="Contoh: Fiksi, Sains, Sejarah..."
                   value="<?php echo $edit_data ? htmlspecialchars($edit_data['nama_kategori']) : ''; ?>"
                   required>
          </div>

          <div class="form-group">
            <label class="form-label">Deskripsi</label>
            <textarea name="deskripsi" class="form-control" rows="4"
                      placeholder="Deskripsi singkat kategori ini..."><?php
              echo $edit_data ? htmlspecialchars($edit_data['deskripsi']) : '';
            ?></textarea>
          </div>

          <div class="form-actions">
            <button type="submit" name="<?php echo $edit_data ? 'edit' : 'tambah'; ?>" class="btn-primary">
              <?php echo $edit_data ? '&#9998; Simpan Perubahan' : '&#43; Tambah Kategori'; ?>
            </button>
            <?php if ($edit_data): ?>
            <a href="kategori.php" class="btn-secondary">&#10005; Batal</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
      <!-- END FORM -->


      <!-- TABEL PANEL -->
      <div class="card kat-table-panel">
        <div class="card-title">
          Daftar Kategori
          <div class="card-title-actions">
            <form method="GET" action="kategori.php" class="search-inline">
              <div class="search-box search-box-sm">
                <span>&#128269;</span>
                <input type="text" name="search" placeholder="Cari kategori..."
                       value="<?php echo htmlspecialchars($search); ?>">
              </div>
              <button type="submit" class="btn-primary btn-sm">Cari</button>
              <?php if ($search): ?>
              <a href="kategori.php" class="btn-secondary btn-sm">&#10005;</a>
              <?php endif; ?>
            </form>
          </div>
        </div>

        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Nama Kategori</th>
                <th>Deskripsi</th>
                <th>Jumlah Buku</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $no  = $offset + 1;
              $empty = true;
              while ($row = mysqli_fetch_assoc($result)):
                $empty = false;
                $jml   = (int)$row['jumlah_buku'];
                $badge_class = $jml > 0 ? 'badge-success' : 'badge-warn';
              ?>
              <tr>
                <td style="color:var(--text3)"><?php echo $no++; ?></td>
                <td>
                  <div class="kat-nama">
                    <div class="kat-dot"></div>
                    <strong><?php echo htmlspecialchars($row['nama_kategori']); ?></strong>
                  </div>
                </td>
                <td style="color:var(--text2); max-width:220px;">
                  <?php echo $row['deskripsi']
                    ? htmlspecialchars(mb_strimwidth($row['deskripsi'], 0, 60, '…'))
                    : '<em style="color:var(--text3)">—</em>'; ?>
                </td>
                <td>
                  <span class="badge <?php echo $badge_class; ?>">
                    <?php echo $jml; ?> buku
                  </span>
                </td>
                <td>
                  <div class="action-btns">
                    <a href="kategori.php?edit=<?php echo $row['id']; ?>" class="btn-icon">&#9998; Edit</a>
                    <a href="kategori.php?hapus=<?php echo $row['id']; ?>"
                       class="btn-icon del"
                       onclick="return confirm('Hapus kategori \"<?php echo addslashes($row['nama_kategori']); ?>\"?')">
                      &#128465;
                    </a>
                  </div>
                </td>
              </tr>
              <?php endwhile; ?>
              <?php if ($empty): ?>
              <tr>
                <td colspan="5" style="text-align:center;padding:32px;color:var(--text3)">
                  <?php echo $search ? 'Kategori tidak ditemukan.' : 'Belum ada kategori.'; ?>
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
          <?php if ($page > 1): ?>
          <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>" class="page-btn">&#8592;</a>
          <?php endif; ?>

          <?php for ($p = max(1, $page-2); $p <= min($total_pages, $page+2); $p++): ?>
          <a href="?page=<?php echo $p; ?>&search=<?php echo urlencode($search); ?>"
             class="page-btn <?php echo $p === $page ? 'active' : ''; ?>">
            <?php echo $p; ?>
          </a>
          <?php endfor; ?>

          <?php if ($page < $total_pages): ?>
          <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>" class="page-btn">&#8594;</a>
          <?php endif; ?>

          <span class="page-info">
            <?php echo $offset+1; ?>–<?php echo min($offset+$per_page, $total_rows); ?>
            dari <?php echo $total_rows; ?> kategori
          </span>
        </div>
        <?php endif; ?>

      </div>
      <!-- END TABEL -->

    </div>
    <!-- END ROW -->

  </div>
  <!-- END CONTENT AREA -->

</main>
<!-- END MAIN CONTENT -->


<!-- TOAST -->
<div class="toast" id="toast">
  <span class="toast-icon" id="toast-icon">&#10003;</span>
  <span id="toast-msg">Berhasil!</span>
</div>


<script>
// ─── Sidebar Toggle ──────────────────────────────────────────────────────────
var sidebarToggleKat = document.getElementById('sidebarToggle');
var sidebarKat       = document.getElementById('sidebar');
var mainContentKat   = document.getElementById('mainContent');

if (sidebarToggleKat && sidebarKat) {
  sidebarToggleKat.addEventListener('click', function() {
    sidebarKat.classList.toggle('active');
    mainContentKat.classList.toggle('sidebar-open');
    sidebarToggleKat.innerHTML = sidebarKat.classList.contains('active') ? '&#10005;' : '&#9776;';
  });
}

// ─── Dark Mode ───────────────────────────────────────────────────────────────
var darkBtnKat = document.getElementById('darkModeToggle');
if (darkBtnKat) {
  darkBtnKat.addEventListener('click', function() {
    document.body.classList.toggle('dark-mode');
    var isDark = document.body.classList.contains('dark-mode');
    darkBtnKat.innerHTML = isDark ? '&#9728;' : '&#9790;';
    darkBtnKat.title = isDark ? 'Light Mode' : 'Dark Mode';
    localStorage.setItem('darkMode', isDark ? 'enabled' : 'disabled');
  });
  if (localStorage.getItem('darkMode') === 'enabled') {
    document.body.classList.add('dark-mode');
    darkBtnKat.innerHTML = '&#9728;';
    darkBtnKat.title = 'Light Mode';
  }
}

// ─── Auto-dismiss alert ───────────────────────────────────────────────────────
document.querySelectorAll('.alert').forEach(function(el) {
  setTimeout(function() {
    el.style.opacity = '0';
    el.style.transform = 'translateY(-8px)';
    setTimeout(function() { el.remove(); }, 400);
  }, 4000);
});

// ─── Toast ───────────────────────────────────────────────────────────────────
var toastTimerKat;
function showToastKat(msg, icon) {
  icon = icon || '✓';
  document.getElementById('toast-msg').textContent = msg;
  document.getElementById('toast-icon').textContent = icon;
  var t = document.getElementById('toast');
  t.classList.add('show');
  clearTimeout(toastTimerKat);
  toastTimerKat = setTimeout(function() { t.classList.remove('show'); }, 3000);
}

<?php if ($msg_sukses): ?>
window.addEventListener('DOMContentLoaded', function() { showToastKat(<?php echo json_encode($msg_sukses); ?>); });
<?php elseif ($msg_error): ?>
window.addEventListener('DOMContentLoaded', function() { showToastKat(<?php echo json_encode($msg_error); ?>, '⚠'); });
<?php endif; ?>
</script>

</body>
</html>