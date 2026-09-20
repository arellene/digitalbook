<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['role'])) {
    header('Location: ../index.php');
    exit();
}

$isGuest = ($_SESSION['role'] === 'guest');

// Redirect guest — notifikasi hanya untuk member
if ($isGuest) {
    header('Location: ../anggota/dashboard.php');
    exit();
}

if (!is_numeric($_SESSION['user_id'] ?? '')) {
    header('Location: ../index.php');
    exit();
}

$dbOk = isset($conn) && $conn instanceof mysqli;
$uid  = (int) ($_SESSION['user_id'] ?? 0);

$user = null;
if ($dbOk) {
    $q    = mysqli_query($conn, "SELECT * FROM users WHERE id = $uid LIMIT 1");
    $user = $q ? mysqli_fetch_assoc($q) : null;
}
if (empty($user)) {
    $user = [
        'nama_lengkap' => $_SESSION['nama_lengkap'] ?? 'Pengguna',
        'username'     => $_SESSION['username']     ?? 'user',
        'role'         => $_SESSION['role']         ?? 'anggota',
    ];
}

// ── Ambil notifikasi dari DB (jika tabel ada) ────────────────────────
// Struktur tabel: id, user_id, type, judul, pesan, is_read, created_at
// Kolom 'type': info | success | warning | danger
$notifications = [];
if ($dbOk) {
    $tbl = mysqli_query($conn, "SHOW TABLES LIKE 'notifikasi'");
    if ($tbl && mysqli_num_rows($tbl) > 0) {
        $r = mysqli_query($conn,
            "SELECT * FROM notifikasi WHERE user_id = $uid ORDER BY created_at DESC LIMIT 50"
        );
        if ($r) {
            while ($row = mysqli_fetch_assoc($r)) $notifications[] = $row;
        }
        // Tandai sudah dibaca dilakukan via AJAX saat user klik, bukan otomatis saat page load
    }
}

// Tidak ada fallback dummy — tampilkan data nyata dari DB saja

$unreadCount = count(array_filter($notifications, fn($n) => !$n['is_read']));
$totalCount  = count($notifications);

// ── SVG ICON HELPER ─────────────────────────────────────────────────────────
function icon($name, $size = 16, $style = '') {
    $s = $style ? " style=\"$style\"" : '';
    $icons = [
        'house'        => '<path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>',
        'book-open'    => '<path d="M21 4H3a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h18a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1zM3 6h8v12H3V6zm10 12V6h8v12h-8z"/>',
        'tag'          => '<path d="M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58s1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41s-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z"/>',
        'layers'       => '<path d="M11.99 18.54l-7.37-5.73L3 14.07l9 7 9-7-1.63-1.27-7.38 5.74zM12 16l7.36-5.73L21 9l-9-7-9 7 1.63 1.27L12 16z"/>',
        'history'      => '<path d="M13 3a9 9 0 0 0-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42A8.954 8.954 0 0 0 13 21a9 9 0 0 0 0-18zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/>',
        'star'         => '<path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>',
        'lock'         => '<path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>',
        'user-plus'    => '<path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>',
        'sign-in'      => '<path d="M11 7L9.6 8.4l2.6 2.6H2v2h10.2l-2.6 2.6L11 17l5-5-5-5zm9 12h-8v2h8c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-8v2h8v14z"/>',
        'sign-out'     => '<path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>',
        'user'         => '<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>',
        'check-circle' => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>',
        'bell'         => '<path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/>',
        'bell-off'     => '<path d="M20 18.69L7.84 6.14 5.27 3.49 4 4.76l2.8 2.8C6.29 8.28 6 9.1 6 10v5l-2 2v1h13.73l2 2L21 18.69l-1-1zM12 22c1.11 0 2-.89 2-2h-4c0 1.11.89 2 2 2zm6-7.73V10c0-3.07-1.64-5.64-4.5-6.32V3c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68c-.48.11-.94.28-1.38.48L18 14.27z"/>',
        'bars'         => '<path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>',
        'arrow-right'  => '<path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6z"/>',
        'check'        => '<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>',
        'trash'        => '<path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>',
        'info'         => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>',
        'warning'      => '<path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/>',
        'danger'       => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>',
        'success'      => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>',
        'filter'       => '<path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/>',
        'dots'         => '<path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/>',
        'pencil'       => '<path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>',
        'user-lock'    => '<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-1.5 0-4.5.75-5.99 2.25C7.18 17.38 9.32 18 12 18c.36 0 .71-.02 1.05-.05-.03-.3-.05-.6-.05-.95 0-1.87.85-3.54 2.18-4.67C14.08 12.11 12.63 14 12 14zm6 1c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3zm0 4.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>',
        'profil'       => '<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>',
        'inbox'        => '<path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5v-3h3.56c.69 1.19 1.97 2 3.45 2s2.75-.81 3.45-2H19v3zm0-5h-4.99c0 1.1-.9 1.99-2.01 1.99S10 15.1 10 14H5V5h14v9z"/>',
    ];
    $path = $icons[$name] ?? '<circle cx="12" cy="12" r="10"/>';
    return "<svg xmlns='http://www.w3.org/2000/svg' width='{$size}' height='{$size}' viewBox='0 0 24 24' fill='currentColor'{$s}>{$path}</svg>";
}

