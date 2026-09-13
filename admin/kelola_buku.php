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

$user_id = $_SESSION['user_id'];


// ─── PROSES AKSI ─────────────────────────────────────────────────────────────

$pesan = '';
$pesan_type = '';

// HAPUS
if (isset($_GET['hapus']) && is_numeric($_GET['hapus'])) {
    $hid = (int)$_GET['hapus'];
    // Hapus file PDF jika ada
    $row_del = mysqli_fetch_assoc(mysqli_query($conn, "SELECT file_pdf, cover_img FROM buku WHERE id = $hid"));
    if ($row_del) {
        if (!empty($row_del['file_pdf']) && file_exists('../' . $row_del['file_pdf'])) unlink('../' . $row_del['file_pdf']);
        if (!empty($row_del['cover_img']) && file_exists('../' . $row_del['cover_img'])) unlink('../' . $row_del['cover_img']);
    }
    mysqli_query($conn, "DELETE FROM buku WHERE id = $hid");
    $pesan = 'Buku berhasil dihapus.';
    $pesan_type = 'danger';
}

// TAMBAH / EDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    $judul       = mysqli_real_escape_string($conn, trim($_POST['judul'] ?? ''));
    $penulis     = mysqli_real_escape_string($conn, trim($_POST['penulis'] ?? ''));
    $penerbit    = mysqli_real_escape_string($conn, trim($_POST['penerbit'] ?? ''));
    $tahun       = (int)($_POST['tahun'] ?? date('Y'));
    $kategori    = mysqli_real_escape_string($conn, trim($_POST['kategori'] ?? ''));
    $deskripsi   = mysqli_real_escape_string($conn, trim($_POST['deskripsi'] ?? ''));
    $cover_emoji = '';
    $stok        = (int)($_POST['stok'] ?? 1);
    $edit_id     = (int)($_POST['edit_id'] ?? 0);

    if ($edit_id > 0) {
        mysqli_query($conn, "UPDATE buku SET
            judul='$judul', penulis='$penulis', penerbit='$penerbit',
            tahun=$tahun, kategori='$kategori', deskripsi='$deskripsi',
            stok=$stok
            WHERE id=$edit_id");
        $pesan = 'Buku berhasil diperbarui.';
        $pesan_type = 'success';
    } else {
        mysqli_query($conn, "INSERT INTO buku
            (judul, penulis, penerbit, tahun, kategori, deskripsi, stok, total_baca, created_at)
            VALUES ('$judul','$penulis','$penerbit',$tahun,'$kategori','$deskripsi',$stok,0,NOW())");
        $pesan = 'Buku berhasil ditambahkan.';
        $pesan_type = 'success';
    }
}

// ─── FILTER & SEARCH ─────────────────────────────────────────────────────────

$search    = mysqli_real_escape_string($conn, trim($_GET['search'] ?? ''));
$filter_kat = mysqli_real_escape_string($conn, trim($_GET['kategori'] ?? ''));
$page      = max(1, (int)($_GET['page'] ?? 1));
$per_page  = 10;
$offset    = ($page - 1) * $per_page;

$where = "WHERE 1=1";
if ($search)     $where .= " AND (b.judul LIKE '%$search%' OR b.penulis LIKE '%$search%')";
if ($filter_kat) $where .= " AND b.kategori = '$filter_kat'";

$total_rows = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM buku b $where"))['c'];
$total_pages = max(1, ceil($total_rows / $per_page));

$result_buku = mysqli_query($conn, "
    SELECT b.*, COALESCE(b.total_baca, 0) AS total_baca,
           COALESCE(ROUND(AVG(u.rating),1), 0) AS rating_ulasan
    FROM buku b
    LEFT JOIN ulasan u ON u.buku_id = b.id
    $where
    GROUP BY b.id
    ORDER BY b.created_at DESC
    LIMIT $per_page OFFSET $offset
");

// ─── KPI ─────────────────────────────────────────────────────────────────────
$total_buku   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM buku"))['c'];
$buku_bulan   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM buku WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())"))['c'];
$total_baca   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_baca),0) AS c FROM buku"))['c'];
$rating_avg   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT ROUND(AVG(rating),1) AS r FROM ulasan"))['r'] ?? 0;
$total_kat    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT kategori) AS c FROM buku"))['c'];

// ─── Kategori list untuk filter & form ───────────────────────────────────────
$result_kat_list = mysqli_query($conn, "SELECT nama_kategori FROM kategori ORDER BY nama_kategori ASC");
$kat_list = [];
while ($r = mysqli_fetch_assoc($result_kat_list)) $kat_list[] = $r['nama_kategori'];

