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


$toast_msg  = '';
$toast_type = 'success';

// ─── HAPUS ────────────────────────────────────────────────────────────────────
if (isset($_GET['hapus'])) {
    $hid = (int)$_GET['hapus'];
    // Jangan hapus diri sendiri
    if ($hid === (int)$user_id) {
        $toast_msg  = 'Tidak bisa menghapus akun sendiri!';
        $toast_type = 'error';
    } else {
        $del = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
        mysqli_stmt_bind_param($del, "i", $hid);
        if (mysqli_stmt_execute($del)) {
            $toast_msg = 'Anggota berhasil dihapus.';
        } else {
            $toast_msg  = 'Gagal menghapus anggota.';
            $toast_type = 'error';
        }
        mysqli_stmt_close($del);
    }
}

// ─── TAMBAH / EDIT ────────────────────────────────────────────────────────────
$edit_data = null;
if (isset($_GET['edit'])) {
    $eid  = (int)$_GET['edit'];
    $estmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
    mysqli_stmt_bind_param($estmt, "i", $eid);
    mysqli_stmt_execute($estmt);
    $edit_data = mysqli_fetch_assoc(mysqli_stmt_get_result($estmt));
    mysqli_stmt_close($estmt);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama     = trim($_POST['nama_lengkap']  ?? '');
    $username = trim($_POST['username']      ?? '');
    $email    = trim($_POST['email']         ?? '');
    $role     = $_POST['role']               ?? 'anggota';
    $status   = $_POST['status']             ?? 'aktif';
    $password = trim($_POST['password']      ?? '');
    $post_id  = (int)($_POST['id']           ?? 0);

    if ($post_id > 0) {
        // UPDATE
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $upd  = mysqli_prepare($conn,
                "UPDATE users SET nama_lengkap=?, username=?, email=?, role=?, status=?, password=? WHERE id=?");
            mysqli_stmt_bind_param($upd, "ssssssi", $nama, $username, $email, $role, $status, $hash, $post_id);
        } else {
            $upd = mysqli_prepare($conn,
                "UPDATE users SET nama_lengkap=?, username=?, email=?, role=?, status=? WHERE id=?");
            mysqli_stmt_bind_param($upd, "sssssi", $nama, $username, $email, $role, $status, $post_id);
        }
        if (mysqli_stmt_execute($upd)) {
            $toast_msg = 'Data anggota berhasil diperbarui.';
        } else {
            $toast_msg  = 'Gagal memperbarui anggota.';
            $toast_type = 'error';
        }
        mysqli_stmt_close($upd);
    } else {
        // INSERT
        if ($password === '') {
            $toast_msg  = 'Password wajib diisi untuk anggota baru.';
            $toast_type = 'error';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins  = mysqli_prepare($conn,
                "INSERT INTO users (nama_lengkap, username, email, role, status, password, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())");
            mysqli_stmt_bind_param($ins, "ssssss", $nama, $username, $email, $role, $status, $hash);
            if (mysqli_stmt_execute($ins)) {
                $toast_msg = 'Anggota baru berhasil ditambahkan.';
            } else {
                $toast_msg  = 'Gagal menambahkan anggota. Username/email mungkin sudah terdaftar.';
                $toast_type = 'error';
            }
            mysqli_stmt_close($ins);
        }
    }
    $edit_data = null; // tutup form setelah submit
}

// ─── SEARCH & FILTER ──────────────────────────────────────────────────────────
$search      = trim($_GET['search'] ?? '');
$filter_role = $_GET['filter_role']   ?? '';
$filter_stat = $_GET['filter_status'] ?? '';
$page        = max(1, (int)($_GET['page'] ?? 1));
$per_page    = 10;
$offset      = ($page - 1) * $per_page;

$where  = "WHERE 1=1";
$params = [];
$types  = "";

if ($search !== '') {
    $like    = "%$search%";
    $where  .= " AND (nama_lengkap LIKE ? OR username LIKE ? OR email LIKE ?)";
    $params  = array_merge($params, [$like, $like, $like]);
    $types  .= "sss";
}
if ($filter_role !== '') {
    $where  .= " AND role = ?";
    $params[] = $filter_role;
    $types   .= "s";
}
if ($filter_stat !== '') {
    $where  .= " AND status = ?";
    $params[] = $filter_stat;
    $types   .= "s";
}