$active_menu = "notifikasi";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi — Pojok Baca</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- CSS Sidebar & Dashboard (sudah ada) -->
    <link rel="stylesheet" href="../assets/css/anggota/sidebar.css">
    <link rel="stylesheet" href="../assets/css/anggota/dashboard.css">
    <!-- CSS Notifikasi (baru) -->
    <link rel="stylesheet" href="../assets/css/anggota/notifikasi.css">
</head>
<body>

<?php require_once '../includes/anggota/sidebar.php'; ?>

<div class="main">

    <!-- TOPBAR -->
    <div class="topbar">
        <div class="topbar-left">
            <button class="menu-btn" onclick="toggleSidebar()" aria-label="Menu">
                <?= icon('bars', 22) ?>
            </button>
            <div>
                <div class="topbar-title">Notifikasi</div>
                <div class="topbar-breadcrumb">
                    Pojok Baca &rsaquo; <span>Notifikasi</span>
                </div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="profil.php" class="user-chip" style="text-decoration:none;color:inherit;">
                <div class="chip-ava" style="overflow:hidden;">
                    <?php if (!empty($user['foto_profil']) && is_file(__DIR__ . '/../uploads/profil/' . $user['foto_profil'])): ?>
                        <img src="../uploads/profil/<?= htmlspecialchars($user['foto_profil']) ?>?v=<?= time() ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                        <?= strtoupper(substr($user['nama_lengkap'], 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="chip-name"><?= htmlspecialchars($user['nama_lengkap']) ?></div>
                    <div class="chip-role">Member</div>
                </div>
            </a>
        </div>
    </div>

    <!-- CONTENT -->
    <div class="content">

        <!-- NOTIF HEADER CARD -->
        <div class="notif-hero anim anim-d1">
            <div class="notif-hero-icon">
                <?= icon('bell', 28) ?>
                <?php if ($unreadCount > 0): ?>
                <span class="notif-hero-badge"><?= $unreadCount ?></span>
                <?php endif; ?>
            </div>
            <div class="notif-hero-info">
                <h2>Pusat Notifikasi</h2>
                <p>
                    <?php if ($unreadCount > 0): ?>
                        Kamu punya <strong><?= $unreadCount ?> notifikasi belum dibaca</strong> dari total <?= $totalCount ?> notifikasi.
                    <?php else: ?>
                        Semua notifikasi sudah dibaca. Total <?= $totalCount ?> notifikasi.
                    <?php endif; ?>
                </p>
            </div>
            <div class="notif-hero-actions">
                <?php if ($unreadCount > 0): ?>
                <button class="notif-btn-markall" onclick="markAllRead()">
                    <?= icon('check', 14) ?> Tandai Semua Dibaca
                </button>
                <?php endif; ?>
                <button class="notif-btn-filter active" data-filter="all" onclick="filterNotif(this, 'all')">
                    Semua
                </button>
                <button class="notif-btn-filter" data-filter="unread" onclick="filterNotif(this, 'unread')">
                    Belum Dibaca
                </button>
            </div>
        </div>

        <!-- NOTIFIKASI LIST -->
        <div class="notif-list anim anim-d2" id="notifList">

            <?php if (empty($notifications)): ?>
            <div class="notif-empty">
                <div class="notif-empty-icon"><?= icon('bell-off', 48) ?></div>
                <h3>Tidak ada notifikasi</h3>
                <p>Kamu belum memiliki notifikasi apapun saat ini.</p>
            </div>
            <?php else: ?>

            <?php
            // Kelompokkan per hari
            $grouped = [];
            foreach ($notifications as $n) {
                $ts   = strtotime($n['created_at']);
                $today    = date('Y-m-d');
                $yesterday= date('Y-m-d', strtotime('-1 day'));
                $day  = date('Y-m-d', $ts);

                if ($day === $today)         $label = 'Hari Ini';
                elseif ($day === $yesterday) $label = 'Kemarin';
                else                         $label = date('d F Y', $ts);

                $grouped[$label][] = $n;
            }

            foreach ($grouped as $dayLabel => $items): ?>

            <div class="notif-day-group">
                <div class="notif-day-label"><?= $dayLabel ?></div>

                <?php foreach ($items as $n):
                    $typeMap = [
                        'info'    => ['icon' => 'info',    'color' => 'blue'],
                        'success' => ['icon' => 'success', 'color' => 'green'],
                        'warning' => ['icon' => 'warning', 'color' => 'yellow'],
                        'danger'  => ['icon' => 'danger',  'color' => 'red'],
                    ];
                    $tm      = $typeMap[$n['type']] ?? $typeMap['info'];
                    $isRead  = (bool) $n['is_read'];
                    $timeAgo = '';
                    $ts      = strtotime($n['created_at']);
                    $diff    = time() - $ts;
                    if      ($diff < 60)    $timeAgo = 'Baru saja';
                    elseif  ($diff < 3600)  $timeAgo = floor($diff/60) . ' menit lalu';
                    elseif  ($diff < 86400) $timeAgo = floor($diff/3600) . ' jam lalu';
                    else                    $timeAgo = date('d M Y', $ts);
                ?>
                <div class="notif-item <?= $isRead ? 'read' : 'unread' ?> type-<?= $tm['color'] ?>"
                     data-id="<?= $n['id'] ?>"
                     data-read="<?= $isRead ? '1' : '0' ?>">
                    <div class="notif-item-icon type-<?= $tm['color'] ?>-icon">
                        <?= icon($tm['icon'], 18) ?>
                    </div>
                    <div class="notif-item-body">
                        <div class="notif-item-header">
                            <span class="notif-item-title"><?= htmlspecialchars($n['judul']) ?></span>
                            <?php if (!$isRead): ?>
                            <span class="notif-unread-dot"></span>
                            <?php endif; ?>
                        </div>
                        <p class="notif-item-msg"><?= htmlspecialchars($n['pesan']) ?></p>
                        <div class="notif-item-footer">
                            <span class="notif-item-time"><?= $timeAgo ?></span>
                            <div class="notif-item-actions">
                                <?php if (!$isRead): ?>
                                <button class="notif-action-btn" onclick="markRead(<?= $n['id'] ?>, this)"
                                        title="Tandai dibaca">
                                    <?= icon('check', 13) ?> Tandai Dibaca
                                </button>
                                <?php endif; ?>
                                <button class="notif-action-btn danger" onclick="deleteNotif(<?= $n['id'] ?>, this)"
                                        title="Hapus">
                                    <?= icon('trash', 13) ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

            </div>
            <?php endforeach; ?>

            <?php endif; ?>
        </div><!-- /notif-list -->

    </div><!-- /content -->