// ─── Data edit ───────────────────────────────────────────────────────────────
$edit_data = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM buku WHERE id=" . (int)$_GET['edit']));
}

$active_menu = 'buku';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kelola Buku — Pojok Baca</title>
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
    $topbar_title      = 'Kelola Buku';
    $topbar_breadcrumb = 'Kelola Buku';
    $topbar_search     = true;
    include '../includes/admin/topbar.php';
  ?>

  <div class="content-area">

    <!-- KPI -->
    <div class="kpi-grid">
      <div class="kpi-card">
        <div class="kpi-header">
          <span class="kpi-label">Total Buku</span>
          <div class="kpi-icon-wrap yellow">📚</div>
        </div>
        <div class="kpi-value"><?php echo number_format($total_buku); ?></div>
        <div class="kpi-footer">
          <span class="kpi-change">&#9650; +<?php echo $buku_bulan; ?> bulan ini</span>
          <span class="kpi-period"><?php echo date('M Y'); ?></span>
        </div>
      </div>
      <div class="kpi-card">
        <div class="kpi-header">
          <span class="kpi-label">Total Dibaca</span>
          <div class="kpi-icon-wrap green">👁️</div>
        </div>
        <div class="kpi-value"><?php echo $total_baca >= 1000 ? number_format($total_baca/1000,1).'K' : $total_baca; ?></div>
        <div class="kpi-footer">
          <span class="kpi-change">&#9650; Semua waktu</span>
          <span class="kpi-period">All time</span>
        </div>
      </div>
      <div class="kpi-card">
        <div class="kpi-header">
          <span class="kpi-label">Rating Rata-rata</span>
          <div class="kpi-icon-wrap orange">⭐</div>
        </div>
        <div class="kpi-value"><?php echo $rating_avg ?: '—'; ?></div>
        <div class="kpi-footer">
          <span class="kpi-change">&#9650; Dari semua buku</span>
          <span class="kpi-period">All time</span>
        </div>
      </div>
      <div class="kpi-card">
        <div class="kpi-header">
          <span class="kpi-label">Kategori</span>
          <div class="kpi-icon-wrap blue">🏷️</div>
        </div>
        <div class="kpi-value"><?php echo $total_kat; ?></div>
        <div class="kpi-footer">
          <span class="kpi-change">&#9650; Kategori aktif</span>
          <span class="kpi-period"><?php echo date('M Y'); ?></span>
        </div>
      </div>
    </div>
    <!-- END KPI -->


    <!-- TOOLBAR -->
    <div class="card" style="margin-bottom:20px">
      <div class="toolbar">

        <!-- Filter & Search -->
        <form method="GET" class="toolbar-filters" id="filterForm">
          <div class="search-box-inline">
            <span>&#128269;</span>
            <input type="text" name="search" placeholder="Cari judul / penulis..."
                   value="<?php echo htmlspecialchars($search); ?>">
          </div>
          <select name="kategori" class="filter-select" onchange="document.getElementById('filterForm').submit()">
            <option value="">Semua Kategori</option>
            <?php foreach ($kat_list as $k): ?>
            <option value="<?php echo htmlspecialchars($k); ?>"
              <?php echo $filter_kat === $k ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($k); ?>
            </option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn-primary">Cari</button>
          <?php if ($search || $filter_kat): ?>
          <a href="kelola_buku.php" class="btn-ghost">Reset</a>
          <?php endif; ?>
        </form>

        <!-- Tambah -->
        <a href="tambah_buku.php" class="btn-primary">
          ➕ Tambah Buku
        </a>

      </div>
    </div>


    <!-- TABEL BUKU -->
    <div class="card" style="margin-bottom:40px">
      <div class="card-title">
        Daftar Buku
        <span style="font-size:12px;color:var(--text3);font-weight:400">
          <?php echo number_format($total_rows); ?> buku ditemukan
        </span>
      </div>

      <?php if ($pesan): ?>
      <div class="alert alert-<?php echo $pesan_type; ?>" id="alertMsg">
        <?php echo $pesan === 'success' ? '✓' : ($pesan_type === 'danger' ? '🗑' : '✓'); ?>
        <?php echo htmlspecialchars($pesan); ?>
      </div>
      <?php endif; ?>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Cover</th>
              <th>Judul & Penulis</th>
              <th>Kategori</th>
              <th>Tahun</th>
              <th>Dibaca</th>
              <th>Rating</th>
              <th>Ditambahkan</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $no = $offset + 1;
            $empty = true;
            while ($row = mysqli_fetch_assoc($result_buku)):
              $empty = false;
            ?>
            <tr>
              <td style="color:var(--text3)"><?php echo $no++; ?></td>
              <td>
                <div class="book-cover-cell">
                  <?php if (!empty($row['cover_img']) && file_exists('../' . $row['cover_img'])): ?>
                    <img src="../<?php echo htmlspecialchars($row['cover_img']); ?>"
                         alt="Cover <?php echo htmlspecialchars($row['judul']); ?>"
                         style="width:40px;height:54px;object-fit:cover;border-radius:4px;display:block;">
                  <?php else: ?>
                    <div style="width:40px;height:54px;border-radius:4px;background:#1e2533;display:flex;align-items:center;justify-content:center;">
                      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="#4b5563"><path d="M18 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/></svg>
                    </div>
                  <?php endif; ?>
                </div>
              </td>
              <td>
                <div class="book-title-cell">
                  <strong><?php echo htmlspecialchars($row['judul']); ?></strong>
                  <span><?php echo htmlspecialchars($row['penulis']); ?></span>
                </div>
              </td>
              <td>
                <span class="badge badge-info"><?php echo htmlspecialchars($row['kategori'] ?? '—'); ?></span>
              </td>
              <td style="color:var(--text2)"><?php echo $row['tahun'] ?? '—'; ?></td>
              <td style="color:var(--text2)"><?php echo number_format($row['total_baca']); ?></td>
              <td>
                <?php if ($row['rating_ulasan'] > 0): ?>
                  <span class="rating-cell">⭐ <?php echo number_format($row['rating_ulasan'],1); ?></span>
                <?php else: ?>
                  <span style="color:var(--text3)">—</span>
                <?php endif; ?>
              </td>
              <td style="color:var(--text2)"><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
              <td>
                <div class="action-btns">
                  <button class="btn-icon btn-edit"
                    data-id="<?php echo $row['id']; ?>"
                    data-judul="<?php echo htmlspecialchars($row['judul'], ENT_QUOTES); ?>"
                    data-penulis="<?php echo htmlspecialchars($row['penulis'], ENT_QUOTES); ?>"
                    data-penerbit="<?php echo htmlspecialchars($row['penerbit'] ?? '', ENT_QUOTES); ?>"
                    data-tahun="<?php echo $row['tahun'] ?? ''; ?>"
                    data-kategori="<?php echo htmlspecialchars($row['kategori'] ?? '', ENT_QUOTES); ?>"
                    data-deskripsi="<?php echo htmlspecialchars($row['deskripsi'] ?? '', ENT_QUOTES); ?>"
                    data-stok="<?php echo $row['stok'] ?? 1; ?>">
                    ✏️ Edit
                  </button>
                  <a href="kelola_buku.php?hapus=<?php echo $row['id']; ?>"
                     class="btn-icon del"
                     onclick="return confirm('Hapus buku \'<?php echo addslashes($row['judul']); ?>\'? Aksi ini tidak bisa dibatalkan.')">
                    🗑
                  </a>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
            <?php if ($empty): ?>
            <tr>
              <td colspan="10" class="empty-state">
                <?php echo $search || $filter_kat ? 'Tidak ada buku yang sesuai filter.' : 'Belum ada data buku.'; ?>
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
          <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&kategori=<?php echo urlencode($filter_kat); ?>" class="page-btn">&#8592;</a>
        <?php endif; ?>

        <?php
        $start = max(1, $page - 2);
        $end   = min($total_pages, $page + 2);
        if ($start > 1) echo '<span class="page-dots">…</span>';
        for ($i = $start; $i <= $end; $i++):
        ?>
          <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&kategori=<?php echo urlencode($filter_kat); ?>"
             class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
            <?php echo $i; ?>
          </a>
        <?php endfor;
        if ($end < $total_pages) echo '<span class="page-dots">…</span>'; ?>

        <?php if ($page < $total_pages): ?>
          <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&kategori=<?php echo urlencode($filter_kat); ?>" class="page-btn">&#8594;</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

    </div>
    <!-- END TABEL -->

  </div>
  <!-- END CONTENT AREA -->

