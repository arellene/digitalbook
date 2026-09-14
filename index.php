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
    <title>Masuk — Pojok Baca</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --indigo-900: #1e1b4b;
            --indigo-700: #4338ca;
            --indigo-500: #6366f1;
            --violet-500: #8b5cf6;
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --paper: #ffffff;
            --canvas: #f8fafc;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--canvas);
            color: var(--ink);
            min-height: 100vh;
        }

        .shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(0, 5fr) minmax(0, 6fr);
        }

        /* ───────── LEFT: gradient stage ───────── */
        .stage {
            position: relative;
            overflow: hidden;
            background:
                radial-gradient(120% 140% at 15% 0%, #7c3aed 0%, transparent 55%),
                radial-gradient(120% 120% at 85% 100%, #4338ca 0%, transparent 60%),
                linear-gradient(160deg, var(--indigo-900) 0%, var(--indigo-700) 55%, var(--violet-500) 130%);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 56px 52px;
            color: #fff;
        }

        .stage-mark {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-family: 'Poppins', sans-serif;
            font-size: 22px;
            font-weight: 600;
            letter-spacing: .2px;
        }
        .stage-mark svg { flex-shrink: 0; }

        .stage-copy {
            max-width: 420px;
        }
        .stage-copy h1 {
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            font-size: clamp(2.1rem, 3.4vw, 2.9rem);
            line-height: 1.14;
            letter-spacing: -.01em;
            margin-bottom: 18px;
        }
        .stage-copy p {
            font-size: 15.5px;
            line-height: 1.7;
            color: rgba(255,255,255,.78);
            max-width: 380px;
        }

        .stage-shelf {
            display: flex;
            gap: 14px;
            margin-top: 40px;
        }
        .shelf-card {
            width: 64px;
            height: 92px;
            border-radius: 8px;
            background: rgba(255,255,255,.1);
            border: 1px solid rgba(255,255,255,.18);
            backdrop-filter: blur(2px);
            position: relative;
            overflow: hidden;
        }
        .shelf-card::after {
            content: '';
            position: absolute;
            inset: 12px 10px auto 10px;
            height: 2px;
            background: rgba(255,255,255,.3);
            box-shadow: 0 8px 0 rgba(255,255,255,.22), 0 16px 0 rgba(255,255,255,.16);
        }
        .shelf-card.rise-1 { transform: translateY(6px); }
        .shelf-card.rise-2 { transform: translateY(-4px); }
        .shelf-card.rise-3 { transform: translateY(10px); }

        .stage-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            font-size: 12.5px;
            color: rgba(255,255,255,.55);
        }
        .stage-footer .stat b {
            display: block;
            font-family: 'Poppins', sans-serif;
            font-size: 22px;
            font-weight: 500;
            color: #fff;
            margin-bottom: 2px;
        }
        .stage-stats { display: flex; gap: 32px; }

        /* ───────── RIGHT: form panel ───────── */
        .panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            background: var(--paper);
        }
        .panel-inner {
            width: 100%;
            max-width: 380px;
        }

        .panel-head {
            margin-bottom: 30px;
        }
        .panel-head h2 {
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            font-size: 1.9rem;
            letter-spacing: -.01em;
            margin-bottom: 8px;
        }
        .panel-head p {
            font-size: 14px;
            color: var(--muted);
        }
        .panel-head a {
            color: var(--indigo-700);
            font-weight: 600;
            text-decoration: none;
        }
        .panel-head a:hover { text-decoration: underline; }

        .alert-error {
            display: flex;
            align-items: center;
            gap: 9px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            font-size: 13px;
            padding: 11px 14px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .field { margin-bottom: 16px; }
        .field label {
            display: block;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 7px;
        }
        .field input {
            width: 100%;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1.5px solid var(--line);
            font-family: inherit;
            font-size: 14.5px;
            color: var(--ink);
            background: var(--canvas);
            transition: border-color .15s, background .15s;
        }
        .field input:focus {
            outline: none;
            border-color: var(--indigo-500);
            background: #fff;
        }
        .pw-wrap { position: relative; }
        .pw-wrap input { padding-right: 44px; }
        .pw-toggle {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--muted);
        }
        .pw-toggle:hover { background: var(--canvas); color: var(--ink); }

        .row-between {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 22px;
        }
        .row-between a {
            font-size: 13px;
            color: var(--indigo-700);
            text-decoration: none;
            font-weight: 500;
        }
        .row-between a:hover { text-decoration: underline; }

        .btn-masuk {
            width: 100%;
            padding: 13px;
            border-radius: 10px;
            border: none;
            background: linear-gradient(135deg, var(--indigo-700), var(--violet-500));
            color: #fff;
            font-family: inherit;
            font-size: 14.5px;
            font-weight: 700;
            letter-spacing: .2px;
            cursor: pointer;
            transition: opacity .15s, transform .15s;
        }
        .btn-masuk:hover { opacity: .92; }
        .btn-masuk:active { transform: scale(.99); }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 22px 0;
            color: var(--muted);
            font-size: 12.5px;
        }
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--line);
        }

        .btn-guest {
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            border: 1.5px solid var(--line);
            background: var(--paper);
            color: var(--ink);
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            transition: border-color .15s, background .15s;
        }
        .btn-guest:hover { border-color: var(--indigo-500); background: var(--canvas); }

        .guest-note {
            font-size: 12px;
            color: var(--muted);
            text-align: center;
            margin-top: 14px;
            line-height: 1.6;
        }

        @media (max-width: 880px) {
            .shell { grid-template-columns: 1fr; }
            .stage { display: none; }
            .panel { padding: 28px; }
        }
    </style>
