<?php
session_start();
require_once '../config/database.php';
require_once '../includes/anggota/notif_helper.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] === 'guest') {
    header('Location: ../index.php');
    exit();
}

if (!is_numeric($_SESSION['user_id'] ?? '')) {
    header('Location: ../index.php');
    exit();
}

$dbOk = isset($conn) && $conn instanceof mysqli;
$uid  = (int) $_SESSION['user_id'];

// ── Data user ─────────────────────────────────────────────────────────────────
$user = null;
if ($dbOk) {
    $q    = mysqli_query($conn, "SELECT * FROM users WHERE id = $uid LIMIT 1");
    $user = $q ? mysqli_fetch_assoc($q) : null;
}
if (empty($user)) {
    $user = [
        'id'           => $uid,
        'nama_lengkap' => $_SESSION['nama_lengkap'] ?? 'Pengguna',
        'username'     => $_SESSION['username']     ?? 'user',
        'email'        => $_SESSION['email']        ?? 'user@email.com',
        'role'         => $_SESSION['role']         ?? 'anggota',
        'no_telepon'   => '',
        'alamat'       => '',
        'tgl_daftar'   => date('Y-m-d'),
        'foto'         => '',
    ];
}

// ── Statistik anggota ─────────────────────────────────────────────────────────
$statKoleksi = $statSelesai = $statWishlist = $statRiwayat = 0;
if ($dbOk) {
    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM koleksi WHERE id_anggota = $uid");
    if ($r) $statKoleksi = (int) mysqli_fetch_assoc($r)['c'];

    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM koleksi WHERE id_anggota = $uid AND status_baca = 'selesai'");
    if ($r) $statSelesai = (int) mysqli_fetch_assoc($r)['c'];

    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM wishlist WHERE id_anggota = $uid");
    if ($r) $statWishlist = (int) mysqli_fetch_assoc($r)['c'];

    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM riwayat_baca WHERE id_anggota = $uid");
    if ($r) $statRiwayat = (int) mysqli_fetch_assoc($r)['c'];
}

// Fallback angka dummy
if ($statKoleksi + $statSelesai + $statWishlist + $statRiwayat === 0) {
    $statKoleksi = 6; $statSelesai = 2; $statWishlist = 4; $statRiwayat = 14;
}

