<?php
/**
 * Riwayat Aktivitas Membaca — Admin PojokBaca
 * Path: admin/riwayat_aktivitas.php
 */
session_start();
require_once '../config/database.php';

// ─── Autentikasi ──────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../anggota/dashboard.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// ─── Hapus riwayat (sebelum query lain) ──────────────────────────────────────
$toast_msg  = '';
$toast_type = '';

if (isset($_GET['hapus']) && is_numeric($_GET['hapus'])) {
    $del_id   = (int) $_GET['hapus'];
    $del_stmt = mysqli_prepare($conn, "DELETE FROM riwayat_baca WHERE id = ?");
    mysqli_stmt_bind_param($del_stmt, "i", $del_id);
    if (mysqli_stmt_execute($del_stmt)) {
        $toast_msg  = "Riwayat membaca berhasil dihapus.";
        $toast_type = "success";
    } else {
        $toast_msg  = "Gagal menghapus data.";
        $toast_type = "error";
    }
    mysqli_stmt_close($del_stmt);

    // Redirect untuk hindari re-submit
    $redirect = 'riwayat_aktivitas.php?toast=' . urlencode($toast_msg) . '&toast_type=' . $toast_type;
    header("Location: $redirect");
    exit();
}

// Ambil toast dari redirect
if (isset($_GET['toast'])) {
    $toast_msg  = $_GET['toast'];
    $toast_type = $_GET['toast_type'] ?? 'success';
}

