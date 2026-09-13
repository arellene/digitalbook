<?php
session_start();
require_once '../config/database.php';

if (isset($_SESSION['user_id']) && $_SESSION['role'] != 'guest') {
    if ($_SESSION['role'] == 'admin') {
        header("Location: ../admin/dashboard.php");
    } else {
        header("Location: ../anggota/dashboard.php");
    }
    exit();
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_lengkap     = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
    $email            = mysqli_real_escape_string($conn, $_POST['email']);
    $username         = mysqli_real_escape_string($conn, $_POST['username']);
    $password         = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $no_telepon       = mysqli_real_escape_string($conn, $_POST['no_telepon']);
    $alamat           = mysqli_real_escape_string($conn, $_POST['alamat']);

    if ($password !== $confirm_password) {
        $error = 'Password dan konfirmasi password tidak cocok!';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } else {
        $check = "SELECT * FROM users WHERE username='$username' OR email='$email'";
        if (mysqli_num_rows(mysqli_query($conn, $check)) > 0) {
            $error = 'Username atau email sudah terdaftar!';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $insert = "INSERT INTO users (nama_lengkap, email, username, password, no_telepon, alamat, role)
                       VALUES ('$nama_lengkap','$email','$username','$hashed','$no_telepon','$alamat','anggota')";
            if (mysqli_query($conn, $insert)) {
                $success = 'Registrasi berhasil! Silakan login.';
            } else {
                $error = 'Terjadi kesalahan: ' . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - Digital Book</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/auth/register.css">
</head>

<body>

    <div class="container">

        <!-- Left Panel -->
        <div class="left-panel">
            <div class="circle c1"></div>
            <div class="circle c2"></div>
            <div class="circle c3"></div>
            <div class="circle c4"></div>
            <div class="circle c5"></div>
            <div class="circle c6"></div>
            <div class="circle c7"></div>
            <div class="circle c8"></div>

            <div class="left-content">
                <svg width="96" height="80" viewBox="0 0 96 80" fill="none" xmlns="http://www.w3.org/2000/svg" class="book-icon">
                    <ellipse cx="46" cy="74" rx="30" ry="5" fill="#1a1a2e" opacity="0.13"/>
                    <path d="M8 12 Q8 8 12 8 L44 8 Q46 8 46 12 L46 64 Q46 66 44 66 L12 66 Q8 66 8 62 Z" fill="#fff" stroke="#1a1a2e" stroke-width="2"/>
                    <path d="M44 8 L46 8 L46 66 L44 66 Z" fill="#e0e0e0"/>
                    <line x1="16" y1="22" x2="40" y2="22" stroke="#E84393" stroke-width="2.2" stroke-linecap="round"/>
                    <line x1="16" y1="30" x2="40" y2="30" stroke="#E84393" stroke-width="2.2" stroke-linecap="round"/>
                    <line x1="16" y1="38" x2="40" y2="38" stroke="#1DB5E0" stroke-width="2.2" stroke-linecap="round"/>
                    <line x1="16" y1="46" x2="32" y2="46" stroke="#1DB5E0" stroke-width="2.2" stroke-linecap="round"/>
                    <path d="M48 12 Q48 8 50 8 L82 8 Q86 8 86 12 L86 62 Q86 66 82 66 L50 66 Q48 66 48 64 Z" fill="#fff" stroke="#1a1a2e" stroke-width="2"/>
                    <line x1="54" y1="22" x2="80" y2="22" stroke="#FF6B35" stroke-width="2.2" stroke-linecap="round"/>
                    <line x1="54" y1="30" x2="80" y2="30" stroke="#FF6B35" stroke-width="2.2" stroke-linecap="round"/>
                    <line x1="54" y1="38" x2="80" y2="38" stroke="#9C27B0" stroke-width="2.2" stroke-linecap="round"/>
                    <path d="M44 8 Q47 4 50 8 L50 66 Q47 70 44 66 Z" fill="#1a1a2e" opacity="0.18"/>
                    <g transform="rotate(-35, 74, 52)">
                        <rect x="70" y="34" width="7" height="28" rx="2" fill="#F5C842" stroke="#1a1a2e" stroke-width="1.5"/>
                        <polygon points="70,62 77,62 73.5,70" fill="#1a1a2e"/>
                        <rect x="70" y="34" width="7" height="6" rx="1.5" fill="#E84393"/>
                        <circle cx="73.5" cy="71" r="1.5" fill="#1DB5E0"/>
                    </g>
                </svg>
                <h1>Bergabung<br>Sekarang!</h1>
                <p>Daftarkan akunmu dan<br>mulai jelajahi koleksi buku digital</p>
            </div>
        </div>

        <!-- Right Panel -->
        <div class="right-panel">
            <h2>Buat <span>Akun Baru</span></h2>
            <p class="subtitle">Digital Book — Formulir Pendaftaran</p>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
                    <a href="../index.php">Login di sini</a>
                </div>
            <?php endif; ?>

            <form method="POST" action="">

                <div class="section-label">Data Pribadi</div>

                <div class="field">
                    <label>Nama Lengkap <span class="req">*</span></label>
                    <div class="input-wrap">
                        <i class="fas fa-id-card input-icon"></i>
                        <input type="text" name="nama_lengkap" required placeholder="Nama lengkap sesuai identitas"
                            value="<?= htmlspecialchars($_POST['nama_lengkap'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label>Email <span class="req">*</span></label>
                        <div class="input-wrap">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="email" name="email" required placeholder="email@contoh.com"
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="field">
                        <label>No. Telepon</label>
                        <div class="input-wrap">
                            <i class="fas fa-phone input-icon"></i>
                            <input type="tel" name="no_telepon" placeholder="08xxxxxxxxxx"
                                value="<?= htmlspecialchars($_POST['no_telepon'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="field">
                    <label>Alamat</label>
                    <div class="input-wrap textarea-wrap">
                        <i class="fas fa-map-marker-alt input-icon" style="align-self:flex-start;margin-top:11px;"></i>
                        <textarea name="alamat" placeholder="Alamat lengkap"><?= htmlspecialchars($_POST['alamat'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="section-label">Data Akun</div>

                <div class="field">
                    <label>Username <span class="req">*</span></label>
                    <div class="input-wrap">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" name="username" required placeholder="Pilih username unik"
                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>
                    <div class="hint">Gunakan huruf, angka, atau underscore.</div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label>Password <span class="req">*</span></label>
                        <div class="input-wrap pw-wrap">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" name="password" id="pw1" required placeholder="Min. 6 karakter">
                            <button type="button" class="pw-toggle" onclick="togglePw('pw1','ic1')" aria-label="Tampilkan password">
                                <i class="fas fa-eye" id="ic1"></i>
                            </button>
                        </div>
                    </div>
                    <div class="field">
                        <label>Konfirmasi Password <span class="req">*</span></label>
                        <div class="input-wrap pw-wrap">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" name="confirm_password" id="pw2" required placeholder="Ulangi password">
                            <button type="button" class="pw-toggle" onclick="togglePw('pw2','ic2')" aria-label="Tampilkan konfirmasi">
                                <i class="fas fa-eye" id="ic2"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="btn-group">
                    <a href="../index.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                    <button type="submit" class="btn-daftar">DAFTAR SEKARANG</button>
                </div>

            </form>

            <div class="login-link">
                Sudah punya akun? <a href="../index.php">Login di sini</a>
            </div>
        </div>

    </div>

    <script>
        function togglePw(id, iconId) {
            const input = document.getElementById(id);
            const icon  = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>

</body>
</html>