// Total untuk pagination
$count_sql  = "SELECT COUNT(*) AS total FROM users $where";
$count_stmt = mysqli_prepare($conn, $count_sql);
if ($types !== '') mysqli_stmt_bind_param($count_stmt, $types, ...$params);
mysqli_stmt_execute($count_stmt);
$total_rows = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'];
mysqli_stmt_close($count_stmt);
$total_pages = max(1, ceil($total_rows / $per_page));

// Data anggota
$sql  = "SELECT id, nama_lengkap, username, email, role, status, created_at
         FROM users $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
$astmt = mysqli_prepare($conn, $sql);
$all_params = array_merge($params, [$per_page, $offset]);
$all_types  = $types . "ii";
mysqli_stmt_bind_param($astmt, $all_types, ...$all_params);
mysqli_stmt_execute($astmt);
$result_anggota = mysqli_stmt_get_result($astmt);

// ─── Statistik ringkas ────────────────────────────────────────────────────────
$stat_total   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users"))['c'];
$stat_anggota = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role='anggota'"))['c'];
$stat_admin   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role='admin'"))['c'];
$stat_nonaktif= mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE status='nonaktif'"))['c'];

$active_menu = 'anggota';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kelola Anggota — Pojok Baca</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/admin/dashboard.css">
  <link rel="stylesheet" href="../assets/css/admin/sidebar.css">
  <link rel="stylesheet" href="../assets/css/admin/anggota.css">
</head>
<body>

<!-- ========== SIDEBAR ========== -->
<?php include '../includes/admin/sidebar.php'; ?>

