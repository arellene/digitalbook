<?php
session_start();
require_once 'config/database.php';

if (isset($_SESSION['user_id']) && $_SESSION['role'] != 'guest') {
    if ($_SESSION['role'] == 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: anggota/dashboard.php");
    }
    exit();
}

if (isset($_SESSION['role']) && $_SESSION['role'] === 'guest') {
    session_destroy();
    session_start();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($_POST['guest_login'])) {
        $_SESSION['user_id']      = 'guest';
        $_SESSION['username']     = 'guest';
        $_SESSION['nama_lengkap'] = 'Tamu';
        $_SESSION['role']         = 'guest';
        header("Location: guest/dashboard.php");
        exit();
    }

    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    $query  = "SELECT * FROM users WHERE username='$username' AND status='aktif'";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) == 1) {
        $user           = mysqli_fetch_assoc($result);
        $storedPassword = $user['password'];
        $passwordOk     = password_verify($password, $storedPassword)
                          || md5($password) === $storedPassword;
        if ($passwordOk) {
            $_SESSION['user_id']      = $user['id'];
            $_SESSION['username']     = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role']         = $user['role'];

            if ($user['role'] == 'admin') {
                header("Location: admin/dashboard.php");
            } else {
                header("Location: anggota/dashboard.php");
            }
            exit();
        } else {
            $error = "Username atau password salah!";
        }
    } else {
        $error = "Username atau password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Aplikasi Digital Book</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/auth/index.css">
</head>

<body>

    <div class="container">

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
                <h1>Welcome<br>Back!</h1>
                <p>Masuk ke akun kamu<br>dan nikmati koleksi buku digital</p>
            </div>
        </div>

        <div class="right-panel">
            <h2>Hello, <span>Selamat Datang!</span></h2>
            <p class="subtitle">Digital Book — Perpustakaan Digital</p>

            <?php if ($error): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" autocomplete="off">
                <div class="field">
                    <label>Username</label>
                    <input type="text" name="username" required placeholder="Masukkan username"
                        autocomplete="off"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Kata Sandi</label>
                    <div class="pw-wrap">
                        <input type="password" name="password" id="pw" required placeholder="••••••••" autocomplete="new-password">
                        <button type="button" class="pw-toggle" onclick="togglePw()" aria-label="Tampilkan sandi">
                            <i class="fas fa-eye" id="pw-ic"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-login">MASUK</button>
            </form>

            <div class="divider">atau</div>

            <form method="POST" action="">
                <button type="submit" name="guest_login" value="1" class="btn-guest">
                    <i class="fas fa-user-secret"></i>
                    Masuk sebagai Tamu
                </button>
            </form>

            <p class="guest-note">
                <i class="fas fa-info-circle"></i>
                Akun tamu hanya dapat membaca katalog buku
            </p>

            <div class="register-link">
                Belum punya akun? <a href="auth/register.php">Daftar Sekarang</a>
            </div>
        </div>

    </div>

    <script>
        function togglePw() {
            const pw = document.getElementById('pw');
            const ic = document.getElementById('pw-ic');
            if (pw.type === 'password') {
                pw.type = 'text';
                ic.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                pw.type = 'password';
                ic.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>

</body>
</html>