</div><!-- /main -->

<!-- TOAST -->
<div class="toast" id="toast">
    <?= icon('check-circle', 18, 'color:var(--accent)') ?>
    <span id="toast-msg">Notifikasi diperbarui.</span>
</div>

<script>
    // ── Sidebar toggle ─────────────────────────────────────────────
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('overlay').classList.toggle('show');
    }
    function closeSidebar() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('overlay').classList.remove('show');
    }

    // ── Toast helper ──────────────────────────────────────────────
    let toastTimer;
    function showToast(msg) {
        const t = document.getElementById('toast');
        document.getElementById('toast-msg').textContent = msg;
        t.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => t.classList.remove('show'), 3500);
    }

    // ── Filter notifikasi ─────────────────────────────────────────
    function filterNotif(btn, type) {
        document.querySelectorAll('.notif-btn-filter').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll('.notif-item').forEach(item => {
            if (type === 'all') {
                item.style.display = '';
            } else {
                item.style.display = item.dataset.read === '0' ? '' : 'none';
            }
        });
        // Sembunyikan day-label kosong
        document.querySelectorAll('.notif-day-group').forEach(g => {
            const visible = [...g.querySelectorAll('.notif-item')]
                .some(i => i.style.display !== 'none');
            g.style.display = visible ? '' : 'none';
        });
    }

    // ── Tandai satu dibaca ────────────────────────────────────────
    function markRead(id, btn) {
        const item = document.querySelector(`.notif-item[data-id="${id}"]`);
        if (!item) return;
        item.classList.remove('unread');
        item.classList.add('read');
        item.dataset.read = '1';
        const dot = item.querySelector('.notif-unread-dot');
        if (dot) dot.remove();
        btn.remove();
        updateUnreadBadge(-1);
        showToast('Notifikasi ditandai sudah dibaca.');

        // Kirim ke server (ajax)
        fetch('ajax/notifikasi_update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'read', id: id })
        }).catch(() => {});
    }

    // ── Tandai semua dibaca ───────────────────────────────────────
    function markAllRead() {
        document.querySelectorAll('.notif-item.unread').forEach(item => {
            item.classList.remove('unread');
            item.classList.add('read');
            item.dataset.read = '1';
            const dot = item.querySelector('.notif-unread-dot');
            if (dot) dot.remove();
            const btn = item.querySelector('.notif-action-btn:not(.danger)');
            if (btn) btn.remove();
        });
        const badge = document.querySelector('.notif-hero-badge');
        if (badge) badge.remove();
        const markAllBtn = document.querySelector('.notif-btn-markall');
        if (markAllBtn) markAllBtn.remove();
        updateUnreadBadge(0, true);
        showToast('Semua notifikasi ditandai sudah dibaca.');

        fetch('ajax/notifikasi_update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'read_all' })
        }).catch(() => {});
    }

    // ── Hapus notifikasi ──────────────────────────────────────────
    function deleteNotif(id, btn) {
        const item = document.querySelector(`.notif-item[data-id="${id}"]`);
        if (!item) return;
        const wasUnread = item.dataset.read === '0';
        item.style.opacity = '0';
        item.style.transform = 'translateX(30px)';
        item.style.transition = 'opacity 0.3s, transform 0.3s';
        setTimeout(() => {
            item.remove();
            if (wasUnread) updateUnreadBadge(-1);
            showToast('Notifikasi dihapus.');
        }, 300);

        fetch('ajax/notifikasi_update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id: id })
        }).catch(() => {});
    }

    // ── Update badge counter ──────────────────────────────────────
    function updateUnreadBadge(delta, reset = false) {
        const badge = document.querySelector('.notif-hero-badge');
        if (reset) return;
        if (!badge) return;
        const current = parseInt(badge.textContent) || 0;
        const next = Math.max(0, current + delta);
        if (next === 0) badge.remove();
        else badge.textContent = next;
    }
</script>
</body>
</html>