<!-- ========== MAIN CONTENT ========== -->
<main class="main-content" id="mainContent">

  <!-- TOPBAR -->
  <?php
    $topbar_title      = 'Kelola Anggota';
    $topbar_breadcrumb = 'Kelola Anggota';
    $topbar_search     = true;
    include '../includes/admin/topbar.php';
  ?>

  <!-- CONTENT -->
  <div class="content-area">

    <!-- KPI CARDS -->
    <div class="kpi-grid kpi-grid-4">
      <div class="kpi-card">
        <div class="kpi-header">
          <span class="kpi-label">Total Pengguna</span>
          <div class="kpi-icon-wrap blue">&#128101;</div>
        </div>
        <div class="kpi-value"><?php echo $stat_total; ?></div>
        <div class="kpi-footer">
          <span class="kpi-change">&#9650; Semua role</span>
          <span class="kpi-period">All time</span>
        </div>
      </div>
      <div class="kpi-card">
        <div class="kpi-header">
          <span class="kpi-label">Anggota</span>
          <div class="kpi-icon-wrap green">&#128100;</div>
        </div>
        <div class="kpi-value"><?php echo $stat_anggota; ?></div>
        <div class="kpi-footer">
          <span class="kpi-change">&#9650; Role anggota</span>
          <span class="kpi-period">All time</span>
        </div>
      </div>
      <div class="kpi-card">
        <div class="kpi-header">
          <span class="kpi-label">Admin</span>
          <div class="kpi-icon-wrap yellow">&#128737;</div>
        </div>
        <div class="kpi-value"><?php echo $stat_admin; ?></div>
        <div class="kpi-footer">
          <span class="kpi-change">&#9650; Role admin</span>
          <span class="kpi-period">All time</span>
        </div>
      </div>
      <div class="kpi-card">
        <div class="kpi-header">
          <span class="kpi-label">Nonaktif</span>
          <div class="kpi-icon-wrap orange">&#128683;</div>
        </div>
        <div class="kpi-value"><?php echo $stat_nonaktif; ?></div>
        <div class="kpi-footer">
          <span class="kpi-change">&#9660; Perlu perhatian</span>
          <span class="kpi-period">Saat ini</span>
        </div>
      </div>
    </div>
    <!-- END KPI -->


    <!-- FORM TAMBAH / EDIT -->
    <div class="card anggota-form-card <?php echo ($edit_data || isset($_POST['nama_lengkap'])) ? 'form-open' : ''; ?>" id="formCard">
      <div class="card-title form-card-title">
        <span id="formTitle"><?php echo $edit_data ? '&#9998; Edit Anggota' : '&#43; Tambah Anggota Baru'; ?></span>
        <div class="form-title-actions">
          <button class="btn-outline btn-sm" id="toggleFormBtn" type="button">
            <?php echo $edit_data ? '✕ Tutup' : '+ Tambah Baru'; ?>
          </button>
        </div>
      </div>

      <div class="form-body" id="formBody">
        <form method="POST" action="anggota.php<?php echo $edit_data ? '?edit='.$edit_data['id'] : ''; ?>">
          <?php if ($edit_data): ?>
            <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
          <?php endif; ?>

          <div class="form-grid">
            <div class="form-group">
              <label>Nama Lengkap <span class="req">*</span></label>
              <input type="text" name="nama_lengkap" class="form-control"
                     value="<?php echo htmlspecialchars($edit_data['nama_lengkap'] ?? ''); ?>"
                     placeholder="Masukkan nama lengkap" required>
            </div>
            <div class="form-group">
              <label>Username <span class="req">*</span></label>
              <input type="text" name="username" class="form-control"
                     value="<?php echo htmlspecialchars($edit_data['username'] ?? ''); ?>"
                     placeholder="Masukkan username" required>
            </div>
            <div class="form-group">
              <label>Email <span class="req">*</span></label>
              <input type="email" name="email" class="form-control"
                     value="<?php echo htmlspecialchars($edit_data['email'] ?? ''); ?>"
                     placeholder="contoh@email.com" required>
            </div>
            <div class="form-group">
              <label>Password <?php echo $edit_data ? '<span class="form-hint">(kosongkan jika tidak diubah)</span>' : '<span class="req">*</span>'; ?></label>
              <div class="input-eye-wrap">
                <input type="password" name="password" class="form-control" id="inputPassword"
                       placeholder="<?php echo $edit_data ? 'Isi untuk ganti password' : 'Minimal 6 karakter'; ?>"
                       <?php echo !$edit_data ? 'required' : ''; ?>>
                <button type="button" class="eye-btn" id="eyeBtn" title="Tampilkan/sembunyikan">&#128065;</button>
              </div>
            </div>
            <div class="form-group">
              <label>Role <span class="req">*</span></label>
              <select name="role" class="form-control">
                <option value="anggota" <?php echo (($edit_data['role'] ?? '') === 'anggota') ? 'selected' : ''; ?>>Anggota</option>
                <option value="admin"   <?php echo (($edit_data['role'] ?? '') === 'admin')   ? 'selected' : ''; ?>>Admin</option>
              </select>
            </div>
            <div class="form-group">
              <label>Status <span class="req">*</span></label>
              <select name="status" class="form-control">
                <option value="aktif"    <?php echo (($edit_data['status'] ?? 'aktif') === 'aktif')    ? 'selected' : ''; ?>>Aktif</option>
                <option value="nonaktif" <?php echo (($edit_data['status'] ?? '') === 'nonaktif') ? 'selected' : ''; ?>>Nonaktif</option>
              </select>
            </div>
          </div>

          <div class="form-actions">
            <a href="anggota.php" class="btn-outline">Batal</a>
            <button type="submit" class="btn-primary">
              <?php echo $edit_data ? '&#9998; Simpan Perubahan' : '&#43; Tambah Anggota'; ?>
            </button>
          </div>
        </form>
      </div>
    </div>
    <!-- END FORM -->


    <!-- TABEL ANGGOTA -->
    <div class="card" style="margin-top:20px;margin-bottom:40px">
      <div class="card-title">
        Daftar Anggota
        <span class="card-count"><?php echo $total_rows; ?> pengguna</span>
      </div>

      <!-- Filter & Search Bar -->
      <div class="table-toolbar">
        <form method="GET" action="anggota.php" class="filter-form" id="filterForm">
          <div class="search-filter-wrap">
            <div class="search-box-inline">
              <span>&#128269;</span>
              <input type="text" name="search" placeholder="Cari nama, username, email..."
                     value="<?php echo htmlspecialchars($search); ?>" id="tableSearch">
            </div>
            <select name="filter_role" class="filter-select" onchange="document.getElementById('filterForm').submit()">
              <option value="">Semua Role</option>
              <option value="anggota" <?php echo $filter_role === 'anggota' ? 'selected' : ''; ?>>Anggota</option>
              <option value="admin"   <?php echo $filter_role === 'admin'   ? 'selected' : ''; ?>>Admin</option>
            </select>
            <select name="filter_status" class="filter-select" onchange="document.getElementById('filterForm').submit()">
              <option value="">Semua Status</option>
              <option value="aktif"    <?php echo $filter_stat === 'aktif'    ? 'selected' : ''; ?>>Aktif</option>
              <option value="nonaktif" <?php echo $filter_stat === 'nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
            </select>
            <button type="submit" class="btn-primary btn-sm">Cari</button>
            <?php if ($search || $filter_role || $filter_stat): ?>
              <a href="anggota.php" class="btn-outline btn-sm">&#10005; Reset</a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Nama Lengkap</th>
              <th>Username</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Bergabung</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $no       = $offset + 1;
            $no_data  = true;
            while ($row = mysqli_fetch_assoc($result_anggota)):
              $no_data      = false;
              $badge_role   = $row['role'] === 'admin' ? 'badge-warn' : 'badge-info';
              $badge_status = $row['status'] === 'nonaktif' ? 'badge-danger' : 'badge-success';
              $label_status = $row['status'] === 'nonaktif' ? 'Nonaktif' : 'Aktif';
              $initials     = strtoupper(substr($row['nama_lengkap'], 0, 2));
            ?>
            <tr class="<?php echo $row['status'] === 'nonaktif' ? 'row-inactive' : ''; ?>">
              <td style="color:var(--text3)"><?php echo $no++; ?></td>
              <td>
                <div class="member-cell">
                  <div class="member-avatar"><?php echo $initials; ?></div>
                  <div>
                    <div class="member-name"><?php echo htmlspecialchars($row['nama_lengkap']); ?></div>
                    <div class="member-id">#<?php echo str_pad($row['id'], 4, '0', STR_PAD_LEFT); ?></div>
                  </div>
                </div>
              </td>
              <td style="color:var(--text2)">@<?php echo htmlspecialchars($row['username']); ?></td>
              <td style="color:var(--text2)"><?php echo htmlspecialchars($row['email']); ?></td>
              <td><span class="badge <?php echo $badge_role; ?>"><?php echo ucfirst($row['role']); ?></span></td>
              <td>
                <span class="badge <?php echo $badge_status; ?> badge-dot">
                  <?php echo $label_status; ?>
                </span>
              </td>
              <td style="color:var(--text2)"><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
              <td>
                <div class="action-btns">
                  <a href="anggota.php?edit=<?php echo $row['id']; ?>"
                     class="btn-icon" title="Edit">&#9998; Edit</a>
                  <?php if ($row['id'] != $user_id): ?>
                  <a href="anggota.php?hapus=<?php echo $row['id']; ?>"
                     class="btn-icon del"
                     title="Hapus"
                     onclick="return confirm('Hapus anggota <?php echo htmlspecialchars(addslashes($row['nama_lengkap'])); ?>?')">
                     &#128465;
                  </a>
                  <?php else: ?>
                  <span class="btn-icon disabled" title="Tidak bisa hapus diri sendiri">&#128683;</span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
            <?php if ($no_data): ?>
            <tr>
              <td colspan="8" class="empty-table-state">
                <div class="empty-state-inner">
                  <div class="empty-icon">&#128100;</div>
                  <div>Tidak ada data anggota<?php echo $search ? ' untuk pencarian "<strong>'.htmlspecialchars($search).'</strong>"' : ''; ?>.</div>
                  <?php if ($search || $filter_role || $filter_stat): ?>
                    <a href="anggota.php" class="btn-outline btn-sm" style="margin-top:10px">Reset Filter</a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- PAGINATION -->
      <?php if ($total_pages > 1): ?>
      <div class="pagination-wrap">
        <div class="pagination-info">
          Menampilkan <?php echo $offset + 1; ?>–<?php echo min($offset + $per_page, $total_rows); ?>
          dari <?php echo $total_rows; ?> pengguna
        </div>
        <div class="pagination">
          <?php
          $base_url = 'anggota.php?' . http_build_query(array_filter([
              'search'        => $search,
              'filter_role'   => $filter_role,
              'filter_status' => $filter_stat,
          ]));
          ?>
          <!-- Prev -->
          <?php if ($page > 1): ?>
            <a href="<?php echo $base_url; ?>&page=<?php echo $page - 1; ?>" class="page-btn">&#8592;</a>
          <?php else: ?>
            <span class="page-btn disabled">&#8592;</span>
          <?php endif; ?>

          <?php for ($p = 1; $p <= $total_pages; $p++): ?>
            <?php if ($p === $page): ?>
              <span class="page-btn active"><?php echo $p; ?></span>
            <?php elseif (abs($p - $page) <= 2 || $p === 1 || $p === $total_pages): ?>
              <a href="<?php echo $base_url; ?>&page=<?php echo $p; ?>" class="page-btn"><?php echo $p; ?></a>
            <?php elseif (abs($p - $page) === 3): ?>
              <span class="page-btn dots">…</span>
            <?php endif; ?>
          <?php endfor; ?>

          <!-- Next -->
          <?php if ($page < $total_pages): ?>
            <a href="<?php echo $base_url; ?>&page=<?php echo $page + 1; ?>" class="page-btn">&#8594;</a>
          <?php else: ?>
            <span class="page-btn disabled">&#8594;</span>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>
    <!-- END TABEL -->

  </div>
  <!-- END CONTENT AREA -->

