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


// ─── Kategori list ────────────────────────────────────────────────────────────
$result_kat_list = mysqli_query($conn, "SELECT nama_kategori FROM kategori ORDER BY nama_kategori ASC");
$kat_list = [];
while ($r = mysqli_fetch_assoc($result_kat_list)) $kat_list[] = $r['nama_kategori'];

// ─── PROSES TAMBAH ────────────────────────────────────────────────────────────
$pesan = '';
$pesan_type = '';
$form_data = [];

// ─── FITUR BARU (FAREL): Bulk Input - import banyak buku sekaligus dari CSV ──
$bulkPesan   = '';
$bulkTipe    = '';
$bulkDetail  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'bulk_import') {

    if (empty($_FILES['csv_file']['name'])) {
        $bulkPesan = 'Silakan pilih file CSV terlebih dahulu.';
        $bulkTipe  = 'danger';
    } else {
        $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            $bulkPesan = 'File harus berformat .csv';
            $bulkTipe  = 'danger';
        } else {
            $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            if ($handle === false) {
                $bulkPesan = 'Gagal membaca file CSV.';
                $bulkTipe  = 'danger';
            } else {
                $pdf_temp_dir = '../uploads/bulk_pdf_temp/';
                $pdf_final_dir = '../uploads/pdf/';
                $cover_temp_dir = '../uploads/bulk_cover_temp/';
                $cover_final_dir = '../uploads/cover/';
                if (!is_dir($pdf_temp_dir)) mkdir($pdf_temp_dir, 0755, true);
                if (!is_dir($pdf_final_dir)) mkdir($pdf_final_dir, 0755, true);
                if (!is_dir($cover_temp_dir)) mkdir($cover_temp_dir, 0755, true);
                if (!is_dir($cover_final_dir)) mkdir($cover_final_dir, 0755, true);

                // ── FITUR BARU: Upload ZIP berisi PDF & gambar cover, otomatis di-extract ──
                $zipInfo = '';
                if (!empty($_FILES['zip_pdf']['name'])) {
                    $zipExt = strtolower(pathinfo($_FILES['zip_pdf']['name'], PATHINFO_EXTENSION));
                    if ($zipExt !== 'zip') {
                        $zipInfo = 'File yang diunggah untuk PDF harus berformat .zip — dilewati.';
                    } elseif (!class_exists('ZipArchive')) {
                        $zipInfo = 'Ekstensi PHP ZipArchive tidak aktif di server ini, ZIP tidak bisa diproses.';
                    } else {
                        $zip = new ZipArchive();
                        if ($zip->open($_FILES['zip_pdf']['tmp_name']) === true) {
                            $jmlPdfDiekstrak = 0;
                            $jmlCoverDiekstrak = 0;
                            $ekstensiGambar = ['jpg', 'jpeg', 'png', 'webp'];
                            for ($i = 0; $i < $zip->numFiles; $i++) {
                                $namaDalamZip = $zip->getNameIndex($i);
                                $namaBersih = basename($namaDalamZip);
                                if ($namaBersih === '' || substr($namaDalamZip, -1) === '/') continue;

                                $ekst = strtolower(pathinfo($namaBersih, PATHINFO_EXTENSION));
                                $isiFile = $zip->getFromIndex($i);
                                if ($isiFile === false) continue;

                                if ($ekst === 'pdf') {
                                    file_put_contents($pdf_temp_dir . $namaBersih, $isiFile);
                                    $jmlPdfDiekstrak++;
                                } elseif (in_array($ekst, $ekstensiGambar, true)) {
                                    file_put_contents($cover_temp_dir . $namaBersih, $isiFile);
                                    $jmlCoverDiekstrak++;
                                }
                                // Selain PDF/gambar, dilewati begitu saja
                            }
                            $zip->close();
                            $zipInfo = "$jmlPdfDiekstrak file PDF dan $jmlCoverDiekstrak gambar cover dari ZIP berhasil diekstrak.";
                        } else {
                            $zipInfo = 'Gagal membuka file ZIP — pastikan file tidak rusak.';
                        }
                    }
                }

                $header = fgetcsv($handle); // baris pertama = nama kolom, dilewati
                $baris_ke = 1;
                $sukses = 0;
                $gagal  = 0;
                $tanpaPdf = 0;

                while (($row = fgetcsv($handle)) !== false) {
                    $baris_ke++;
                    // Lewati baris kosong
                    if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) continue;

                    $judul_b     = trim($row[0] ?? '');
                    $penulis_b   = trim($row[1] ?? '');
                    $penerbit_b  = trim($row[2] ?? '');
                    $tahun_b     = (int) ($row[3] ?? date('Y'));
                    $kategori_b  = trim($row[4] ?? '');
                    $deskripsi_b = trim($row[5] ?? '');
                    $pdf_nama_b  = trim($row[6] ?? ''); // kolom opsional nama file PDF
                    $cover_nama_b = trim($row[7] ?? ''); // FITUR BARU: kolom opsional nama file cover

                    if ($judul_b === '' || $penulis_b === '' || $kategori_b === '') {
                        $gagal++;
                        $bulkDetail[] = "Baris $baris_ke: dilewati — Judul, Penulis, dan Kategori wajib diisi.";
                        continue;
                    }
                    if (!in_array($kategori_b, $kat_list, true)) {
                        $gagal++;
                        $bulkDetail[] = "Baris $baris_ke: dilewati — kategori \"$kategori_b\" tidak ditemukan di daftar kategori.";
                        continue;
                    }

                    // ── FITUR BARU: Cari & pasangkan file PDF dari folder sementara ──
                    $file_pdf_final = '';
                    if ($pdf_nama_b !== '') {
                        $sumber_pdf = $pdf_temp_dir . $pdf_nama_b;
                        if (is_file($sumber_pdf) && strtolower(pathinfo($pdf_nama_b, PATHINFO_EXTENSION)) === 'pdf') {
                            $nama_baru = 'buku_' . time() . '_' . uniqid() . '.pdf';
                            if (copy($sumber_pdf, $pdf_final_dir . $nama_baru)) {
                                $file_pdf_final = 'uploads/pdf/' . $nama_baru;
                            } else {
                                $bulkDetail[] = "Baris $baris_ke: PDF \"$pdf_nama_b\" gagal disalin, buku tetap disimpan tanpa PDF.";
                                $tanpaPdf++;
                            }
                        } else {
                            $bulkDetail[] = "Baris $baris_ke: PDF \"$pdf_nama_b\" tidak ditemukan di folder uploads/bulk_pdf_temp/, buku disimpan tanpa PDF.";
                            $tanpaPdf++;
                        }
                    } else {
                        $tanpaPdf++;
                    }

                    // ── FITUR BARU: Cari & pasangkan gambar cover dari folder sementara ──
                    $cover_img_final = '';
                    if ($cover_nama_b !== '') {
                        $sumber_cover = $cover_temp_dir . $cover_nama_b;
                        $ekstCover = strtolower(pathinfo($cover_nama_b, PATHINFO_EXTENSION));
                        if (is_file($sumber_cover) && in_array($ekstCover, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                            $nama_cover_baru = 'cover_' . time() . '_' . uniqid() . '.' . $ekstCover;
                            if (copy($sumber_cover, $cover_final_dir . $nama_cover_baru)) {
                                $cover_img_final = 'uploads/cover/' . $nama_cover_baru;
                            } else {
                                $bulkDetail[] = "Baris $baris_ke: cover \"$cover_nama_b\" gagal disalin, memakai ikon default.";
                            }
                        } else {
                            $bulkDetail[] = "Baris $baris_ke: cover \"$cover_nama_b\" tidak ditemukan, memakai ikon default.";
                        }
                    }

                    $judul_e       = mysqli_real_escape_string($conn, $judul_b);
                    $penulis_e     = mysqli_real_escape_string($conn, $penulis_b);
                    $penerbit_e    = mysqli_real_escape_string($conn, $penerbit_b);
                    $kategori_e    = mysqli_real_escape_string($conn, $kategori_b);
                    $deskripsi_e   = mysqli_real_escape_string($conn, $deskripsi_b);
                    $file_pdf_e    = mysqli_real_escape_string($conn, $file_pdf_final);
                    $cover_img_e   = mysqli_real_escape_string($conn, $cover_img_final);
                    if ($tahun_b <= 0) $tahun_b = (int) date('Y');

                    $ok = mysqli_query($conn, "INSERT INTO buku
                        (judul, penulis, penerbit, tahun, kategori, deskripsi, cover_emoji, cover_img, file_pdf, total_baca, created_at)
                        VALUES
                        ('$judul_e','$penulis_e','$penerbit_e',$tahun_b,'$kategori_e','$deskripsi_e','📚','$cover_img_e','$file_pdf_e',0,NOW())");

                    if ($ok) {
                        $sukses++;
                    } else {
                        $gagal++;
                        $bulkDetail[] = "Baris $baris_ke: gagal disimpan ke database.";
                    }
                }
                fclose($handle);

                if ($sukses > 0) {
                    $bulkPesan = "$sukses buku berhasil diimpor";
                    $extra = [];
                    if ($gagal > 0) $extra[] = "$gagal baris dilewati";
                    if ($tanpaPdf > 0) $extra[] = "$tanpaPdf buku tersimpan tanpa file PDF";
                    if ($extra) $bulkPesan .= ' (' . implode(', ', $extra) . ').';
                    else $bulkPesan .= '.';
                    if ($zipInfo) $bulkPesan .= ' ' . $zipInfo;
                    $bulkTipe  = ($gagal > 0 || $tanpaPdf > 0) ? 'warning' : 'success';
                } else {
                    $bulkPesan = 'Tidak ada buku yang berhasil diimpor. Periksa kembali format file CSV kamu.';
                    if ($zipInfo) $bulkPesan .= ' ' . $zipInfo;
                    $bulkTipe  = 'danger';
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? 'single') === 'single') {
    $judul       = mysqli_real_escape_string($conn, trim($_POST['judul'] ?? ''));
    $penulis     = mysqli_real_escape_string($conn, trim($_POST['penulis'] ?? ''));
    $penerbit    = mysqli_real_escape_string($conn, trim($_POST['penerbit'] ?? ''));
    $tahun       = (int)($_POST['tahun'] ?? date('Y'));
    $kategori    = mysqli_real_escape_string($conn, trim($_POST['kategori'] ?? ''));
    $deskripsi   = mysqli_real_escape_string($conn, trim($_POST['deskripsi'] ?? ''));
    $form_data = $_POST;

    if (empty($judul) || empty($penulis) || empty($kategori)) {
        $pesan = 'Judul, Penulis, dan Kategori wajib diisi!';
        $pesan_type = 'danger';
    } else {
        // Handle upload cover image (WAJIB)
        $cover_img = '';
        if (empty($_FILES['cover_img']['name'])) {
            $pesan = 'Cover buku wajib diupload!';
            $pesan_type = 'danger';
        } else {
            $allowed_ext = ['jpg','jpeg','png','webp'];
            $ext = strtolower(pathinfo($_FILES['cover_img']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_ext)) {
                $pesan = 'Format gambar tidak didukung. Gunakan JPG, PNG, atau WEBP.';
                $pesan_type = 'danger';
            } elseif ($_FILES['cover_img']['size'] > 2 * 1024 * 1024) {
                $pesan = 'Ukuran gambar maksimal 2MB.';
                $pesan_type = 'danger';
            } else {
                $upload_dir = '../uploads/cover/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $filename = 'cover_' . time() . '_' . uniqid() . '.' . $ext;
                move_uploaded_file($_FILES['cover_img']['tmp_name'], $upload_dir . $filename);
                $cover_img = 'uploads/cover/' . $filename;
            }
        }

        // Handle upload file PDF (opsional)
        $file_pdf = '';
        if (!empty($_FILES['file_pdf']['name'])) {
            $ext_pdf = strtolower(pathinfo($_FILES['file_pdf']['name'], PATHINFO_EXTENSION));
            if ($ext_pdf !== 'pdf') {
                $pesan = 'File buku harus berformat PDF.';
                $pesan_type = 'danger';
            } elseif ($_FILES['file_pdf']['size'] > 50 * 1024 * 1024) {
                $pesan = 'Ukuran PDF maksimal 50MB.';
                $pesan_type = 'danger';
            } else {
                $upload_pdf_dir = '../uploads/pdf/';
                if (!is_dir($upload_pdf_dir)) mkdir($upload_pdf_dir, 0755, true);
                $pdf_name = 'buku_' . time() . '_' . uniqid() . '.pdf';
                move_uploaded_file($_FILES['file_pdf']['tmp_name'], $upload_pdf_dir . $pdf_name);
                $file_pdf = 'uploads/pdf/' . $pdf_name;
            }
        }

        if (empty($pesan)) {
            $insert = mysqli_query($conn, "INSERT INTO buku
                (judul, penulis, penerbit, tahun, kategori, deskripsi, cover_img, file_pdf, total_baca, created_at)
                VALUES
                ('$judul','$penulis','$penerbit',$tahun,'$kategori','$deskripsi',
                 '$cover_img','$file_pdf',0,NOW())");

            if ($insert) {
                $pesan = 'Buku <strong>' . htmlspecialchars($judul) . '</strong> berhasil ditambahkan!';
                $pesan_type = 'success';
                $form_data = []; // reset form
            } else {
                $pesan = 'Gagal menyimpan buku. Silakan coba lagi.';
                $pesan_type = 'danger';
            }
        }
    }
}

$active_menu = 'tambah_buku';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tambah Buku — Pojok Baca</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/admin/dashboard.css">
  <link rel="stylesheet" href="../assets/css/admin/tambah_buku.css">
  <link rel="stylesheet" href="../assets/css/admin/sidebar.css">
</head>
<body>

<?php include '../includes/admin/sidebar.php'; ?>

<main class="main-content" id="mainContent">

  <!-- TOPBAR -->
  <?php
    $topbar_title      = 'Tambah Buku';
    $topbar_breadcrumb = 'Tambah Buku';
    $topbar_search     = false;
    include '../includes/admin/topbar.php';
  ?>

  <div class="content-area">

    <!-- PAGE HEADER -->
    <div class="page-header-bar">
      <div class="page-header-left">
        <h2 class="page-heading">➕ Tambah Buku Baru</h2>
        <p class="page-subheading">Lengkapi informasi buku yang ingin ditambahkan ke koleksi perpustakaan.</p>
      </div>
      <div class="page-header-right">
        <a href="kelola_buku.php" class="btn-ghost">← Kembali ke Daftar</a>
      </div>
    </div>

    <!-- FITUR BARU (FAREL): Tab Mode Input -->
    <div class="mode-tabs" style="display:flex;gap:10px;margin-bottom:20px;">
      <button type="button" class="mode-tab-btn active" data-mode="single"
        style="padding:10px 18px;border-radius:10px;border:1px solid var(--border, #2a2f3d);
               background:var(--accent, #5a9cf0);color:#fff;font-weight:600;font-size:13.5px;cursor:pointer;">
        ➕ Satu Buku
      </button>
      <button type="button" class="mode-tab-btn" data-mode="bulk"
        style="padding:10px 18px;border-radius:10px;border:1px solid var(--border, #2a2f3d);
               background:transparent;color:var(--text2, #9aa4b8);font-weight:600;font-size:13.5px;cursor:pointer;">
        📑 Bulk Input (CSV)
      </button>
    </div>

    <!-- ALERT (single) -->
    <?php if ($pesan): ?>
    <div class="alert alert-<?php echo $pesan_type; ?>" id="alertMsg">
      <?php echo $pesan_type === 'success' ? '✅' : '❌'; ?>
      <?php echo $pesan; ?>
    </div>
    <?php endif; ?>

    <!-- FITUR BARU (FAREL): Alert Bulk Import -->
    <?php if ($bulkPesan): ?>
    <div class="alert alert-<?php echo $bulkTipe; ?>" id="alertBulkMsg">
      <?php echo $bulkTipe === 'success' ? '✅' : ($bulkTipe === 'warning' ? '⚠️' : '❌'); ?>
      <?php echo htmlspecialchars($bulkPesan); ?>
      <?php if (!empty($bulkDetail)): ?>
        <ul style="margin:10px 0 0 20px;font-size:12.5px;">
          <?php foreach (array_slice($bulkDetail, 0, 15) as $d): ?>
            <li><?php echo htmlspecialchars($d); ?></li>
          <?php endforeach; ?>
          <?php if (count($bulkDetail) > 15): ?>
            <li>...dan <?php echo count($bulkDetail) - 15; ?> baris lainnya.</li>
          <?php endif; ?>
        </ul>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- FITUR BARU (FAREL): Panel Bulk Input CSV -->
    <div class="mode-panel" id="panelBulk" style="display:none;">
      <div class="card form-card">
        <div class="card-title">📑 Bulk Input — Import Banyak Buku Sekaligus</div>
        <p class="form-hint" style="margin-bottom:16px;">
          Unggah file CSV berisi daftar buku. Format kolom: <strong>judul, penulis, penerbit, tahun, kategori, deskripsi, file_pdf, cover_img</strong>.
        </p>

        <a href="#" id="downloadTemplate" class="btn-ghost" style="display:inline-flex;margin-bottom:20px;">
          ⬇️ Contoh CSV / Template
        </a>

        <form method="POST" enctype="multipart/form-data" id="formBulkImport">
          <input type="hidden" name="aksi" value="bulk_import">
          <div class="form-group">
            <label for="csv_file">File CSV <span class="req">*</span></label>
            <div class="file-upload-area" id="csvUploadArea">
              <input type="file" id="csv_file" name="csv_file" accept=".csv" class="file-input-hidden" required>
              <div class="file-upload-content" id="csvUploadContent">
                <span class="file-upload-icon">📁</span>
                <span class="file-upload-text">Klik atau seret file CSV ke sini</span>
                <span class="file-upload-hint">Format .csv · Baris pertama = nama kolom</span>
              </div>
            </div>
          </div>

          <div class="form-group" style="margin-top:16px;">
            <label for="zip_pdf">File ZIP Berisi PDF <span style="color:var(--text3, #6b7280);font-weight:400;">(opsional)</span></label>
            <div class="file-upload-area" id="zipUploadArea">
              <input type="file" id="zip_pdf" name="zip_pdf" accept=".zip" class="file-input-hidden">
              <div class="file-upload-content" id="zipUploadContent">
                <span class="file-upload-icon">🗜️</span>
                <span class="file-upload-text">Klik atau seret file ZIP ke sini</span>
                <span class="file-upload-hint">Berisi kumpulan file PDF, nama file harus cocok dengan kolom file_pdf di CSV</span>
              </div>
            </div>
          </div>

          <button type="submit" class="btn-primary btn-full" id="btnBulkSubmit" style="margin-top:14px;">
            📥 Import Buku dari CSV
          </button>
        </form>
      </div>
    </div>

    <div class="mode-panel" id="panelSingle">

    <form method="POST" enctype="multipart/form-data" class="form-layout" id="formTambahBuku" novalidate>
      <input type="hidden" name="aksi" value="single">

      <!-- KOLOM KIRI: Form utama -->
      <div class="form-main">

        <!-- CARD: Informasi Utama -->
        <div class="card form-card">
          <div class="card-title">📋 Informasi Utama</div>

          <div class="form-row">
            <div class="form-group flex-2">
              <label for="judul">Judul Buku <span class="req">*</span></label>
              <input type="text" id="judul" name="judul"
                     placeholder="Masukkan judul buku lengkap"
                     value="<?php echo htmlspecialchars($form_data['judul'] ?? ''); ?>"
                     required>
              <span class="form-hint">Tulis judul sesuai dengan sampul buku.</span>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="penulis">Penulis <span class="req">*</span></label>
              <input type="text" id="penulis" name="penulis"
                     placeholder="Nama penulis atau editor"
                     value="<?php echo htmlspecialchars($form_data['penulis'] ?? ''); ?>"
                     required>
            </div>
            <div class="form-group">
              <label for="penerbit">Penerbit</label>
              <input type="text" id="penerbit" name="penerbit"
                     placeholder="Nama penerbit"
                     value="<?php echo htmlspecialchars($form_data['penerbit'] ?? ''); ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="kategori">Kategori <span class="req">*</span></label>
              <select id="kategori" name="kategori" required>
                <option value="">— Pilih Kategori —</option>
                <?php foreach ($kat_list as $k): ?>
                <option value="<?php echo htmlspecialchars($k); ?>"
                  <?php echo (($form_data['kategori'] ?? '') === $k) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($k); ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="tahun">Tahun Terbit</label>
              <input type="number" id="tahun" name="tahun"
                     min="1900" max="<?php echo date('Y'); ?>"
                     placeholder="<?php echo date('Y'); ?>"
                     value="<?php echo htmlspecialchars($form_data['tahun'] ?? date('Y')); ?>">
            </div>
          </div>

          <div class="form-group">
            <label for="deskripsi">Deskripsi / Sinopsis</label>
            <textarea id="deskripsi" name="deskripsi" rows="5"
                      placeholder="Tulis sinopsis atau deskripsi singkat buku ini..."><?php echo htmlspecialchars($form_data['deskripsi'] ?? ''); ?></textarea>
            <span class="form-hint char-count-wrap">
              <span id="charCount">0</span> karakter
            </span>
          </div>
        </div>
        <!-- END CARD Informasi Utama -->

      </div>
      <!-- END KOLOM KIRI -->


      <!-- KOLOM KANAN: Upload & Preview -->
      <div class="form-sidebar">

        <!-- CARD: Preview Cover -->
        <div class="card form-card">
          <div class="card-title">🖼️ Cover Buku</div>

          <div class="cover-preview-wrap">
            <div class="cover-preview" id="coverPreview">
              <img src="" alt="Preview Cover" id="coverImg" style="display:none">
              <span id="coverPlaceholder" style="font-size:13px;color:#6b7280;">Belum ada gambar</span>
            </div>
            <p class="cover-preview-label" id="coverPreviewLabel">Preview Cover</p>
          </div>

          <div class="form-group">
            <label for="cover_img">Upload Gambar Cover <span class="req">*</span></label>
            <div class="file-upload-area" id="coverUploadArea">
              <input type="file" id="cover_img" name="cover_img"
                     accept="image/jpg,image/jpeg,image/png,image/webp"
                     class="file-input-hidden" required>
              <div class="file-upload-content" id="coverUploadContent">
                <span class="file-upload-icon">📁</span>
                <span class="file-upload-text">Klik atau seret gambar ke sini</span>
                <span class="file-upload-hint">JPG, PNG, WEBP · Maks 2MB</span>
              </div>
            </div>
          </div>
        </div>

        <!-- CARD: Upload PDF -->
        <div class="card form-card">
          <div class="card-title">📄 File PDF Buku</div>

          <div class="form-group">
            <label for="file_pdf">Upload File PDF</label>
            <div class="file-upload-area" id="pdfUploadArea">
              <input type="file" id="file_pdf" name="file_pdf"
                     accept="application/pdf"
                     class="file-input-hidden">
              <div class="file-upload-content" id="pdfUploadContent">
                <span class="file-upload-icon">📄</span>
                <span class="file-upload-text">Klik atau seret PDF ke sini</span>
                <span class="file-upload-hint">Format PDF · Maks 50MB</span>
              </div>
            </div>
            <span class="form-hint">File PDF bisa diakses anggota untuk membaca online (opsional).</span>
          </div>
        </div>

        <!-- CARD: Tombol Aksi -->
        <div class="card form-card action-card">
          <button type="submit" class="btn-primary btn-full" id="btnSimpan">
            💾 Simpan Buku
          </button>
          <button type="reset" class="btn-ghost btn-full" id="btnReset">
            ↺ Reset Form
          </button>
          <a href="kelola_buku.php" class="btn-ghost btn-full btn-center">
            ← Batal & Kembali
          </a>
        </div>

      </div>
      <!-- END KOLOM KANAN -->

    </form>
    </div>
    <!-- END panelSingle -->

  </div>
  <!-- END CONTENT AREA -->

</main>

<!-- TOAST -->
<div class="toast" id="toast">
  <span class="toast-icon" id="toast-icon">✓</span>
  <span id="toast-msg">Berhasil!</span>
</div>

<script>
// ─── FITUR BARU (FAREL): Toggle Tab Mode Single / Bulk ───────────────────────
const tabBtns     = document.querySelectorAll('.mode-tab-btn');
const panelSingle = document.getElementById('panelSingle');
const panelBulk   = document.getElementById('panelBulk');

function setMode(mode) {
  tabBtns.forEach(b => {
    const active = b.dataset.mode === mode;
    b.classList.toggle('active', active);
    b.style.background = active ? 'var(--accent, #5a9cf0)' : 'transparent';
    b.style.color = active ? '#fff' : 'var(--text2, #9aa4b8)';
  });
  panelSingle.style.display = mode === 'single' ? '' : 'none';
  panelBulk.style.display   = mode === 'bulk' ? '' : 'none';
}

tabBtns.forEach(btn => {
  btn.addEventListener('click', () => setMode(btn.dataset.mode));
});

<?php if ($bulkPesan): ?>
setMode('bulk'); // Buka tab Bulk otomatis kalau baru saja submit CSV
<?php else: ?>
setMode('single');
<?php endif; ?>

// ─── FITUR BARU (FAREL): Upload CSV & Preview Nama File ──────────────────────
const csvInput   = document.getElementById('csv_file');
const csvArea    = document.getElementById('csvUploadArea');
const csvContent = document.getElementById('csvUploadContent');

if (csvArea && csvInput) {
  csvArea.addEventListener('click', () => csvInput.click());
  csvArea.addEventListener('dragover', e => { e.preventDefault(); csvArea.classList.add('drag-over'); });
  csvArea.addEventListener('dragleave', () => csvArea.classList.remove('drag-over'));
  csvArea.addEventListener('drop', e => {
    e.preventDefault();
    csvArea.classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file) { csvInput.files = e.dataTransfer.files; showCsvName(file); }
  });
  csvInput.addEventListener('change', () => {
    if (csvInput.files[0]) showCsvName(csvInput.files[0]);
  });
}

function showCsvName(file) {
  csvContent.innerHTML = `<span class="file-upload-icon">✅</span>
    <span class="file-upload-text">${file.name}</span>
    <span class="file-upload-hint">${(file.size/1024).toFixed(1)} KB</span>`;
}

// ─── FITUR BARU (FAREL): Upload ZIP PDF & Preview Nama File ──────────────────
const zipInput   = document.getElementById('zip_pdf');
const zipArea    = document.getElementById('zipUploadArea');
const zipContent = document.getElementById('zipUploadContent');

if (zipArea && zipInput) {
  zipArea.addEventListener('click', () => zipInput.click());
  zipArea.addEventListener('dragover', e => { e.preventDefault(); zipArea.classList.add('drag-over'); });
  zipArea.addEventListener('dragleave', () => zipArea.classList.remove('drag-over'));
  zipArea.addEventListener('drop', e => {
    e.preventDefault();
    zipArea.classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file) { zipInput.files = e.dataTransfer.files; showZipName(file); }
  });
  zipInput.addEventListener('change', () => {
    if (zipInput.files[0]) showZipName(zipInput.files[0]);
  });
}

function showZipName(file) {
  zipContent.innerHTML = `<span class="file-upload-icon">✅</span>
    <span class="file-upload-text">${file.name}</span>
    <span class="file-upload-hint">${(file.size/1024/1024).toFixed(2)} MB</span>`;
}

const bulkForm = document.getElementById('formBulkImport');
if (bulkForm) {
  bulkForm.addEventListener('submit', function () {
    const btn = document.getElementById('btnBulkSubmit');
    btn.disabled = true;
    btn.textContent = '⏳ Mengimpor...';
  });
}

// ─── FITUR BARU (FAREL): Download Template CSV ───────────────────────────────
document.getElementById('downloadTemplate').addEventListener('click', function (e) {
  e.preventDefault();
  const rows = [
    ['judul', 'penulis', 'penerbit', 'tahun', 'kategori', 'deskripsi', 'file_pdf', 'cover_img'],
    ['Contoh Judul Buku', 'Nama Penulis', 'Nama Penerbit', '2024', 'Novel', 'Sinopsis singkat buku ini...', 'contoh-judul-buku.pdf', 'contoh-judul-buku.jpg'],
  ];
  const csvContentStr = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\r\n');
  const blob = new Blob(['\ufeff' + csvContentStr], { type: 'text/csv;charset=utf-8;' });
  const url  = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'template_bulk_buku.csv';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
});

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

// ─── Cover Image Upload & Preview ────────────────────────────────────────────
const coverInput    = document.getElementById('cover_img');
const coverImg      = document.getElementById('coverImg');
const coverArea     = document.getElementById('coverUploadArea');
const coverContent  = document.getElementById('coverUploadContent');
const coverLabel    = document.getElementById('coverPreviewLabel');
const coverPlaceholder = document.getElementById('coverPlaceholder');

coverArea.addEventListener('click', () => coverInput.click());

coverArea.addEventListener('dragover', e => { e.preventDefault(); coverArea.classList.add('drag-over'); });
coverArea.addEventListener('dragleave', ()  => coverArea.classList.remove('drag-over'));
coverArea.addEventListener('drop', e => {
  e.preventDefault();
  coverArea.classList.remove('drag-over');
  const file = e.dataTransfer.files[0];
  if (file) previewCover(file);
});

coverInput.addEventListener('change', () => {
  if (coverInput.files[0]) previewCover(coverInput.files[0]);
});

function previewCover(file) {
  const reader = new FileReader();
  reader.onload = e => {
    coverImg.src = e.target.result;
    coverImg.style.display = 'block';
    if (coverPlaceholder) coverPlaceholder.style.display = 'none';
    coverLabel.textContent = file.name;
    coverContent.innerHTML = `<span class="file-upload-icon">✅</span>
      <span class="file-upload-text">${file.name}</span>
      <span class="file-upload-hint">${(file.size/1024/1024).toFixed(2)} MB</span>`;
  };
  reader.readAsDataURL(file);
}

// ─── PDF Upload ───────────────────────────────────────────────────────────────
const pdfInput   = document.getElementById('file_pdf');
const pdfArea    = document.getElementById('pdfUploadArea');
const pdfContent = document.getElementById('pdfUploadContent');

pdfArea.addEventListener('click', () => pdfInput.click());

pdfArea.addEventListener('dragover', e => { e.preventDefault(); pdfArea.classList.add('drag-over'); });
pdfArea.addEventListener('dragleave', ()  => pdfArea.classList.remove('drag-over'));
pdfArea.addEventListener('drop', e => {
  e.preventDefault();
  pdfArea.classList.remove('drag-over');
  const file = e.dataTransfer.files[0];
  if (file) updatePdfDisplay(file);
});

pdfInput.addEventListener('change', () => {
  if (pdfInput.files[0]) updatePdfDisplay(pdfInput.files[0]);
});

function updatePdfDisplay(file) {
  pdfContent.innerHTML = `<span class="file-upload-icon">✅</span>
    <span class="file-upload-text">${file.name}</span>
    <span class="file-upload-hint">${(file.size/1024/1024).toFixed(2)} MB</span>`;
}

// ─── Char Counter ─────────────────────────────────────────────────────────────
const deskripsi  = document.getElementById('deskripsi');
const charCount  = document.getElementById('charCount');
deskripsi.addEventListener('input', () => {
  charCount.textContent = deskripsi.value.length;
});
charCount.textContent = deskripsi.value.length;

// ─── Reset Form ───────────────────────────────────────────────────────────────
document.getElementById('btnReset').addEventListener('click', () => {
  coverImg.style.display    = 'none';
  coverImg.src              = '';
  if (coverPlaceholder) coverPlaceholder.style.display = '';
  coverLabel.textContent    = 'Preview Cover';
  coverContent.innerHTML    = `<span class="file-upload-icon">📁</span>
    <span class="file-upload-text">Klik atau seret gambar ke sini</span>
    <span class="file-upload-hint">JPG, PNG, WEBP · Maks 2MB</span>`;
  pdfContent.innerHTML      = `<span class="file-upload-icon">📄</span>
    <span class="file-upload-text">Klik atau seret PDF ke sini</span>
    <span class="file-upload-hint">Format PDF · Maks 50MB</span>`;
  charCount.textContent     = '0';
});

// ─── Toast ────────────────────────────────────────────────────────────────────
let toastTimer;
function showToast(msg, icon = '✓') {
  document.getElementById('toast-msg').textContent  = msg;
  document.getElementById('toast-icon').textContent = icon;
  const t = document.getElementById('toast');
  t.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.classList.remove('show'), 3500);
}

<?php if ($pesan): ?>
showToast(<?php echo json_encode(strip_tags($pesan)); ?>, '<?php echo $pesan_type === "success" ? "✅" : "❌"; ?>');
<?php endif; ?>

// Auto-hide alert
const alertEl = document.getElementById('alertMsg');
if (alertEl) setTimeout(() => alertEl.style.opacity = '0', 4000);

// ─── Client-side Validation ───────────────────────────────────────────────────
document.getElementById('formTambahBuku').addEventListener('submit', function(e) {
  const judul    = document.getElementById('judul').value.trim();
  const penulis  = document.getElementById('penulis').value.trim();
  const kategori = document.getElementById('kategori').value;
  const cover    = document.getElementById('cover_img').files.length;

  if (!judul || !penulis || !kategori || !cover) {
    e.preventDefault();
    if (!cover) {
      showToast('Cover buku wajib diupload!', '⚠️');
    } else {
      showToast('Judul, Penulis, dan Kategori wajib diisi!', '⚠️');
    }

    if (!judul)    document.getElementById('judul').classList.add('input-error');
    if (!penulis)  document.getElementById('penulis').classList.add('input-error');
    if (!kategori) document.getElementById('kategori').classList.add('input-error');
    if (!cover)    document.getElementById('coverUploadArea').classList.add('input-error');
    return;
  }

  // Loading state pada tombol
  const btn = document.getElementById('btnSimpan');
  btn.disabled     = true;
  btn.textContent  = '⏳ Menyimpan...';
});

// Hapus error state saat user mulai mengetik
['judul','penulis','kategori'].forEach(id => {
  document.getElementById(id).addEventListener('input', function() {
    this.classList.remove('input-error');
  });
});
</script>

</body>
</html>