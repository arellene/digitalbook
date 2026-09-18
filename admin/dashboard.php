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

// ─── Statistik ───────────────────────────────────────────────────────────────

$total_ebook = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total FROM buku"
))['total'];

$total_anggota = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total FROM users WHERE role = 'anggota'"
))['total'];

$total_akses = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total FROM riwayat_baca"
))['total'];

$rating_rata = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT ROUND(AVG(rating), 1) AS avg_rating FROM ulasan"
))['avg_rating'] ?? 0;

$akses_bulan_ini = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total FROM riwayat_baca
    WHERE MONTH(tanggal_akses) = MONTH(CURDATE())
      AND YEAR(tanggal_akses)  = YEAR(CURDATE())
"))['total'];

$ebook_baru_bulan_ini = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total FROM buku
    WHERE MONTH(created_at) = MONTH(CURDATE())
      AND YEAR(created_at)  = YEAR(CURDATE())
"))['total'];

// ─── Buku paling populer ──────────────────────────────────────────────────────
$result_populer = mysqli_query($conn, "
    SELECT b.id, b.judul, b.penulis, b.cover_emoji, b.cover_img, b.total_baca,
           COALESCE(ROUND(AVG(u.rating),1), 0) AS rating
    FROM buku b
    LEFT JOIN ulasan u ON u.buku_id = b.id
    GROUP BY b.id
    ORDER BY b.total_baca DESC
    LIMIT 5
");
$populer_list = [];
$max_baca = 1;
while ($row = mysqli_fetch_assoc($result_populer)) {
    $populer_list[] = $row;
    if ($row['total_baca'] > $max_baca) $max_baca = $row['total_baca'];
}

// ─── Distribusi kategori ──────────────────────────────────────────────────────
$result_kategori = mysqli_query($conn, "
    SELECT k.nama_kategori AS nama, COUNT(b.id) AS jml
    FROM kategori k
    LEFT JOIN buku b ON b.kategori = k.nama_kategori
    GROUP BY k.id, k.nama_kategori
    ORDER BY jml DESC
    LIMIT 6
");
$kategori_list = [];
$max_cat = 1;
while ($row = mysqli_fetch_assoc($result_kategori)) {
    $kategori_list[] = $row;
    if ($row['jml'] > $max_cat) $max_cat = $row['jml'];
}

// ─── Aktivitas terbaru ────────────────────────────────────────────────────────
$result_aktivitas = mysqli_query($conn, "
    SELECT
        u.nama_lengkap,
        b.judul,
        p.tanggal_akses,
        p.status,
        CASE
            WHEN p.status = 'aktif'  THEN 'akses'
            ELSE 'selesai'
        END AS tipe
    FROM riwayat_baca p
    JOIN buku   b ON p.id_buku    = b.id
    JOIN users  u ON p.id_anggota = u.id
    ORDER BY p.id DESC
    LIMIT 7
");

// ─── Akses  terbaru (tabel) ───────────────────────────────────────
$result_pinjam = mysqli_query($conn, "
    SELECT
        p.id,
        u.nama_lengkap,
        b.judul,
        p.tanggal_akses,
        p.tanggal_kembali,
        p.status,
        COALESCE(p.progress, 0) AS progress
    FROM riwayat_baca p
    JOIN buku   b ON p.id_buku    = b.id
    JOIN users  u ON p.id_anggota = u.id
    ORDER BY p.tanggal_akses DESC
    LIMIT 5
");

// ─── Anggota terbaru (tabel) ──────────────────────────────────────────────────
$result_anggota = mysqli_query($conn, "
    SELECT id, nama_lengkap, username, email, role, status, created_at
    FROM users
    ORDER BY created_at DESC
    LIMIT 5
");

$active_menu = 'dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Admin — Pojok Baca</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <!-- Satu file CSS untuk semua (sidebar + dashboard sudah di-merge) -->
  <link rel="stylesheet" href="../assets/css/admin/dashboard.css">
  <link rel="stylesheet" href="../assets/css/admin/sidebar.css">
</head>
<body>

<!-- ========== SIDEBAR ========== -->
<?php include '../includes/admin/sidebar.php'; ?>

<!-- ========== MAIN CONTENT ========== -->
<main class="main-content" id="mainContent">

  <!-- TOPBAR -->
  <?php
    $topbar_title      = 'Dashboard';
    $topbar_breadcrumb = 'Dashboard';
    $topbar_search     = true;
    include '../includes/admin/topbar.php';
  ?>

  <!-- CONTENT -->
  <div class="content-area">

    <!-- KPI CARDS -->
    <div class="kpi-grid">

      <div class="kpi-card">
        <div class="kpi-header">
          <span class="kpi-label">Total eBook</span>
          <div class="kpi-icon-wrap yellow">&#128218;</div>
        </div>
        <div class="kpi-value"><?php echo $total_ebook; ?></div>
        <div class="kpi-footer">
          <span class="kpi-change">&#9650; +<?php echo $ebook_baru_bulan_ini; ?> bulan ini</span>
          <span class="kpi-period"><?php echo date('M Y'); ?></span>
        </div>
      </div>

      <div class="kpi-card">
        <div class="kpi-header">
          <span class="kpi-label">Total Anggota</span>
          <div class="kpi-icon-wrap blue">&#128101;</div>
        </div>
        <div class="kpi-value"><?php echo $total_anggota; ?></div>
        <div class="kpi-footer">
          <span class="kpi-change">&#9650; Anggota terdaftar</span>
          <span class="kpi-period"><?php echo date('M Y'); ?></span>
        </div>
      </div>

      <div class="kpi-card">
        <div class="kpi-header">
          <span class="kpi-label">Total Akses</span>
          <div class="kpi-icon-wrap green">&#128065;</div>
        </div>
        <div class="kpi-value">
          <?php echo $total_akses >= 1000
            ? number_format($total_akses / 1000, 1) . 'K'
            : $total_akses; ?>
        </div>
        <div class="kpi-footer">
          <span class="kpi-change">&#9650; <?php echo $akses_bulan_ini; ?> bulan ini</span>
          <span class="kpi-period"><?php echo date('M Y'); ?></span>
        </div>
      </div>

      <div class="kpi-card">
        <div class="kpi-header">
          <span class="kpi-label">Rating Rata-rata</span>
          <div class="kpi-icon-wrap orange">&#11088;</div>
        </div>
        <div class="kpi-value"><?php echo $rating_rata ?: '&#8212;'; ?></div>
        <div class="kpi-footer">
          <span class="kpi-change">&#9650; Dari semua eBook</span>
          <span class="kpi-period">All time</span>
        </div>
      </div>

    </div>
    <!-- END KPI -->


    <!-- DASHBOARD GRID -->
    <div class="dash-grid">

      <!-- KIRI -->
      <div class="dash-col">

        <!-- Buku Populer -->
        <div class="card">
          <div class="card-title">
            Buku Paling Populer
            <a href="../admin/kelola_buku.php" class="card-link">Lihat semua &rarr;</a>
          </div>
          <div class="popular-list">
            <?php if (empty($populer_list)): ?>
              <div class="empty-state">Belum ada data buku.</div>
            <?php else: ?>
              <?php foreach ($populer_list as $i => $b): ?>
              <div class="popular-item">
                <div class="pop-rank"><?php echo $i + 1; ?></div>
                <div class="pop-emoji" style="width:48px;height:64px;flex-shrink:0;overflow:hidden;border-radius:6px;display:flex;align-items:center;justify-content:center;">
                  <?php if (!empty($b['cover_img'])): ?>
                    <img src="../<?php echo htmlspecialchars($b['cover_img']); ?>"
                         alt="<?php echo htmlspecialchars($b['judul']); ?>"
                         style="width:100%;height:100%;object-fit:cover;object-position:center;border-radius:6px;display:block;">
                  <?php else: ?>
                    <?php echo htmlspecialchars($b['cover_emoji'] ?? '📗'); ?>
                  <?php endif; ?>
                </div>
                <div class="pop-info">
                  <div class="pop-title"><?php echo htmlspecialchars($b['judul']); ?></div>
                  <div class="pop-reads">
                    <?php echo htmlspecialchars($b['penulis']); ?>
                    &middot; <?php echo number_format($b['total_baca']); ?> baca
                  </div>
                </div>
                <div class="pop-bar-wrap">
                  <div class="pop-bar">
                    <div class="pop-bar-fill"
                      style="width:<?php echo round($b['total_baca'] / $max_baca * 100); ?>%">
                    </div>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Distribusi Kategori -->
        <div class="card">
          <div class="card-title">
            Distribusi Kategori
            <a href="../admin/kategori.php" class="card-link">Kelola &rarr;</a>
          </div>
          <div class="cat-chart">
            <?php if (empty($kategori_list)): ?>
              <div class="empty-state">Belum ada data kategori.</div>
            <?php else: ?>
              <?php foreach ($kategori_list as $k): ?>
              <div class="cat-bar-item">
                <div class="cat-bar-label"><?php echo htmlspecialchars($k['nama']); ?></div>
                <div class="cat-bar-track">
                  <div class="cat-bar-fill"
                    style="width:<?php echo round($k['jml'] / $max_cat * 100); ?>%">
                  </div>
                </div>
                <div class="cat-bar-val"><?php echo $k['jml']; ?></div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

      </div>
      <!-- END KIRI -->


      <!-- KANAN: Aktivitas -->
      <div>
        <div class="card" style="height:100%">
          <div class="card-title">
            Aktivitas Terbaru
            <a href="../admin/riwayat_aktivitas.php" class="card-link">Semua &rarr;</a>
          </div>
          <div class="activity-list">
            <?php
            $no_aktivitas = true;
            while ($row = mysqli_fetch_assoc($result_aktivitas)):
              $no_aktivitas = false;
              $dot_class = $row['tipe'] === 'selesai' ? 'green' : ($row['tipe'] === 'akses' ? '' : 'blue');
            ?>
            <div class="activity-item">
              <div class="act-dot <?php echo $dot_class; ?>"></div>
              <div class="act-text">
                <strong><?php echo htmlspecialchars($row['nama_lengkap']); ?></strong>
                <?php echo $row['tipe'] === 'selesai' ? 'selesai membaca' : 'mengakses'; ?>
                "<?php echo htmlspecialchars($row['judul']); ?>"
              </div>
              <div class="act-time">
                <?php echo date('d M', strtotime($row['tanggal_akses'])); ?>
              </div>
            </div>
            <?php endwhile; ?>
            <?php if ($no_aktivitas): ?>
              <div class="empty-state">Belum ada aktivitas.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <!-- END KANAN -->

    </div>
    <!-- END DASH GRID -->


    <!-- TABEL RIWAYAT AKTIVITAS TERBARU -->
    <div class="card" style="margin-top:20px">
      <div class="card-title">
        Riwayat Aktivitas Terbaru
        <a href="../admin/riwayat_aktivitas.php" class="card-link">Lihat semua &rarr;</a>
      </div>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Anggota</th>
              <th>Judul eBook</th>
              <th>Mulai Dibaca</th>
              <th>Terakhir Dibaca</th>
              <th>Progress</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $no_pinjam = true;
            while ($row = mysqli_fetch_assoc($result_pinjam)):
              $no_pinjam = false;
              $prog = ($row['status'] === 'selesai') ? 100 : (int)($row['progress'] ?? 0);
              if ($row['status'] === 'selesai')       { $badge = 'badge-info';    $label = '&#10003; Selesai'; }
              elseif ($row['status'] === 'sedang_dibaca') { $badge = 'badge-success'; $label = '&#128214; Sedang Dibaca'; }
              else                                    { $badge = 'badge-muted';   $label = '&#8212; Tidak Aktif'; }
            ?>
            <tr>
              <td style="color:var(--text3)">#<?php echo str_pad($row['id'], 4, '0', STR_PAD_LEFT); ?></td>
              <td><?php echo htmlspecialchars($row['nama_lengkap']); ?></td>
              <td><?php echo htmlspecialchars($row['judul']); ?></td>
              <td style="color:var(--text2)"><?php echo date('d M Y', strtotime($row['tanggal_akses'])); ?></td>
              <td style="color:var(--text2)">
                <?php echo $row['tanggal_kembali']
                  ? date('d M Y', strtotime($row['tanggal_kembali']))
                  : '<span style="color:var(--text3)">—</span>'; ?>
              </td>
              <td>
                <div style="display:flex;align-items:center;gap:8px;min-width:80px">
                  <div style="flex:1;height:6px;background:var(--border);border-radius:99px;overflow:hidden">
                    <div style="height:100%;width:<?php echo $prog; ?>%;background:<?php echo $prog===100?'#2ecc8a':'#5a9cf0'; ?>;border-radius:99px;transition:width .4s"></div>
                  </div>
                  <span style="font-size:11px;color:var(--text2);white-space:nowrap"><?php echo $prog; ?>%</span>
                </div>
              </td>
              <td><span class="badge <?php echo $badge; ?>"><?php echo $label; ?></span></td>
              <td>
                <a href="../admin/riwayat_aktivitas.php?detail=<?php echo $row['id']; ?>" class="btn-icon">
                  &#128065; Detail
                </a>
              </td>
            </tr>
            <?php endwhile; ?>
            <?php if ($no_pinjam): ?>
            <tr>
              <td colspan="8" style="text-align:center;padding:24px;color:var(--text3)">
                Belum ada riwayat aktivitas.
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>


    <!-- TABEL ANGGOTA TERBARU -->
    <div class="card" style="margin-top:20px;margin-bottom:40px">
      <div class="card-title">
        Anggota Terbaru
        <a href="../admin/anggota.php" class="card-link">Lihat semua &rarr;</a>
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
            $no_anggota = true;
            $no = 1;
            while ($row = mysqli_fetch_assoc($result_anggota)):
              $no_anggota = false;
              $badge_role   = $row['role'] === 'admin' ? 'badge-warn' : 'badge-info';
              $badge_status = (isset($row['status']) && $row['status'] === 'nonaktif') ? 'badge-danger' : 'badge-success';
              $label_status = (isset($row['status']) && $row['status'] === 'nonaktif') ? 'Nonaktif' : 'Aktif';
            ?>
            <tr>
              <td style="color:var(--text3)"><?php echo $no++; ?></td>
              <td><strong><?php echo htmlspecialchars($row['nama_lengkap']); ?></strong></td>
              <td style="color:var(--text2)"><?php echo htmlspecialchars($row['username']); ?></td>
              <td style="color:var(--text2)"><?php echo htmlspecialchars($row['email']); ?></td>
              <td><span class="badge <?php echo $badge_role; ?>"><?php echo $row['role']; ?></span></td>
              <td><span class="badge <?php echo $badge_status; ?>"><?php echo $label_status; ?></span></td>
              <td style="color:var(--text2)">
                <?php echo date('d M Y', strtotime($row['created_at'])); ?>
              </td>
              <td>
                <div class="action-btns">
                  <a href="../admin/anggota.php?edit=<?php echo $row['id']; ?>" class="btn-icon">&#9998; Edit</a>
                  <a href="../admin/anggota.php?hapus=<?php echo $row['id']; ?>"
                     class="btn-icon del"
                     onclick="return confirm('Hapus anggota ini?')">&#128465;</a>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
            <?php if ($no_anggota): ?>
            <tr>
              <td colspan="8" style="text-align:center;padding:24px;color:var(--text3)">
                Belum ada data anggota.
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

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
    darkBtn.innerHTML  = isDark ? '&#9728;' : '&#9790;';
    darkBtn.title = isDark ? 'Light Mode' : 'Dark Mode';
    localStorage.setItem('darkMode', isDark ? 'enabled' : 'disabled');
  });
  if (localStorage.getItem('darkMode') === 'enabled') {
    document.body.classList.add('dark-mode');
    darkBtn.innerHTML = '&#9728;';
    darkBtn.title = 'Light Mode';
  }
}

// ─── Topbar Search ───────────────────────────────────────────────────────────
document.getElementById('topbarSearch').addEventListener('keydown', function(e) {
  if (e.key === 'Enter' && this.value.trim()) {
    window.location.href = '../admin/kelola_buku.php?search=' + encodeURIComponent(this.value.trim());
  }
});

// ─── Animasi bar ─────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.cat-bar-fill, .pop-bar-fill').forEach(el => {
    const w = el.style.width;
    el.style.width = '0';
    setTimeout(() => { el.style.width = w; }, 120);
  });
});

// ─── Toast ───────────────────────────────────────────────────────────────────
let toastTimer;
function showToast(msg, icon = '✓') {
  document.getElementById('toast-msg').textContent = msg;
  document.getElementById('toast-icon').textContent = icon;
  const t = document.getElementById('toast');
  t.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.classList.remove('show'), 3000);
}
</script>

</body>
</html>