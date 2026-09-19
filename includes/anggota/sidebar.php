<?php
$isGuest = $isGuest ?? false;
$active_menu = $active_menu ?? '';

// ── Hitung notifikasi belum dibaca untuk badge sidebar ────────────────────────
$_sidebarUnread = 0;
$_sbUid = (int) ($uid ?? $_SESSION['user_id'] ?? 0);
if (!$isGuest && $_sbUid > 0 && isset($conn) && $conn instanceof mysqli) {
    $tblCheck = mysqli_query($conn, "SHOW TABLES LIKE 'notifikasi'");
    if ($tblCheck && mysqli_num_rows($tblCheck) > 0) {
        $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM notifikasi WHERE user_id = $_sbUid AND is_read = 0");
        if ($r) $_sidebarUnread = (int) mysqli_fetch_assoc($r)['c'];
    }
}
?>

<div class="overlay" id="overlay" onclick="closeSidebar()"></div>

<!-- ── SIDEBAR ── -->
<aside class="sidebar" id="sidebar">

    <!-- BRAND -->
    <div class="brand">
        <div class="brand-logo"><?= icon('book-open', 20) ?></div>
        <div>
            <div class="brand-name">Pojok Baca</div>
            <div class="brand-sub"><?= $isGuest ? 'Portal Guest' : 'Portal Anggota' ?></div>
        </div>
    </div>

    <!-- USER -->
    <div class="sidebar-user">
        <div class="user-ava" style="overflow:hidden;">
            <?php if (!$isGuest && !empty($user['foto_profil']) && is_file(__DIR__ . '/../../uploads/profil/' . $user['foto_profil'])): ?>
                <img src="../uploads/profil/<?= htmlspecialchars($user['foto_profil']) ?>?v=<?= time() ?>"
                     alt="" style="width:100%;height:100%;object-fit:cover;">
            <?php else: ?>
                <?= strtoupper(substr($user['nama_lengkap'], 0, 1)) ?>
            <?php endif; ?>
        </div>
        <div>
            <div class="user-name"><?= htmlspecialchars($user['nama_lengkap']) ?></div>
            <span class="user-badge <?= $isGuest ? 'guest' : 'member' ?>">
                <?= $isGuest ? icon('lock', 11).' Tamu' : icon('check-circle', 11).' Member' ?>
            </span>
        </div>
    </div>

    <!-- NAV -->
    <nav>

        <div class="nav-label">MAIN</div>
        <a href="../anggota/dashboard.php" class="nav-link <?= ($active_menu == 'dashboard') ? 'active' : '' ?>">
            <span><?= icon('house', 16) ?></span> Beranda
        </a>

        <div class="nav-label">KOLEKSI</div>
        <a href="../anggota/katalog_ebook.php" class="nav-link <?= ($active_menu == 'katalog') ? 'active' : '' ?>">
            <span><?= icon('book-open', 16) ?></span> Katalog eBook
        </a>
        <a href="../anggota/kategori.php" class="nav-link <?= ($active_menu == 'kategori') ? 'active' : '' ?>">
            <span><?= icon('tag', 16) ?></span> Kategori
        </a>

        <div class="nav-label">AKTIVITAS</div>
        <?php if ($isGuest): ?>
            <a href="#" class="nav-link locked" onclick="showToast(event)">
                <span><?= icon('layers', 16) ?></span> Koleksi Saya
                <span class="lock"><?= icon('lock', 12) ?></span>
            </a>
            <a href="#" class="nav-link locked" onclick="showToast(event)">
                <span><?= icon('history', 16) ?></span> Riwayat Baca
                <span class="lock"><?= icon('lock', 12) ?></span>
            </a>
            <a href="#" class="nav-link locked" onclick="showToast(event)">
                <span><?= icon('star', 16) ?></span> Wishlist
                <span class="lock"><?= icon('lock', 12) ?></span>
            </a>
        <?php else: ?>
            <a href="../anggota/koleksi.php" class="nav-link <?= ($active_menu == 'koleksi') ? 'active' : '' ?>">
                <span><?= icon('layers', 16) ?></span> Koleksi Saya
            </a>
            <a href="../anggota/riwayat.php" class="nav-link <?= ($active_menu == 'riwayat') ? 'active' : '' ?>">
                <span><?= icon('history', 16) ?></span> Riwayat Baca
            </a>
            <a href="../anggota/wishlist.php" class="nav-link <?= ($active_menu == 'wishlist') ? 'active' : '' ?>">
                <span><?= icon('star', 16) ?></span> Wishlist
            </a>
        <?php endif; ?>

        <?php if (!$isGuest): ?>
        <div class="nav-label">AKUN</div>
            <a href="../anggota/notifikasi.php" class="nav-link <?= ($active_menu == 'notifikasi') ? 'active' : '' ?>" style="position:relative;">
                <span><?= icon('bell', 16) ?></span> Notifikasi
                <?php if ($_sidebarUnread > 0): ?>
                <span class="notif-sidebar-dot" title="<?= $_sidebarUnread ?> belum dibaca"></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

    </nav>

    <!-- FOOTER -->
    <div class="sidebar-footer">
        <?php if ($isGuest): ?>
            <div class="footer-actions">
                <a href="../index.php" class="btn-logout btn-logout-ghost">
                    <?= icon('sign-in', 16) ?> Login
                </a>
                <a href="../auth/register.php" class="btn-logout register">
                    <?= icon('user-plus', 16) ?> Daftar
                </a>
            </div>
        <?php else: ?>
            <a href="../auth/logout.php" class="btn-logout">
                <?= icon('sign-out', 16) ?> Keluar
            </a>
        <?php endif; ?>
    </div>

<style>
.notif-sidebar-dot {
    position: absolute;
    top: 50%;
    right: 14px;
    transform: translateY(-50%);
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #ef4444;
    box-shadow: 0 0 0 2px #1a1d2e;
    animation: notif-pulse 2s infinite;
    flex-shrink: 0;
    display: inline-block;
}
@keyframes notif-pulse {
    0%   { box-shadow: 0 0 0 2px #1a1d2e, 0 0 0 0 rgba(239,68,68,.6); }
    70%  { box-shadow: 0 0 0 2px #1a1d2e, 0 0 0 6px rgba(239,68,68,0); }
    100% { box-shadow: 0 0 0 2px #1a1d2e, 0 0 0 0 rgba(239,68,68,0); }
}
</style>
</aside>