</main>

<!-- ========== MODAL TAMBAH / EDIT ========== -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal" id="modalBuku">
    <div class="modal-header">
      <div class="modal-title" id="modalTitle">Tambah Buku Baru</div>
      <button class="modal-close" id="modalClose">&#10005;</button>
    </div>
    <form method="POST" class="modal-body" id="formBuku">
      <input type="hidden" name="aksi" value="simpan">
      <input type="hidden" name="edit_id" id="inputEditId" value="0">

      <div class="form-row">
        <div class="form-group" style="flex:2">
          <label>Judul Buku <span class="req">*</span></label>
          <input type="text" name="judul" id="inputJudul" placeholder="Masukkan judul buku" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Penulis <span class="req">*</span></label>
          <input type="text" name="penulis" id="inputPenulis" placeholder="Nama penulis" required>
        </div>
        <div class="form-group">
          <label>Penerbit</label>
          <input type="text" name="penerbit" id="inputPenerbit" placeholder="Nama penerbit">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Kategori <span class="req">*</span></label>
          <select name="kategori" id="inputKategori" required>
            <option value="">— Pilih Kategori —</option>
            <?php foreach ($kat_list as $k): ?>
            <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($k); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Tahun Terbit</label>
          <input type="number" name="tahun" id="inputTahun" min="1900" max="<?php echo date('Y'); ?>"
                 placeholder="<?php echo date('Y'); ?>">
        </div>
        <div class="form-group" style="flex:0 0 110px">
          <label>Stok</label>
          <input type="number" name="stok" id="inputStok" min="0" value="1">
        </div>
      </div>

      <div class="form-group">
        <label>Deskripsi</label>
        <textarea name="deskripsi" id="inputDeskripsi" rows="4" placeholder="Sinopsis atau deskripsi singkat..."></textarea>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-ghost" id="btnCancel">Batal</button>
        <button type="submit" class="btn-primary" id="btnSubmit">💾 Simpan Buku</button>
      </div>
    </form>
  </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast">
  <span class="toast-icon" id="toast-icon">✓</span>
  <span id="toast-msg">Berhasil!</span>