// ─── Filter & Pagination ─────────────────────────────────────────────────────
$per_page = 10;
$page     = max(1, (int) ($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$search         = trim($_GET['search']   ?? '');
$filter_status  = $_GET['status']        ?? '';
$filter_kat     = trim($_GET['kategori'] ?? '');
$filter_date    = $_GET['tanggal']       ?? '';

// ─── WHERE dinamis ────────────────────────────────────────────────────────────
$where_parts = ["1=1"];
$params      = [];
$types       = "";

if ($search !== '') {
    $where_parts[] = "(u.nama_lengkap LIKE ? OR b.judul LIKE ? OR u.username LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like);
    $types .= "sss";
}
if ($filter_status !== '') {
    $where_parts[] = "p.status = ?";
    $params[] = $filter_status;
    $types   .= "s";
}
if ($filter_kat !== '') {
    $where_parts[] = "b.kategori = ?";
    $params[] = $filter_kat;
    $types   .= "s";
}
if ($filter_date !== '') {
    $where_parts[] = "DATE(p.tanggal_akses) = ?";
    $params[] = $filter_date;
    $types   .= "s";
}

$where_sql = implode(" AND ", $where_parts);

// ─── Export CSV ─────────────────────────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exp_sql  = "SELECT p.id, u.nama_lengkap, u.username, b.judul, b.kategori,
                        p.tanggal_akses, p.tanggal_kembali,
                        COALESCE(p.progress, 0) AS progress, p.status
                 FROM riwayat_baca p
                 JOIN buku  b ON p.id_buku    = b.id
                 JOIN users u ON p.id_anggota = u.id
                 WHERE $where_sql
                 ORDER BY p.tanggal_akses DESC";
    $exp_stmt = mysqli_prepare($conn, $exp_sql);
    if ($types) mysqli_stmt_bind_param($exp_stmt, $types, ...$params);
    mysqli_stmt_execute($exp_stmt);
    $exp_result = mysqli_stmt_get_result($exp_stmt);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="riwayat_aktivitas_' . date('Ymd_His') . '.csv"');
    $fp = fopen('php://output', 'w');
    fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($fp, ['ID','Nama Anggota','Username','Judul eBook','Kategori','Mulai Dibaca','Terakhir Dibaca','Progress (%)','Status']);
    while ($row = mysqli_fetch_assoc($exp_result)) {
        $prog_exp = ($row['status'] === 'selesai') ? 100 : (int)($row['progress'] ?? 0);
        fputcsv($fp, [
            '#' . str_pad($row['id'], 4, '0', STR_PAD_LEFT),
            $row['nama_lengkap'],
            '@' . $row['username'],
            $row['judul'],
            $row['kategori'],
            $row['tanggal_akses']        ? date('d M Y H:i', strtotime($row['tanggal_akses']))        : '-',
            !empty($row['tanggal_kembali']) ? date('d M Y H:i', strtotime($row['tanggal_kembali'])) : '-',
            $prog_exp,
            $row['status'],
        ]);
    }
    fclose($fp);
    exit();
}

// ─── Total rows ───────────────────────────────────────────────────────────────
$count_sql  = "SELECT COUNT(*) AS total
               FROM riwayat_baca p
               JOIN buku  b ON p.id_buku    = b.id
               JOIN users u ON p.id_anggota = u.id
               WHERE $where_sql";
$count_stmt = mysqli_prepare($conn, $count_sql);
if ($types) mysqli_stmt_bind_param($count_stmt, $types, ...$params);
mysqli_stmt_execute($count_stmt);
$total_rows  = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'];
mysqli_stmt_close($count_stmt);
$total_pages = max(1, ceil($total_rows / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;

// ─── Data utama ───────────────────────────────────────────────────────────────
$data_sql = "SELECT
                p.id,
                u.nama_lengkap,
                u.username,
                u.email,
                b.judul,
                b.penulis,
                b.cover_img,
                b.kategori,
                p.tanggal_akses,
                p.tanggal_kembali,
                p.status,
                COALESCE(p.progress, 0) AS progress,
                b.cover_img,
                b.cover_emoji
             FROM riwayat_baca p
             JOIN buku  b ON p.id_buku    = b.id
             JOIN users u ON p.id_anggota = u.id
             WHERE $where_sql
             ORDER BY p.tanggal_akses DESC
             LIMIT ? OFFSET ?";

$all_params = array_merge($params, [$per_page, $offset]);
$all_types  = $types . "ii";
$data_stmt  = mysqli_prepare($conn, $data_sql);
mysqli_stmt_bind_param($data_stmt, $all_types, ...$all_params);
mysqli_stmt_execute($data_stmt);
$result_rows = mysqli_stmt_get_result($data_stmt);

// ─── Statistik KPI ────────────────────────────────────────────────────────────
$stat_total   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM riwayat_baca"))['c'];
$stat_reading = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM riwayat_baca WHERE status='sedang_dibaca'"))['c'];
$stat_done    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM riwayat_baca WHERE status='selesai'"))['c'];
$stat_bulan   = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS c FROM riwayat_baca
    WHERE MONTH(tanggal_akses)=MONTH(CURDATE())
      AND YEAR(tanggal_akses)=YEAR(CURDATE())
"))['c'];

// ─── Daftar kategori untuk dropdown ──────────────────────────────────────────
$result_kategori = mysqli_query($conn,
    "SELECT DISTINCT b.kategori FROM buku b WHERE b.kategori IS NOT NULL ORDER BY b.kategori ASC"
);

// ─── Modal detail ─────────────────────────────────────────────────────────────
$detail_row = null;
if (isset($_GET['detail']) && is_numeric($_GET['detail'])) {
    $det_stmt = mysqli_prepare($conn, "
        SELECT p.*, u.nama_lengkap, u.username, u.email,
               b.judul, b.penulis, b.cover_img, b.kategori
        FROM riwayat_baca p
        JOIN users u ON p.id_anggota = u.id
        JOIN buku  b ON p.id_buku    = b.id
        WHERE p.id = ?
    ");
    $det_id = (int) $_GET['detail'];
    mysqli_stmt_bind_param($det_stmt, "i", $det_id);
    mysqli_stmt_execute($det_stmt);
    $detail_row = mysqli_fetch_assoc(mysqli_stmt_get_result($det_stmt));
    mysqli_stmt_close($det_stmt);
}


// ─── Helper functions ─────────────────────────────────────────────────────────

/**
 * Menghasilkan inisial + warna avatar dari nama
 */
function avatar_info(string $name): array {
    $initials = strtoupper(substr(trim($name), 0, 1));
    $colors   = ['#f0a500','#5a9cf0','#2ecc8a','#e06c2a','#9b59b6','#e05a5a','#1abc9c','#3498db'];
    $idx      = abs(crc32($name)) % count($colors);
    return ['initial' => $initials, 'color' => $colors[$idx]];
}

/**
 * Status badge HTML
 */
function status_badge(string $status): string {
    return match($status) {
        'sedang_dibaca' => '<span class="badge badge-success">&#128214; Sedang Dibaca</span>',
        'selesai'       => '<span class="badge badge-info">&#10003; Selesai</span>',
        default         => '<span class="badge badge-muted">&#8212; Tidak Aktif</span>',
    };
}

/**
 * Progress bar CSS class
 */
function progress_class(string $status): string {
    return match($status) {
        'sedang_dibaca' => 'reading',
        'selesai'       => 'done',
        default         => 'idle',
    };
}

/**
 * Format durasi menit ke jam/menit
 */
function format_durasi(?int $menit): string {
    if (!$menit) return '—';
    $j = intdiv($menit, 60);
    $m = $menit % 60;
    if ($j > 0 && $m > 0) return "{$j}j {$m}m";
    if ($j > 0)            return "{$j} jam";
    return "{$m} menit";
}

// ─── Query string pembantu ─────────────────────────────────────────────────────
$qs_arr = array_filter([
    'search'   => $search,
    'status'   => $filter_status,
    'kategori' => $filter_kat,
    'tanggal'  => $filter_date,
]);
$qs = $qs_arr ? '&' . http_build_query($qs_arr) : '';

$active_menu = 'riwayat_aktivitas';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Riwayat Aktivitas Membaca — PojokBaca</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/admin/dashboard.css">
  <link rel="stylesheet" href="../assets/css/admin/sidebar.css">
  <link rel="stylesheet" href="../assets/css/admin/riwayat_aktivitas.css">
</head>
<body>

<!-- ========== SIDEBAR ========== -->
<?php include '../includes/admin/sidebar.php'; ?>

<!-- ========== MAIN CONTENT ========== -->
<main class="main-content" id="mainContent">

  <!-- TOPBAR -->
  <?php
    $topbar_title      = 'Riwayat Aktivitas Membaca';
    $topbar_breadcrumb = 'Riwayat Aktivitas';
    $topbar_search     = true;
    include '../includes/admin/topbar.php';
  ?>

  <!-- CONTENT -->
  <div class="content-area">

    <!-- ── KPI CARDS ──────────────────────────────────────────────────────── -->
    <div class="kpi-grid">

      <div class="kpi-card">
        <div class="kpi-header">
          <span class="kpi-label">Total Dibaca</span>
          <div class="kpi-icon-wrap blue">&#128065;</div>
        </div>
        <div class="kpi-value"><?php echo number_format($stat_total); ?></div>
        <div class="kpi-footer">
          <span class="kpi-change">&#9650; Semua riwayat</span>
          <span class="kpi-period">All time</span>
        </div>
      </div>

      <div class="kpi-card">
        <div class="kpi-header">
          <span class="kpi-label">Sedang Dibaca</span>
          <div class="kpi-icon-wrap green">&#128214;</div>
        </div>
        <div class="kpi-value"><?php echo number_format($stat_reading); ?></div>
        <div class="kpi-footer">
          <span class="kpi-change">&#9650; Aktif sekarang</span>
          <span class="kpi-period">Saat ini</span>
        </div>
      </div>

      <div class="kpi-card">
        <div class="kpi-header">
          <span class="kpi-label">Selesai Dibaca</span>
          <div class="kpi-icon-wrap orange">&#10003;</div>
        </div>
        <div class="kpi-value"><?php echo number_format($stat_done); ?></div>
        <div class="kpi-footer">
          <span class="kpi-change">&#9650; Total selesai</span>
          <span class="kpi-period">All time</span>
        </div>
      </div>

      <div class="kpi-card">
        <div class="kpi-header">
          <span class="kpi-label">Aktivitas Bulan Ini</span>
          <div class="kpi-icon-wrap yellow">&#128197;</div>
        </div>
        <div class="kpi-value"><?php echo number_format($stat_bulan); ?></div>
        <div class="kpi-footer">
          <span class="kpi-change">&#9650; Bulan berjalan</span>
          <span class="kpi-period"><?php echo date('M Y'); ?></span>
        </div>
      </div>

    </div>
    <!-- END KPI -->


    <!-- ── TOOLBAR ────────────────────────────────────────────────────────── -->
    <div class="toolbar">
      <div class="toolbar-left">
        <a href="riwayat_aktivitas.php?export=csv<?php echo $qs; ?>" class="btn btn-secondary">
          &#128229; Export CSV
        </a>
        <a href="riwayat_aktivitas.php<?php echo $qs_arr ? '?' . http_build_query($qs_arr) : ''; ?>" class="btn btn-ghost">
          &#8635; Refresh Data
        </a>
      </div>
    </div>


    <!-- ── FILTER BAR ─────────────────────────────────────────────────────── -->
    <form method="GET" action="riwayat_aktivitas.php" class="filter-bar" id="filterForm">

      <!-- Cari -->
      <div class="filter-group" style="flex:2; min-width:200px;">
        <div class="filter-search-wrap" style="flex:1">
          <span class="filter-search-icon">&#128269;</span>
          <input type="text" name="search" class="filter-input"
                 placeholder="Cari nama anggota atau judul buku..."
                 value="<?php echo htmlspecialchars($search); ?>">
        </div>
      </div>

      <div class="filter-divider"></div>

      <!-- Status -->
      <div class="filter-group">
        <label class="filter-label">Status</label>
        <select name="status" class="filter-select">
          <option value="">Semua Status</option>
          <option value="sedang_dibaca" <?php echo $filter_status==='sedang_dibaca'?'selected':''; ?>>Sedang Dibaca</option>
          <option value="selesai"       <?php echo $filter_status==='selesai'?'selected':''; ?>>Selesai</option>
          <option value="tidak_aktif"   <?php echo $filter_status==='tidak_aktif'?'selected':''; ?>>Tidak Aktif</option>
        </select>
      </div>

      <!-- Kategori -->
      <div class="filter-group">
        <label class="filter-label">Kategori</label>
        <select name="kategori" class="filter-select">
          <option value="">Semua Kategori</option>
          <?php
          mysqli_data_seek($result_kategori, 0);
          while ($kat = mysqli_fetch_assoc($result_kategori)):
            $sel = $filter_kat === $kat['kategori'] ? 'selected' : '';
          ?>
          <option value="<?php echo htmlspecialchars($kat['kategori']); ?>" <?php echo $sel; ?>>
            <?php echo htmlspecialchars($kat['kategori']); ?>
          </option>
          <?php endwhile; ?>
        </select>
      </div>

      <!-- Tanggal -->
      <div class="filter-group">
        <label class="filter-label">Tanggal</label>
        <input type="date" name="tanggal" class="filter-input" style="max-width:160px"
               value="<?php echo htmlspecialchars($filter_date); ?>">
      </div>

      <div class="filter-divider"></div>

      <!-- Aksi -->
      <div class="filter-actions">
        <button type="submit" class="btn btn-primary">&#128269; Filter</button>
        <a href="riwayat_aktivitas.php" class="btn btn-ghost">&#10005; Reset</a>
      </div>

    </form>


    <!-- ── TABLE CARD ─────────────────────────────────────────────────────── -->
    <div class="card">

      <div class="card-header">
        <div class="card-title-block">
          <span class="card-title">Riwayat Aktivitas Membaca</span>
          <span class="card-count"><?php echo number_format($total_rows); ?> data</span>
        </div>
      </div>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Anggota</th>
              <th>eBook</th>
              <th>Mulai Dibaca</th>
              <th>Terakhir Dibaca</th>
              <th>Progress</th>
              <th>Status</th>
              <th style="text-align:center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $no_data = true;
            while ($row = mysqli_fetch_assoc($result_rows)):
              $no_data = false;
              $av      = avatar_info($row['nama_lengkap']);
              $prog    = ($row['status'] === 'selesai') ? 100 : (int) ($row['progress'] ?? 0);
              $pc      = progress_class($row['status']);
              $id_pad  = '#' . str_pad($row['id'], 4, '0', STR_PAD_LEFT);

              // tanggal terakhir — gunakan tanggal_kembali jika ada, else sama dengan mulai
              $tgl_mulai    = !empty($row['tanggal_akses'])   ? date('d M Y', strtotime($row['tanggal_akses']))   : '—';
              $tgl_terakhir = !empty($row['tanggal_kembali']) ? date('d M Y', strtotime($row['tanggal_kembali'])) : $tgl_mulai;
            ?>
            <tr>
              <!-- ID -->
              <td>
                <span style="font-size:12px;color:var(--text3);font-weight:600">
                  <?php echo $id_pad; ?>
                </span>
              </td>

              <!-- Anggota -->
              <td>
                <div class="member-cell">
                  <div class="member-avatar"
                       style="background:<?php echo $av['color']; ?>">
                    <?php echo $av['initial']; ?>
                  </div>
                  <div>
                    <div class="member-name"><?php echo htmlspecialchars($row['nama_lengkap']); ?></div>
                    <div class="member-user">@<?php echo htmlspecialchars($row['username']); ?></div>
                  </div>
                </div>
              </td>

              <!-- eBook -->
              <td>
                <div class="book-cell">
                  <div class="book-cover">
                    <?php if (!empty($row['cover_img'])): ?>
                      <img src="../<?php echo htmlspecialchars($row['cover_img'], ENT_QUOTES); ?>"
                           alt="cover"
                           style="width:100%;height:100%;object-fit:cover;border-radius:4px;display:block;">
                    <?php else: ?>
                      <?php echo htmlspecialchars($row['cover_emoji'] ?? '📗', ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                  </div>
                  <div>
                    <div class="book-title"><?php echo htmlspecialchars($row['judul']); ?></div>
                    <div class="book-cat"><?php echo htmlspecialchars($row['kategori']); ?></div>
                  </div>
                </div>
              </td>

              <!-- Mulai Dibaca -->
              <td>
                <div class="date-main"><?php echo $tgl_mulai; ?></div>
              </td>

              <!-- Terakhir Dibaca -->
              <td>
                <div class="date-main"><?php echo $tgl_terakhir; ?></div>
              </td>

              <!-- Progress -->
              <td>
                <div class="progress-wrap">
                  <div class="progress-track">
                    <div class="progress-fill <?php echo $pc; ?>"
                         style="width:<?php echo $prog; ?>%"></div>
                  </div>
                  <div class="progress-label"><?php echo $prog; ?>%</div>
                </div>
              </td>

              <!-- Status -->
              <td><?php echo status_badge($row['status']); ?></td>

              <!-- Aksi -->
              <td>
                <div class="action-btns" style="justify-content:center">
                  <a href="riwayat_aktivitas.php?detail=<?php echo $row['id']; ?>&page=<?php echo $page; ?><?php echo $qs; ?>"
                     class="btn-icon" title="Detail">
                    &#128065; Detail
                  </a>
                  <a href="riwayat_aktivitas.php?hapus=<?php echo $row['id']; ?>&page=<?php echo $page; ?><?php echo $qs; ?>"
                     class="btn-icon del" title="Hapus Riwayat"
                     onclick="return confirm('Yakin ingin menghapus riwayat membaca ini?')">
                    &#128465;
                  </a>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>

            <?php if ($no_data): ?>
            <tr>
              <td colspan="8">
                <div class="empty-state">
                  <div class="empty-icon">&#128269;</div>
                  <div class="empty-title">
                    <?php echo $search ? "Tidak ada hasil untuk \"" . htmlspecialchars($search) . "\"" : 'Belum ada aktivitas membaca'; ?>
                  </div>
                  <div class="empty-desc">
                    <?php echo ($search || $filter_status || $filter_kat || $filter_date)
                      ? 'Coba ubah filter atau kata kunci pencarian.'
                      : 'Data akan muncul ketika anggota mulai membaca buku.'; ?>
                  </div>
                  <?php if ($search || $filter_status || $filter_kat || $filter_date): ?>
                  <a href="riwayat_aktivitas.php" class="empty-action">&#8635; Reset Filter</a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endif; ?>

          </tbody>
        </table>
      </div>
      <!-- END TABLE WRAP -->


      <!-- ── PAGINATION ──────────────────────────────────────────────────── -->
      <?php if ($total_rows > 0): ?>
      <div class="pagination-bar">
        <div class="page-info">
          Menampilkan
          <?php echo min($offset + 1, $total_rows); ?>–<?php echo min($offset + $per_page, $total_rows); ?>
          dari <?php echo number_format($total_rows); ?> data
        </div>

        <div class="page-btns">
          <!-- First & Prev -->
          <a href="?page=1<?php echo $qs; ?>"
             class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>" title="Pertama">&#171;</a>
          <a href="?page=<?php echo $page - 1; ?><?php echo $qs; ?>"
             class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>" title="Sebelumnya">&#8249;</a>

          <!-- Nomor halaman -->
          <?php
          $start_p = max(1, $page - 2);
          $end_p   = min($total_pages, $page + 2);
          for ($p = $start_p; $p <= $end_p; $p++):
          ?>
          <a href="?page=<?php echo $p; ?><?php echo $qs; ?>"
             class="page-btn <?php echo $p === $page ? 'active' : ''; ?>">
            <?php echo $p; ?>
          </a>
          <?php endfor; ?>

          <!-- Next & Last -->
          <a href="?page=<?php echo $page + 1; ?><?php echo $qs; ?>"
             class="page-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" title="Berikutnya">&#8250;</a>
          <a href="?page=<?php echo $total_pages; ?><?php echo $qs; ?>"
             class="page-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" title="Terakhir">&#187;</a>
        </div>
      </div>
      <?php endif; ?>

    </div>
    <!-- END TABLE CARD -->

  </div>
  <!-- END CONTENT AREA -->

</main>


<!-- ========== MODAL DETAIL ========== -->
<?php if ($detail_row): ?>
<?php
  $av_detail = avatar_info($detail_row['nama_lengkap']);
  $prog_det  = (int) ($detail_row['progress'] ?? 0);
  $pc_det    = progress_class($detail_row['status']);
?>
<div class="modal-overlay" id="detailModal" onclick="closeModalOverlay(event)">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">&#128214; Detail Aktivitas Membaca</div>
      <a href="riwayat_aktivitas.php?page=<?php echo $page; ?><?php echo $qs; ?>"
         class="modal-close" title="Tutup">&#10005;</a>
    </div>

    <div class="modal-body">

      <!-- Book hero -->
      <div class="modal-book-hero">
        <div class="modal-book-emoji">
          <?php if (!empty($detail_row['cover_img'])): ?>
            <img src="../<?php echo htmlspecialchars($detail_row['cover_img'], ENT_QUOTES); ?>"
                 alt="cover"
                 style="width:60px;height:80px;object-fit:cover;border-radius:6px;display:block;">
          <?php else: ?>
            <?php echo htmlspecialchars($detail_row['cover_emoji'] ?? '📗', ENT_QUOTES, 'UTF-8'); ?>
          <?php endif; ?>
        </div>
        <div>
          <div class="modal-book-name"><?php echo htmlspecialchars($detail_row['judul']); ?></div>
          <div class="modal-book-auth"><?php echo htmlspecialchars($detail_row['penulis']); ?></div>
          <?php echo status_badge($detail_row['status']); ?>
        </div>
      </div>

      <!-- Field grid -->
      <div class="modal-grid">
        <div class="modal-field">
          <div class="modal-field-label">ID Akses</div>
          <div class="modal-field-val">
            #<?php echo str_pad($detail_row['id'], 4, '0', STR_PAD_LEFT); ?>
          </div>
        </div>
        <div class="modal-field">
          <div class="modal-field-label">Kategori</div>
          <div class="modal-field-val"><?php echo htmlspecialchars($detail_row['kategori']); ?></div>
        </div>
        <div class="modal-field">
          <div class="modal-field-label">Nama Anggota</div>
          <div class="modal-field-val"><?php echo htmlspecialchars($detail_row['nama_lengkap']); ?></div>
        </div>
        <div class="modal-field">
          <div class="modal-field-label">Username</div>
          <div class="modal-field-val">@<?php echo htmlspecialchars($detail_row['username']); ?></div>
        </div>
        <div class="modal-field">
          <div class="modal-field-label">Mulai Membaca</div>
          <div class="modal-field-val">
            <?php echo !empty($detail_row['tanggal_akses'])
              ? date('d M Y, H:i', strtotime($detail_row['tanggal_akses']))
              : '—'; ?>
          </div>
        </div>
        <div class="modal-field">
          <div class="modal-field-label">Terakhir Dibaca</div>
          <div class="modal-field-val">
            <?php echo !empty($detail_row['tanggal_kembali'])
              ? date('d M Y, H:i', strtotime($detail_row['tanggal_kembali']))
              : '—'; ?>
          </div>
        </div>
        <div class="modal-field">
          <div class="modal-field-label">Total Waktu</div>
          <div class="modal-field-val">
            <?php echo format_durasi((int) ($detail_row['total_waktu_menit'] ?? 0)); ?>
          </div>
        </div>
        <div class="modal-field">
          <div class="modal-field-label">Status</div>
          <div class="modal-field-val">
            <?php echo status_badge($detail_row['status']); ?>
          </div>
        </div>
      </div>

      <!-- Progress -->
      <div class="modal-progress-section">
        <div class="modal-progress-label">Progress Membaca</div>
        <div class="modal-progress-row">
          <div class="modal-progress-track">
            <div class="modal-progress-fill <?php echo $pc_det; ?>"
                 style="width:<?php echo $prog_det; ?>%"></div>
          </div>
          <div class="modal-progress-pct"><?php echo $prog_det; ?>%</div>
        </div>
      </div>

    </div>

    <div class="modal-footer">
      <a href="riwayat_aktivitas.php?hapus=<?php echo $detail_row['id']; ?>&page=<?php echo $page; ?><?php echo $qs; ?>"
         class="btn btn-danger"
         onclick="return confirm('Yakin ingin menghapus riwayat membaca ini?')">
        &#128465; Hapus
      </a>
      <a href="riwayat_aktivitas.php?page=<?php echo $page; ?><?php echo $qs; ?>"
         class="btn btn-secondary">Tutup</a>
    </div>
  </div>
</div>
<?php endif; ?>


<!-- TOAST -->
<div class="toast" id="toast">
  <span class="toast-icon" id="toast-icon">&#10003;</span>
  <span id="toast-msg">Berhasil!</span>
</div>


<script>
/* ── Sidebar Toggle ─────────────────────────────────────────────────────────── */
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebar       = document.getElementById('sidebar');
if (sidebarToggle && sidebar) {
  sidebarToggle.addEventListener('click', () => {
    sidebar.classList.toggle('active');
    sidebarToggle.innerHTML = sidebar.classList.contains('active') ? '&#10005;' : '&#9776;';
  });
}

/* ── Dark Mode ──────────────────────────────────────────────────────────────── */
const darkBtn = document.getElementById('darkModeToggle');
if (darkBtn) {
  const applyDark = (dark) => {
    document.body.classList.toggle('dark-mode', dark);
    darkBtn.innerHTML = dark ? '&#9728;' : '&#9790;';
    darkBtn.title     = dark ? 'Light Mode' : 'Dark Mode';
  };

  darkBtn.addEventListener('click', () => {
    const isDark = !document.body.classList.contains('dark-mode');
    applyDark(isDark);
    localStorage.setItem('darkMode', isDark ? 'enabled' : 'disabled');
  });

  // Init dari localStorage
  applyDark(localStorage.getItem('darkMode') === 'enabled');
}

/* ── Topbar Search ──────────────────────────────────────────────────────────── */
const topSearch = document.getElementById('topbarSearch');
if (topSearch) {
  topSearch.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && this.value.trim()) {
      window.location.href = 'riwayat_aktivitas.php?search=' + encodeURIComponent(this.value.trim());
    }
  });
}

/* ── Modal: tutup saat klik overlay ────────────────────────────────────────── */
function closeModalOverlay(e) {
  if (e.target === document.getElementById('detailModal')) {
    window.location.href = 'riwayat_aktivitas.php?page=<?php echo $page; ?><?php echo addslashes($qs); ?>';
  }
}

/* ── Toast ──────────────────────────────────────────────────────────────────── */
let toastTimer;
function showToast(msg, icon = '✓') {
  document.getElementById('toast-msg').textContent  = msg;
  document.getElementById('toast-icon').textContent = icon;
  const t = document.getElementById('toast');
  t.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.classList.remove('show'), 3500);
}

<?php if ($toast_msg): ?>
showToast(
  <?php echo json_encode($toast_msg); ?>,
  <?php echo $toast_type === 'success' ? '"✓"' : '"✕"'; ?>
);
<?php endif; ?>

/* ── Progress bar animasi saat load ─────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.progress-fill').forEach(el => {
    const w = el.style.width;
    el.style.width = '0';
    requestAnimationFrame(() => {
      requestAnimationFrame(() => { el.style.width = w; });
    });
  });
});
</script>

</body>
</html>