</main>
<!-- END MAIN CONTENT -->


<!-- TOAST -->
<div class="toast <?php echo $toast_type === 'error' ? 'toast-error' : ''; ?>" id="toast">
  <span class="toast-icon" id="toast-icon">
    <?php echo $toast_type === 'error' ? '&#9888;' : '&#10003;'; ?>
  </span>
  <span id="toast-msg"><?php echo htmlspecialchars($toast_msg); ?></span>
</div>


<script>
// ─── Sidebar Toggle ──────────────────────────────────────────────────────────
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

// ─── Dark Mode ───────────────────────────────────────────────────────────────
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
    darkBtn.title     = 'Light Mode';
  }
}

// ─── Topbar Search ───────────────────────────────────────────────────────────
document.getElementById('topbarSearch').addEventListener('keydown', function(e) {
  if (e.key === 'Enter' && this.value.trim()) {
    window.location.href = 'anggota.php?search=' + encodeURIComponent(this.value.trim());
  }
});

// ─── Toggle Form ─────────────────────────────────────────────────────────────
const formCard    = document.getElementById('formCard');
const formBody    = document.getElementById('formBody');
const toggleBtn   = document.getElementById('toggleFormBtn');
const formTitle   = document.getElementById('formTitle');

if (toggleBtn) {
  toggleBtn.addEventListener('click', () => {
    const isOpen = formCard.classList.toggle('form-open');
    toggleBtn.textContent = isOpen ? '✕ Tutup' : '+ Tambah Baru';
    if (!isOpen) {
      // Reset form
      formTitle.innerHTML = '&#43; Tambah Anggota Baru';
    }
  });
}

// ─── Password Eye ────────────────────────────────────────────────────────────
const eyeBtn  = document.getElementById('eyeBtn');
const passInp = document.getElementById('inputPassword');
if (eyeBtn && passInp) {
  eyeBtn.addEventListener('click', () => {
    const isPass = passInp.type === 'password';
    passInp.type      = isPass ? 'text' : 'password';
    eyeBtn.innerHTML  = isPass ? '&#128064;' : '&#128065;';
  });
}

// ─── Toast ───────────────────────────────────────────────────────────────────
let toastTimer;
function showToast(msg, icon = '✓') {
  document.getElementById('toast-msg').textContent = msg;
  document.getElementById('toast-icon').textContent = icon;
  const t = document.getElementById('toast');
  t.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.classList.remove('show'), 3500);
}

<?php if ($toast_msg): ?>
document.addEventListener('DOMContentLoaded', () => showToast(
  <?php echo json_encode($toast_msg); ?>,
  <?php echo $toast_type === 'error' ? '"⚠"' : '"✓"'; ?>
));
<?php endif; ?>
</script>

</body>
</html>