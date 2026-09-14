<?php
session_start();
require_once '../config/database.php';
require_once '../config/mailer.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));

    if ($email === '') {
        $error = 'Email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } else {
        $check = mysqli_query($conn, "SELECT id, nama_lengkap FROM users WHERE email = '$email' LIMIT 1");
        if (!$check || mysqli_num_rows($check) === 0) {
            $error = 'Email tidak terdaftar di sistem kami.';
        } else {
            $userRow = mysqli_fetch_assoc($check);
            $otp     = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            // Hapus OTP lama untuk email ini, lalu buat yang baru
            mysqli_query($conn, "DELETE FROM password_reset_otp WHERE email = '$email'");
            mysqli_query($conn, "
                INSERT INTO password_reset_otp (email, otp_code, expires_at)
                VALUES ('$email', '$otp', '$expires')
            ");

            $namaDepan = htmlspecialchars(explode(' ', $userRow['nama_lengkap'])[0]);
            $subject = 'Kode OTP Reset Password - Pojok Baca';
            $message = "Halo $namaDepan,\n\n"
                     . "Kami menerima permintaan reset password untuk akun Pojok Baca kamu.\n\n"
                     . "Kode OTP kamu: $otp\n"
                     . "Kode ini berlaku selama 10 menit.\n\n"
                     . "Jika kamu tidak meminta reset password, abaikan email ini.\n\n"
                     . "— Tim Pojok Baca";
            $terkirim = kirimEmailOtp($email, $userRow['nama_lengkap'], $subject, $message);

            $_SESSION['reset_email'] = $email;
            unset($_SESSION['reset_verified'], $_SESSION['dev_otp_preview']);

            // Fallback pengembangan: kalau server belum dikonfigurasi SMTP,
            // tampilkan kode OTP langsung di halaman verifikasi supaya tetap bisa diuji.
            if (!$terkirim) {
                $_SESSION['dev_otp_preview'] = $otp;
            }

            header('Location: verify_otp.php');
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password — Pojok Baca</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            background:
                radial-gradient(120% 140% at 15% 0%, #7c3aed 0%, transparent 55%),
                radial-gradient(120% 120% at 85% 100%, #4338ca 0%, transparent 60%),
                linear-gradient(160deg, var(--indigo-900) 0%, var(--indigo-700) 55%, var(--violet-500) 130%);
            padding: 20px;
        }
        .card {
            background: var(--paper);
            border-radius: 22px;
            padding: 40px 36px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 24px 70px rgba(30, 27, 75, .35);
            position: relative;
            overflow: hidden;
        }
        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--indigo-700), var(--violet-500));
        }
        .icon-wrap {
            width: 60px; height: 60px;
            border-radius: 16px;
            background: rgba(99,102,241,.1);
            color: var(--indigo-700);
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 20px;
        }
        h2 { font-weight: 600; font-size: 21px; color: var(--ink); margin-bottom: 8px; }
        p.desc { font-size: 13.5px; color: var(--muted); line-height: 1.6; margin-bottom: 24px; }

        .alert {
            display: flex; align-items: flex-start; gap: 9px;
            font-size: 13px; padding: 11px 14px; border-radius: 10px;
            margin-bottom: 18px; line-height: 1.5;
        }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }

        .field { margin-bottom: 18px; }
        .field label { display: block; font-size: 12.5px; font-weight: 600; color: var(--ink); margin-bottom: 7px; }
        .field input {
            width: 100%; padding: 12px 14px; border-radius: 10px;
            border: 1.5px solid var(--line); font-family: inherit; font-size: 14.5px;
            color: var(--ink); background: #f8fafc;
        }
        .field input:focus { outline: none; border-color: var(--indigo-500); background: #fff; }

        .btn-submit {
            width: 100%; padding: 13px; border-radius: 10px; border: none;
            background: linear-gradient(135deg, var(--indigo-700), var(--violet-500));
            color: #fff; font-family: inherit; font-size: 14.5px; font-weight: 700;
            cursor: pointer;
        }
        .btn-submit:hover { opacity: .92; }

        .back-link {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 13px; color: var(--muted); text-decoration: none;
            margin-top: 20px;
        }
        .back-link:hover { color: var(--indigo-700); }
    </style>
</head>
<body>

    <div class="card">
        <div class="icon-wrap">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
        </div>

        <h2>Lupa Kata Sandi?</h2>
        <p class="desc">Masukkan email yang terdaftar. Kami akan mengirimkan kode OTP 6 digit untuk verifikasi reset password.</p>

        <?php if ($error): ?>
        <div class="alert alert-error">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0;margin-top:1px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="field">
                <label>Email Terdaftar</label>
                <input type="email" name="email" required placeholder="email@contoh.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <button type="submit" class="btn-submit">Kirim Kode OTP</button>
        </form>

        <a href="../index.php" class="back-link">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M14 7l-5 5 5 5V7z"/></svg>
            Kembali ke Login
        </a>
    </div>

</body>
</html>