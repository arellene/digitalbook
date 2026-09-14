<?php
session_start();
require_once '../config/database.php';

if (empty($_SESSION['reset_email']) || empty($_SESSION['reset_verified'])) {
    header('Location: forgot_password.php');
    exit();
}

$email = $_SESSION['reset_email'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw1 = $_POST['password'] ?? '';
    $pw2 = $_POST['confirm_password'] ?? '';

    if (strlen($pw1) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($pw1 !== $pw2) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $emailEsc = mysqli_real_escape_string($conn, $email);
        $hashed   = password_hash($pw1, PASSWORD_DEFAULT);

        mysqli_query($conn, "UPDATE users SET password = '$hashed' WHERE email = '$emailEsc'");
        mysqli_query($conn, "DELETE FROM password_reset_otp WHERE email = '$emailEsc'");

        unset($_SESSION['reset_email'], $_SESSION['reset_verified'], $_SESSION['dev_otp_preview']);
        $_SESSION['flash_login'] = 'Password berhasil direset. Silakan login dengan password baru.';

        header('Location: ../index.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Password Baru — Pojok Baca</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --indigo-900: #1e1b4b; --indigo-700: #4338ca; --indigo-500: #6366f1;
            --violet-500: #8b5cf6; --ink: #0f172a; --muted: #64748b;
            --line: #e2e8f0; --paper: #ffffff;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            background:
                radial-gradient(120% 140% at 15% 0%, #7c3aed 0%, transparent 55%),
                radial-gradient(120% 120% at 85% 100%, #4338ca 0%, transparent 60%),
                linear-gradient(160deg, var(--indigo-900) 0%, var(--indigo-700) 55%, var(--violet-500) 130%);
            padding: 20px;
        }
        .card {
            background: var(--paper); border-radius: 22px; padding: 40px 36px;
            width: 100%; max-width: 400px;
            box-shadow: 0 24px 70px rgba(30, 27, 75, .35);
            position: relative; overflow: hidden;
        }
        .card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
            background: linear-gradient(90deg, var(--indigo-700), var(--violet-500));
        }
        .icon-wrap {
            width: 60px; height: 60px; border-radius: 16px;
            background: rgba(99,102,241,.1); color: var(--indigo-700);
            display: flex; align-items: center; justify-content: center; margin-bottom: 20px;
        }
        h2 { font-weight: 600; font-size: 21px; color: var(--ink); margin-bottom: 8px; }
        p.desc { font-size: 13.5px; color: var(--muted); line-height: 1.6; margin-bottom: 22px; }

        .alert {
            display: flex; align-items: flex-start; gap: 9px;
            font-size: 13px; padding: 11px 14px; border-radius: 10px;
            margin-bottom: 18px; line-height: 1.5;
        }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }

        .field { margin-bottom: 16px; }
        .field label { display: block; font-size: 12.5px; font-weight: 600; color: var(--ink); margin-bottom: 7px; }
        .pw-wrap { position: relative; }
        .pw-wrap input {
            width: 100%; padding: 12px 44px 12px 14px; border-radius: 10px;
            border: 1.5px solid var(--line); font-family: inherit; font-size: 14.5px;
            color: var(--ink); background: #f8fafc;
        }
        .pw-wrap input:focus { outline: none; border-color: var(--indigo-500); background: #fff; }
        .pw-toggle {
            position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
            background: none; border: none; width: 34px; height: 34px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; color: var(--muted);
        }
        .pw-toggle:hover { background: #f8fafc; color: var(--ink); }
        .hint { font-size: 11.5px; color: var(--muted); margin-top: 6px; }

        .btn-submit {
            width: 100%; padding: 13px; border-radius: 10px; border: none;
            background: linear-gradient(135deg, var(--indigo-700), var(--violet-500));
            color: #fff; font-family: inherit; font-size: 14.5px; font-weight: 700;
            cursor: pointer; margin-top: 8px;
        }
        .btn-submit:hover { opacity: .92; }
    </style>
</head>
<body>

    <div class="card">
        <div class="icon-wrap">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1C8.676 1 6 3.676 6 7h2c0-2.206 1.794-4 4-4s4 1.794 4 4v3H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V12c0-1.1-.9-2-2-2h-2V7c0-3.324-2.676-6-6-6zm0 13c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2z"/></svg>
        </div>

        <h2>Buat Password Baru</h2>
        <p class="desc">Verifikasi berhasil! Sekarang buat password baru untuk akun <strong><?= htmlspecialchars($email) ?></strong>.</p>

        <?php if ($error): ?>
        <div class="alert alert-error">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0;margin-top:1px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="field">
                <label>Password Baru</label>
                <div class="pw-wrap">
                    <input type="password" name="password" id="pw1" required placeholder="Min. 6 karakter">
                    <button type="button" class="pw-toggle" onclick="togglePw('pw1','ic1')">
                        <svg id="ic1" width="17" height="17" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                    </button>
                </div>
            </div>
            <div class="field">
                <label>Konfirmasi Password</label>
                <div class="pw-wrap">
                    <input type="password" name="confirm_password" id="pw2" required placeholder="Ulangi password">
                    <button type="button" class="pw-toggle" onclick="togglePw('pw2','ic2')">
                        <svg id="ic2" width="17" height="17" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                    </button>
                </div>
                <div class="hint">Gunakan kombinasi yang belum pernah dipakai sebelumnya.</div>
            </div>

            <button type="submit" class="btn-submit">Simpan Password Baru</button>
        </form>
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