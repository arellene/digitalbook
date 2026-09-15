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
                $_POST = []; // Kosongkan data form supaya field kembali kosong setelah berhasil
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
    <title>Daftar Akun — Pojok Baca</title>
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

        .stage-copy { max-width: 420px; }
        .stage-copy h1 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
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

        .stage-checklist {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-top: 32px;
        }
        .stage-checklist li {
            list-style: none;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            color: rgba(255,255,255,.85);
        }
        .stage-checklist .tick {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: rgba(255,255,255,.14);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stage-shelf {
            display: flex;
            gap: 14px;
            margin-top: 36px;
        }
        .shelf-card {
            width: 64px;
            height: 92px;
            border-radius: 8px;
            background: rgba(255,255,255,.1);
            border: 1px solid rgba(255,255,255,.18);
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
            font-size: 12.5px;
            color: rgba(255,255,255,.55);
        }

        /* ───────── RIGHT: form panel ───────── */
        .panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            background: var(--paper);
            overflow-y: auto;
        }
        .panel-inner {
            width: 100%;
            max-width: 420px;
            padding: 36px 0;
        }

        .panel-head { margin-bottom: 26px; }
        .panel-head h2 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 1.8rem;
            letter-spacing: -.01em;
            margin-bottom: 8px;
        }
        .panel-head p { font-size: 14px; color: var(--muted); }
        .panel-head a { color: var(--indigo-700); font-weight: 600; text-decoration: none; }
        .panel-head a:hover { text-decoration: underline; }

        .alert {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            font-size: 13px;
            padding: 11px 14px;
            border-radius: 10px;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }
        .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
        .alert-success a { color: #15803d; font-weight: 700; text-decoration: underline; }

        .section-label {
            font-size: 11.5px;
            font-weight: 700;
            color: var(--indigo-700);
            text-transform: uppercase;
            letter-spacing: .5px;
            margin: 22px 0 14px;
        }
        .section-label:first-of-type { margin-top: 0; }

        .form-row { display: flex; gap: 12px; }
        .form-row .field { flex: 1; }

        .field { margin-bottom: 16px; }
        .field label {
            display: block;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 7px;
        }
        .field label .req { color: #ef4444; }
        .field input,
        .field textarea {
            width: 100%;
            padding: 11px 14px;
            border-radius: 10px;
            border: 1.5px solid var(--line);
            font-family: inherit;
            font-size: 14px;
            color: var(--ink);
            background: var(--canvas);
            transition: border-color .15s, background .15s;
        }
        .field textarea { resize: vertical; min-height: 64px; }
        .field input:focus,
        .field textarea:focus {
            outline: none;
            border-color: var(--indigo-500);
            background: #fff;
        }
        .field .hint { font-size: 11.5px; color: var(--muted); margin-top: 5px; }

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

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 24px;
        }
        .btn-back {
            flex: 0 0 auto;
            padding: 13px 18px;
            border-radius: 10px;
            border: 1.5px solid var(--line);
            background: var(--paper);
            color: var(--ink);
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .btn-back:hover { border-color: var(--indigo-500); background: var(--canvas); }
        .btn-daftar {
            flex: 1;
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
        .btn-daftar:hover { opacity: .92; }
        .btn-daftar:active { transform: scale(.99); }

        .login-link {
            font-size: 13px;
            color: var(--muted);
            text-align: center;
            margin-top: 20px;
        }
        .login-link a { color: var(--indigo-700); font-weight: 600; text-decoration: none; }
        .login-link a:hover { text-decoration: underline; }

        @media (max-width: 880px) {
            .shell { grid-template-columns: 1fr; }
            .stage { display: none; }
            .panel { padding: 28px; }
            .form-row { flex-direction: column; gap: 0; }
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
                <h1>Bergabung, dan mulai jelajahi rak digitalmu.</h1>
                <p>Daftar sekali, akses semua koleksi ebook kapan pun kamu butuh bacaan baru.</p>

                <ul class="stage-checklist">
                    <li><span class="tick">✓</span> Akses ribuan judul eBook</li>
                    <li><span class="tick">✓</span> Simpan progress baca otomatis</li>
                    <li><span class="tick">✓</span> Gratis, tanpa biaya langganan</li>
                </ul>

                <div class="stage-shelf">
                    <div class="shelf-card rise-1"></div>
                    <div class="shelf-card rise-3"></div>
                    <div class="shelf-card rise-2"></div>
                    <div class="shelf-card rise-1"></div>
                    <div class="shelf-card rise-3"></div>
                </div>
            </div>

            <div class="stage-footer">Perpustakaan Digital</div>
        </div>

        <!-- ───────── RIGHT: form ───────── -->
        <div class="panel">
            <div class="panel-inner">

                <div class="panel-head">
                    <h2>Buat akun baru</h2>
                    <p>Sudah punya akun? <a href="../index.php">Masuk di sini</a></p>
                </div>

                <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0;margin-top:1px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                    <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="alert alert-success">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0;margin-top:1px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                    <span><?= htmlspecialchars($success) ?> <a href="../index.php">Login di sini</a></span>
                </div>
                <?php endif; ?>

                <form method="POST" action="">

                    <div class="section-label">Data Pribadi</div>

                    <div class="field">
                        <label>Nama Lengkap <span class="req">*</span></label>
                        <input type="text" name="nama_lengkap" required placeholder="Nama lengkap sesuai identitas"
                            value="<?= htmlspecialchars($_POST['nama_lengkap'] ?? '') ?>">
                    </div>

                    <div class="form-row">
                        <div class="field">
                            <label>Email <span class="req">*</span></label>
                            <input type="email" name="email" required placeholder="email@contoh.com"
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label>No. Telepon</label>
                            <input type="tel" name="no_telepon" placeholder="08xxxxxxxxxx"
                                value="<?= htmlspecialchars($_POST['no_telepon'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="field">
                        <label>Alamat</label>
                        <textarea name="alamat" placeholder="Alamat lengkap"><?= htmlspecialchars($_POST['alamat'] ?? '') ?></textarea>
                    </div>

                    <div class="section-label">Data Akun</div>

                    <div class="field">
                        <label>Username <span class="req">*</span></label>
                        <input type="text" name="username" required placeholder="Pilih username unik"
                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                        <div class="hint">Gunakan huruf, angka, atau underscore.</div>
                    </div>

                    <div class="form-row">
                        <div class="field">
                            <label>Password <span class="req">*</span></label>
                            <div class="pw-wrap">
                                <input type="password" name="password" id="pw1" required placeholder="Min. 6 karakter">
                                <button type="button" class="pw-toggle" onclick="togglePw('pw1','ic1')" aria-label="Tampilkan password">
                                    <svg id="ic1" width="17" height="17" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="field">
                            <label>Konfirmasi Password <span class="req">*</span></label>
                            <div class="pw-wrap">
                                <input type="password" name="confirm_password" id="pw2" required placeholder="Ulangi password">
                                <button type="button" class="pw-toggle" onclick="togglePw('pw2','ic2')" aria-label="Tampilkan konfirmasi">
                                    <svg id="ic2" width="17" height="17" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="btn-group">
                        <a href="../index.php" class="btn-back">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M14 7l-5 5 5 5V7z"/></svg>
                            Kembali
                        </a>
                        <button type="submit" class="btn-daftar">Daftar Sekarang</button>
                    </div>

                </form>

                <div class="login-link">
                    Sudah punya akun? <a href="../index.php">Login di sini</a>
                </div>

            </div>
        </div>

    </div>

    <script>
        function togglePw(id, iconId) {
            const input = document.getElementById(id);
            const icon  = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.innerHTML = '<path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.44-4.75-1.73-4.39-6-7.5-11-7.5-1.27 0-2.49.2-3.64.57l2.17 2.17C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/>';
            } else {
                input.type = 'password';
                icon.innerHTML = '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>';
            }
        }
    </script>

</body>
</html>