</div>

<script>
// ─── Sidebar Toggle ───────────────────────────────────────────────────────────
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebar       = document.getElementById('sidebar');
const mainContent   = document.getElementById('mainContent');
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

// ─── Topbar Search ────────────────────────────────────────────────────────────
document.getElementById('topbarSearch').addEventListener('keydown', function(e) {
  if (e.key === 'Enter' && this.value.trim()) {
    window.location.href = 'kelola_buku.php?search=' + encodeURIComponent(this.value.trim());
  }
});

// ─── Modal ────────────────────────────────────────────────────────────────────
const overlay   = document.getElementById('modalOverlay');
const modal     = document.getElementById('modalBuku');
const modalTitle = document.getElementById('modalTitle');
const btnSubmit = document.getElementById('btnSubmit');

function openModal(title, submitLabel) {
  modalTitle.textContent = title;
  btnSubmit.textContent  = submitLabel;
  overlay.classList.add('show');
  document.body.style.overflow = 'hidden';
}
function closeModal() {
  overlay.classList.remove('show');
  document.body.style.overflow = '';
  document.getElementById('formBuku').reset();
  document.getElementById('inputEditId').value = '0';
}

document.getElementById('modalClose').addEventListener('click', closeModal);
document.getElementById('btnCancel').addEventListener('click', closeModal);
overlay.addEventListener('click', (e) => { if (e.target === overlay) closeModal(); });

// Edit buttons
document.querySelectorAll('.btn-edit').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById('inputEditId').value   = btn.dataset.id;
    document.getElementById('inputJudul').value    = btn.dataset.judul;
    document.getElementById('inputPenulis').value  = btn.dataset.penulis;
    document.getElementById('inputPenerbit').value = btn.dataset.penerbit;
    document.getElementById('inputTahun').value    = btn.dataset.tahun;
    document.getElementById('inputDeskripsi').value = btn.dataset.deskripsi;
    document.getElementById('inputStok').value     = btn.dataset.stok;

    // Set select kategori
    const sel = document.getElementById('inputKategori');
    for (let opt of sel.options) {
      opt.selected = opt.value === btn.dataset.kategori;
    }

    openModal('Edit Buku', '💾 Update Buku');
  });
});

// Auto-open modal jika ada ?edit=
<?php if ($edit_data): ?>
document.querySelector('.btn-edit[data-id="<?php echo $edit_data['id']; ?>"]')?.click();
<?php endif; ?>

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
// Hapus alert dari URL agar tidak muncul lagi di refresh
if (window.history.replaceState) {
  const url = new URL(window.location.href);
  url.searchParams.delete('hapus');
  window.history.replaceState({}, '', url.toString());
}
<?php endif; ?>

// Auto-hide alert
const alertEl = document.getElementById('alertMsg');
if (alertEl) setTimeout(() => alertEl.style.display = 'none', 4000);
</script>

</body>
</html>