// ── Handle update profil ──────────────────────────────────────────────────────
$successMsg = '';
$errorMsg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // ──────────────────────────────────────────────────────────────────────────
    // ACTION: update_profil (nama, no HP, alamat)
    // ──────────────────────────────────────────────────────────────────────────
    if ($_POST['action'] === 'update_profil') {
        $namaLengkap = trim($_POST['nama_lengkap'] ?? '');
        $noTelepon   = trim($_POST['no_telepon']   ?? '');
        $alamat      = trim($_POST['alamat']        ?? '');

        if (empty($namaLengkap)) {
            $errorMsg = 'Nama lengkap tidak boleh kosong.';
        } elseif ($dbOk) {
            $namaLengkap = mysqli_real_escape_string($conn, $namaLengkap);
            $noTelepon   = mysqli_real_escape_string($conn, $noTelepon);
            $alamat      = mysqli_real_escape_string($conn, $alamat);

            $ok = mysqli_query($conn,
                "UPDATE users SET nama_lengkap='$namaLengkap', no_telepon='$noTelepon', alamat='$alamat'
                 WHERE id = $uid"
            );
            if ($ok) {
                $_SESSION['nama_lengkap'] = $namaLengkap;
                $user['nama_lengkap']     = $namaLengkap;
                $user['no_telepon']       = $noTelepon;
                $user['alamat']           = $alamat;
                $successMsg = 'Profil berhasil diperbarui.';

                // ── Kirim notifikasi ke DB ─────────────────────────────────
                // Deteksi field mana yang berubah untuk pesan yang informatif
                $perubahanList = [];
                // (Kita bandingkan dengan nilai lama yang sudah di-fetch sebelum UPDATE)
                // Karena sudah di-overwrite di $user, kita buat pesan umum:
                if (!empty($noTelepon)) {
                    $perubahanList[] = 'nomor HP';
                }
                if (!empty($alamat)) {
                    $perubahanList[] = 'alamat';
                }
                $perubahanStr = !empty($perubahanList)
                    ? 'nama, ' . implode(', dan ', $perubahanList)
                    : 'nama';

                kirimNotif(
                    $conn,
                    $uid,
                    'success',
                    'Profil Diperbarui',
                    "Informasi profil kamu berhasil diperbarui ({$perubahanStr})."
                );
                // ─────────────────────────────────────────────────────────
            } else {
                $errorMsg = 'Gagal memperbarui profil. Silakan coba lagi.';
            }
        } else {
            $successMsg = 'Profil berhasil diperbarui. (mode demo)';
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ACTION: ganti_password
    // ──────────────────────────────────────────────────────────────────────────
    if ($_POST['action'] === 'ganti_password') {
        $passLama     = $_POST['password_lama']       ?? '';
        $passBaru     = $_POST['password_baru']        ?? '';
        $passKonfirm  = $_POST['password_konfirmasi'] ?? '';

        if (empty($passLama) || empty($passBaru) || empty($passKonfirm)) {
            $errorMsg = 'Semua kolom password harus diisi.';
        } elseif ($passBaru !== $passKonfirm) {
            $errorMsg = 'Password baru dan konfirmasi tidak cocok.';
        } elseif (strlen($passBaru) < 6) {
            $errorMsg = 'Password baru minimal 6 karakter.';
        } elseif ($dbOk) {
            $q = mysqli_query($conn, "SELECT password FROM users WHERE id = $uid");
            $row = $q ? mysqli_fetch_assoc($q) : null;
            if ($row) {
                $stored = $row['password'];
                $passwordOk = password_verify($passLama, $stored)
                    || md5($passLama) === $stored;
                if ($passwordOk) {
                    $hash = password_hash($passBaru, PASSWORD_DEFAULT);
                    $hash = mysqli_real_escape_string($conn, $hash);
                    mysqli_query($conn, "UPDATE users SET password='$hash' WHERE id = $uid");
                    $successMsg = 'Password berhasil diubah.';

                    // ── Kirim notifikasi ke DB ─────────────────────────────
                    kirimNotif(
                        $conn,
                        $uid,
                        'warning',
                        'Password Diubah',
                        'Perubahan password berhasil disimpan. Gunakan password baru untuk login berikutnya.'
                    );
                    // ────────────────────────────────────────────────────────
                } else {
                    $errorMsg = 'Password lama tidak sesuai.';
                }
            } else {
                $errorMsg = 'Gagal memverifikasi password lama.';
            }
        } else {
            $successMsg = 'Password berhasil diubah. (mode demo)';
        }
    }
}

$isGuest     = false;
$active_menu = 'profil';

// ── SVG Icon helper ───────────────────────────────────────────────────────────
function icon($name, $size = 16, $style = '') {
    $s = $style ? " style=\"$style\"" : '';
    $icons = [
        'house'        => '<path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>',
        'book-open'    => '<path d="M21 4H3a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h18a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1zM3 6h8v12H3V6zm10 12V6h8v12h-8z"/>',
        'tag'          => '<path d="M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58s1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41s-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z"/>',
        'layers'       => '<path d="M11.99 18.54l-7.37-5.73L3 14.07l9 7 9-7-1.63-1.27-7.38 5.74zM12 16l7.36-5.73L21 9l-9-7-9 7 1.63 1.27L12 16z"/>',
        'history'      => '<path d="M13 3a9 9 0 0 0-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42A8.954 8.954 0 0 0 13 21a9 9 0 0 0 0-18zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/>',
        'star'         => '<path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>',
        'check-circle' => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>',
        'bell'         => '<path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/>',
        'bars'         => '<path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>',
        'user'         => '<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>',
        'user-edit'    => '<path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>',
        'lock'         => '<path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>',
        'mail'         => '<path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>',
        'phone'        => '<path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>',
        'map-pin'      => '<path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>',
        'calendar'     => '<path d="M20 3h-1V1h-2v2H7V1H5v2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 18H4V8h16v13z"/>',
        'eye'          => '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>',
        'eye-off'      => '<path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/>',
        'check'        => '<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>',
        'alert'        => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>',
        'sign-out'     => '<path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>',
        'sign-in'      => '<path d="M11 7L9.6 8.4l2.6 2.6H2v2h10.2l-2.6 2.6L11 17l5-5-5-5zm9 12h-8v2h8c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-8v2h8v14z"/>',
        'user-plus'    => '<path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V8H4v2H2v2h2v2h2v-2h2v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>',
        'book-reader'  => '<path d="M21 5c-1.11-.35-2.33-.5-3.5-.5-1.95 0-4.05.4-5.5 1.5-1.45-1.1-3.55-1.5-5.5-1.5S2.45 4.9 1 6v14.65c0 .25.25.5.5.5.1 0 .15-.05.25-.05C3.1 20.45 5.05 20 6.5 20c1.95 0 4.05.4 5.5 1.5 1.35-.85 3.8-1.5 5.5-1.5 1.65 0 3.35.3 4.75 1.05.1.05.15.05.25.05.25 0 .5-.25.5-.5V6c-.6-.45-1.25-.75-2-1zm0 13.5c-1.1-.35-2.3-.5-3.5-.5-1.7 0-4.15.65-5.5 1.5V8c1.35-.85 3.8-1.5 5.5-1.5 1.2 0 2.4.15 3.5.5v11.5z"/>',
        'bookmark'     => '<path d="M17 3H7c-1.1 0-1.99.9-1.99 2L5 21l7-3 7 3V5c0-1.1-.9-2-2-2z"/>',
        'shield'       => '<path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/>',
    ];
    $path = $icons[$name] ?? '<circle cx="12" cy="12" r="10"/>';
    return "<svg xmlns='http://www.w3.org/2000/svg' width='{$size}' height='{$size}' viewBox='0 0 24 24' fill='currentColor'{$s}>{$path}</svg>";
}

$namaInisial  = strtoupper(substr($user['nama_lengkap'], 0, 1));
$tglDaftar    = !empty($user['tgl_daftar'])
    ? date('d M Y', strtotime($user['tgl_daftar']))
    : date('d M Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya — Pojok Baca</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/anggota/sidebar.css">
    <link rel="stylesheet" href="../assets/css/anggota/profil.css">
</head>
<body>

<?php require_once '../includes/anggota/sidebar.php'; ?>

<div class="main">

    <!-- ── TOPBAR ── -->
    <div class="topbar">
        <div class="topbar-left">
            <button class="menu-btn" onclick="toggleSidebar()">
                <?= icon('bars', 20) ?>
            </button>
            <div>
                <div class="topbar-title">Profil Saya</div>
                <div class="topbar-breadcrumb">Pojok Baca / <span>Profil Saya</span></div>
            </div>
        </div>
        <div class="topbar-right">
            <div class="user-chip">
                <div class="chip-ava"><?= $namaInisial ?></div>
                <div>
                    <div class="chip-name"><?= htmlspecialchars(explode(' ', $user['nama_lengkap'])[0]) ?></div>
                    <div class="chip-role">Member</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── CONTENT ── -->
    <div class="content">

        <!-- HERO -->
        <div class="hero anim anim-d1">
            <div class="hero-text">
                <div class="hero-eyebrow">
                    <?= icon('user', 13) ?> Akun Anggota
                </div>
                <h2>Profil <span>Saya</span></h2>
                <p>Kelola informasi pribadi dan keamanan akun Anda di Pojok Baca.</p>
            </div>
            <div class="hero-deco"><?= icon('shield', 90) ?></div>
        </div>

        <!-- ALERT -->
        <?php if ($successMsg): ?>
        <div class="alert alert-success anim anim-d1">
            <?= icon('check-circle', 18) ?>
            <span><?= htmlspecialchars($successMsg) ?></span>
        </div>
        <?php elseif ($errorMsg): ?>
        <div class="alert alert-error anim anim-d1">
            <?= icon('alert', 18) ?>
            <span><?= htmlspecialchars($errorMsg) ?></span>
        </div>
        <?php endif; ?>

        <!-- STATS -->
        <div class="stats anim anim-d2">
            <div class="stat-card">
                <div class="stat-icon purple"><?= icon('book-reader', 22) ?></div>
                <div>
                    <div class="stat-num"><?= $statKoleksi ?></div>
                    <div class="stat-label">Total Koleksi</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon teal"><?= icon('check-circle', 22) ?></div>
                <div>
                    <div class="stat-num"><?= $statSelesai ?></div>
                    <div class="stat-label">Buku Selesai</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon gold"><?= icon('star', 22) ?></div>
                <div>
                    <div class="stat-num"><?= $statWishlist ?></div>
                    <div class="stat-label">Wishlist</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><?= icon('history', 22) ?></div>
                <div>
                    <div class="stat-num"><?= $statRiwayat ?></div>
                    <div class="stat-label">Riwayat Baca</div>
                </div>
            </div>
        </div>

        <!-- MAIN GRID -->
        <div class="profil-grid anim anim-d3">

            <!-- ── KARTU IDENTITAS ── -->
            <div class="card identity-card">
                <div class="identity-avatar">
                    <?= $namaInisial ?>
                </div>
                <div class="identity-name"><?= htmlspecialchars($user['nama_lengkap']) ?></div>
                <div class="identity-username">@<?= htmlspecialchars($user['username']) ?></div>
                <span class="identity-badge">
                    <?= icon('check-circle', 13) ?> Member Aktif
                </span>

                <div class="identity-meta">
                    <div class="meta-row">
                        <?= icon('mail', 14) ?>
                        <span><?= htmlspecialchars($user['email']) ?></span>
                    </div>
                    <?php if (!empty($user['no_telepon'])): ?>
                    <div class="meta-row">
                        <?= icon('phone', 14) ?>
                        <span><?= htmlspecialchars($user['no_telepon']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($user['alamat'])): ?>
                    <div class="meta-row">
                        <?= icon('map-pin', 14) ?>
                        <span><?= htmlspecialchars($user['alamat']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="meta-row">
                        <?= icon('calendar', 14) ?>
                        <span>Bergabung <?= $tglDaftar ?></span>
                    </div>
                </div>

                <a href="../auth/logout.php" class="btn-logout-profil">
                    <?= icon('sign-out', 15) ?> Keluar
                </a>
            </div>

            <!-- ── TAB PANEL ── -->
            <div class="card panel-card">

                <!-- Tab Nav -->
                <div class="tab-nav">
                    <button class="tab-btn active" onclick="switchTab('edit', this)">
                        <?= icon('user-edit', 15) ?> Edit Profil
                    </button>
                    <button class="tab-btn" onclick="switchTab('password', this)">
                        <?= icon('lock', 15) ?> Ganti Password
                    </button>
                </div>

                <!-- ── TAB: EDIT PROFIL ── -->
                <div class="tab-content active" id="tab-edit">
                    <form method="POST" class="profil-form">
                        <input type="hidden" name="action" value="update_profil">

                        <div class="form-section-title">
                            <?= icon('user', 15) ?> Informasi Pribadi
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Nama Lengkap <span class="req">*</span></label>
                                <input type="text" name="nama_lengkap"
                                       value="<?= htmlspecialchars($user['nama_lengkap']) ?>"
                                       placeholder="Nama lengkap Anda"
                                       required>
                            </div>
                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" value="<?= htmlspecialchars($user['username']) ?>"
                                       disabled class="input-disabled">
                                <span class="form-hint">Username tidak dapat diubah.</span>
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" value="<?= htmlspecialchars($user['email']) ?>"
                                       disabled class="input-disabled">
                                <span class="form-hint">Email tidak dapat diubah.</span>
                            </div>
                            <div class="form-group">
                                <label>Nomor HP</label>
                                <input type="text" name="no_telepon"
                                       value="<?= htmlspecialchars($user['no_telepon'] ?? '') ?>"
                                       placeholder="08xxxxxxxxxx">
                            </div>
                        </div>

                        <div class="form-group full">
                            <label>Alamat</label>
                            <textarea name="alamat" rows="3"
                                      placeholder="Alamat lengkap Anda"><?= htmlspecialchars($user['alamat'] ?? '') ?></textarea>
                        </div>

                        <div class="form-footer">
                            <button type="submit" class="btn-save">
                                <?= icon('check', 16) ?> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── TAB: GANTI PASSWORD ── -->
                <div class="tab-content" id="tab-password">
                    <form method="POST" class="profil-form">
                        <input type="hidden" name="action" value="ganti_password">

                        <div class="form-section-title">
                            <?= icon('shield', 15) ?> Keamanan Akun
                        </div>

                        <div class="form-group full">
                            <label>Password Saat Ini <span class="req">*</span></label>
                            <div class="input-pass-wrap">
                                <input type="password" name="password_lama" id="passLama"
                                       placeholder="Masukkan password saat ini" required>
                                <button type="button" class="toggle-pass"
                                        onclick="togglePass('passLama', this)">
                                    <?= icon('eye', 16) ?>
                                </button>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Password Baru <span class="req">*</span></label>
                                <div class="input-pass-wrap">
                                    <input type="password" name="password_baru" id="passBaru"
                                           placeholder="Min. 6 karakter" required>
                                    <button type="button" class="toggle-pass"
                                            onclick="togglePass('passBaru', this)">
                                        <?= icon('eye', 16) ?>
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Konfirmasi Password Baru <span class="req">*</span></label>
                                <div class="input-pass-wrap">
                                    <input type="password" name="password_konfirmasi" id="passKonfirm"
                                           placeholder="Ulangi password baru" required>
                                    <button type="button" class="toggle-pass"
                                            onclick="togglePass('passKonfirm', this)">
                                        <?= icon('eye', 16) ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="password-tips">
                            <?= icon('shield', 14) ?>
                            <ul>
                                <li>Gunakan minimal 6 karakter</li>
                                <li>Kombinasikan huruf besar, kecil, dan angka</li>
                                <li>Hindari kata sandi yang mudah ditebak</li>
                            </ul>
                        </div>

                        <div class="form-footer">
                            <button type="submit" class="btn-save">
                                <?= icon('lock', 16) ?> Ubah Password
                            </button>
                        </div>
                    </form>
                </div>

            </div><!-- /panel-card -->
        </div><!-- /profil-grid -->

    </div><!-- /content -->
</div><!-- /main -->

<script>
    /* ── Sidebar Toggle ── */
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('overlay').classList.toggle('show');
    }
    function closeSidebar() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('overlay').classList.remove('show');
    }

    /* ── Tab Switch ── */
    function switchTab(name, btn) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('tab-' + name).classList.add('active');
    }

    /* ── Toggle Password Visibility ── */
    function togglePass(inputId, btn) {
        const inp = document.getElementById(inputId);
        const isPass = inp.type === 'password';
        inp.type = isPass ? 'text' : 'password';
        btn.innerHTML = isPass
            ? `<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='currentColor'><path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/></svg>`
            : `<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='currentColor'><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>`;
    }

    /* ── Auto-buka tab password jika POST action = ganti_password ── */
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ganti_password'): ?>
    document.addEventListener('DOMContentLoaded', () => {
        const btn = document.querySelectorAll('.tab-btn')[1];
        if (btn) switchTab('password', btn);
    });
    <?php endif; ?>
</script>

</body>
</html>