</head>

<body>

    <div class="shell">

        <!-- ───────── LEFT: brand stage ───────── -->
        <div class="stage">
            <div class="stage-mark">
                <svg width="30" height="26" viewBox="0 0 30 26" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M2 4 Q2 2 4 2 L13 2 Q15 2 15 4 L15 22 Q15 24 13 24 L4 24 Q2 24 2 22 Z" fill="#fff" opacity=".95"/>
                    <path d="M15 4 Q15 2 17 2 L26 2 Q28 2 28 4 L28 22 Q28 24 26 24 L17 24 Q15 24 15 22 Z" fill="#fff" opacity=".7"/>
                    <line x1="5" y1="8" x2="12" y2="8" stroke="#4338ca" stroke-width="1.4" stroke-linecap="round"/>
                    <line x1="5" y1="12" x2="12" y2="12" stroke="#4338ca" stroke-width="1.4" stroke-linecap="round"/>
                    <line x1="18" y1="8" x2="25" y2="8" stroke="#4338ca" stroke-width="1.4" stroke-linecap="round" opacity=".6"/>
                    <line x1="18" y1="12" x2="25" y2="12" stroke="#4338ca" stroke-width="1.4" stroke-linecap="round" opacity=".6"/>
                </svg>
                Pojok Baca
            </div>

            <div class="stage-copy">
                <h1>Ribuan cerita menunggu untuk dibuka.</h1>
                <p>Satu akun, satu rak digital — baca, simpan, dan lanjutkan kapan saja kamu mau.</p>

                <div class="stage-shelf">
                    <div class="shelf-card rise-1"></div>
                    <div class="shelf-card rise-3"></div>
                    <div class="shelf-card rise-2"></div>
                    <div class="shelf-card rise-1"></div>
                    <div class="shelf-card rise-3"></div>
                </div>
            </div>

            <div class="stage-footer">
                <div class="stage-stats">
                    <div class="stat"><b>1.2K+</b>Free eBook</div>
                    <div class="stat"><b>24/7</b>Akses baca</div>
                </div>
                <div>Perpustakaan Digital</div>
            </div>
        </div>

        <!-- ───────── RIGHT: form ───────── -->
        <div class="panel">
            <div class="panel-inner">

                <div class="panel-head">
                    <h2>Selamat datang kembali</h2>
                    <p>Belum punya akun? <a href="auth/register.php">Daftar di sini</a></p>
                </div>

                <?php if ($error): ?>
                <div class="alert-error">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                    <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="" autocomplete="off">
                    <div class="field">
                        <label for="username">Username</label>
                        <input type="text" name="username" id="username" required
                            placeholder="Masukkan username" autocomplete="off"
                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label for="pw">Kata Sandi</label>
                        <div class="pw-wrap">
                            <input type="password" name="password" id="pw" required
                                placeholder="••••••••" autocomplete="new-password">
                            <button type="button" class="pw-toggle" onclick="togglePw()" aria-label="Tampilkan sandi">
                                <svg id="pw-ic" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="row-between">
                        <a href="#">Lupa kata sandi?</a>
                    </div>

                    <button type="submit" class="btn-masuk">Masuk</button>
                </form>

                <div class="divider">atau</div>

                <form method="POST" action="">
                    <button type="submit" name="guest_login" value="1" class="btn-guest">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        Masuk sebagai Tamu
                    </button>
                </form>

                <p class="guest-note">Akun tamu hanya dapat menjelajahi katalog buku.</p>

            </div>
        </div>

    </div>

    <script>
        function togglePw() {
            const pw = document.getElementById('pw');
            const ic = document.getElementById('pw-ic');
            if (pw.type === 'password') {
                pw.type = 'text';
                ic.innerHTML = '<path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.44-4.75-1.73-4.39-6-7.5-11-7.5-1.27 0-2.49.2-3.64.57l2.17 2.17C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/>';
            } else {
                pw.type = 'password';
                ic.innerHTML = '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>';
            }
        }
    </script>

</body>
</html>