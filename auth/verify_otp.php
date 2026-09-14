<?php
session_start();
require_once '../config/database.php';
require_once '../config/mailer.php';

if (empty($_SESSION['reset_email'])) {
    header('Location: forgot_password.php');
    exit();
}

$email = $_SESSION['reset_email'];
$emailEsc = mysqli_real_escape_string($conn, $email);
$error = '';
$info  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['kirim_ulang'])) {
        // ── Kirim ulang kode OTP baru ──
        $otp     = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        mysqli_query($conn, "DELETE FROM password_reset_otp WHERE email = '$emailEsc'");
        mysqli_query($conn, "
            INSERT INTO password_reset_otp (email, otp_code, expires_at)
            VALUES ('$emailEsc', '$otp', '$expires')
        ");

        $subject = 'Kode OTP Baru - Pojok Baca';
        $message = "Kode OTP baru kamu: $otp\nBerlaku selama 10 menit.\n\n— Tim Pojok Baca";
        $terkirim = kirimEmailOtp($email, '', $subject, $message);

        if (!$terkirim) {
            $_SESSION['dev_otp_preview'] = $otp;
        } else {
            unset($_SESSION['dev_otp_preview']);
        }

        $info = 'Kode OTP baru telah dikirim ke email kamu.';

    } else {
        // ── Verifikasi kode OTP yang dimasukkan ──
        $otpInput = mysqli_real_escape_string($conn, trim($_POST['otp'] ?? ''));

        $r = mysqli_query($conn, "
            SELECT * FROM password_reset_otp
            WHERE email = '$emailEsc' AND otp_code = '$otpInput'
            LIMIT 1
        ");

        if (!$r || mysqli_num_rows($r) === 0) {
            $error = 'Kode OTP yang kamu masukkan salah.';
        } else {
            $row = mysqli_fetch_assoc($r);
            if (strtotime($row['expires_at']) < time()) {
                $error = 'Kode OTP sudah kedaluwarsa. Silakan kirim ulang kode.';
            } else {
                mysqli_query($conn, "UPDATE password_reset_otp SET verified = 1 WHERE id = " . (int) $row['id']);
                $_SESSION['reset_verified'] = true;
                header('Location: reset_password.php');
                exit();
            }
        }
    }
}

$devOtp = $_SESSION['dev_otp_preview'] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi OTP — Pojok Baca</title>
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
        p.desc { font-size: 13.5px; color: var(--muted); line-height: 1.6; margin-bottom: 8px; }
        p.desc strong { color: var(--ink); }

        .alert {
            display: flex; align-items: flex-start; gap: 9px;
            font-size: 13px; padding: 11px 14px; border-radius: 10px;
            margin: 16px 0; line-height: 1.5;
        }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }
        .alert-info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; }
        .alert-dev {
            background: #fffbeb; border: 1px dashed #fcd34d; color: #92400e;
            font-size: 12.5px; margin-top: 16px;
        }
        .alert-dev b { font-size: 20px; letter-spacing: 3px; display: block; margin-top: 4px; }

        .otp-input {
            width: 100%; padding: 14px; border-radius: 10px;
            border: 1.5px solid var(--line); font-family: inherit;
            font-size: 22px; font-weight: 700; letter-spacing: 8px; text-align: center;
            color: var(--ink); background: #f8fafc; margin: 20px 0 18px;
        }
        .otp-input:focus { outline: none; border-color: var(--indigo-500); background: #fff; }

        .btn-submit {
            width: 100%; padding: 13px; border-radius: 10px; border: none;
            background: linear-gradient(135deg, var(--indigo-700), var(--violet-500));
            color: #fff; font-family: inherit; font-size: 14.5px; font-weight: 700; cursor: pointer;
        }
        .btn-submit:hover { opacity: .92; }

        .resend-row {
            display: flex; justify-content: center; margin-top: 16px;
        }
        .btn-resend {
            background: none; border: none; color: var(--indigo-700);
            font-family: inherit; font-size: 13px; font-weight: 600; cursor: pointer;
        }
        .btn-resend:hover { text-decoration: underline; }

        .back-link {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 13px; color: var(--muted); text-decoration: none; margin-top: 18px;
        }
        .back-link:hover { color: var(--indigo-700); }
    </style>
</head>
<body>

    <div class="card">
        <div class="icon-wrap">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
        </div>

        <h2>Masukkan Kode OTP</h2>
        <p class="desc">Kode 6 digit telah dikirim ke <strong><?= htmlspecialchars($email) ?></strong>. Berlaku selama 10 menit.</p>

        <?php if ($error): ?>
        <div class="alert alert-error">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0;margin-top:1px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if ($info): ?>
        <div class="alert alert-info">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0;margin-top:1px;"><path d="M11 9h2V7h-2v2zm0 10h2v-6h-2v6zm1-18C6.48 1 2 5.48 2 11s4.48 10 10 10 10-4.48 10-10S17.52 1 12 1z"/></svg>
            <?= htmlspecialchars($info) ?>
        </div>
        <?php endif; ?>

        <?php if ($devOtp): ?>
        <div class="alert alert-dev">
            ⚠️ Email server belum dikonfigurasi di localhost ini. Untuk keperluan testing, ini kode OTP kamu:
            <b><?= htmlspecialchars($devOtp) ?></b>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="text" name="otp" class="otp-input" maxlength="6" inputmode="numeric"
                pattern="[0-9]{6}" placeholder="000000" required autofocus>
            <button type="submit" class="btn-submit">Verifikasi Kode</button>
        </form>

        <form method="POST" action="">
            <input type="hidden" name="kirim_ulang" value="1">
            <div class="resend-row">
                <button type="submit" class="btn-resend">Belum dapat kode? Kirim ulang</button>
            </div>
        </form>

        <a href="forgot_password.php" class="back-link">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M14 7l-5 5 5 5V7z"/></svg>
            Ganti Email
        </a>
    </div>

</body>
</html>