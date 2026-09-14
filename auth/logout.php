<?php
session_start();
$doLogout = isset($_GET['confirm']) && $_GET['confirm'] == '1';
if ($doLogout) {
    session_destroy();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Logout — Pojok Baca</title>
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

        @keyframes popIn { from { opacity: 0; transform: scale(.92); } to { opacity: 1; transform: scale(1); } }
        @keyframes progress { from { width: 100%; } to { width: 0%; } }

        .card {
            background: var(--paper);
            border-radius: 22px;
            padding: 40px 36px 32px;
            text-align: center;
            width: 100%;
            max-width: 380px;
            box-shadow: 0 24px 70px rgba(30, 27, 75, .35);
            animation: popIn .35s cubic-bezier(.34,1.56,.64,1);
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
        .progress {
            position: absolute;
            bottom: 0; left: 0;
            height: 3px;
            background: var(--indigo-500);
            width: 100%;
            animation: progress 2s linear forwards;
        }

        .icon-wrap {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .icon-warn { background: rgba(99,102,241,.1); border: 2px solid var(--indigo-500); color: var(--indigo-700); }
        .icon-success { background: rgba(139,92,246,.1); border: 2px solid var(--violet-500); color: var(--violet-500); }

        h2 {
            color: var(--ink);
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 21px;
            margin-bottom: 10px;
        }
        h2 span { color: var(--indigo-700); }

        p {
            color: var(--muted);
            font-size: 13.5px;
            line-height: 1.65;
            margin-bottom: 26px;
        }
        p strong { color: var(--ink); }

        .btn-group { display: flex; gap: 10px; justify-content: center; }

        .btn {
            padding: 11px 26px;
            border-radius: 10px;
            font-family: inherit;
            font-size: 13.5px;
            font-weight: 600;
            letter-spacing: .2px;
            cursor: pointer;
            text-decoration: none;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }
        .btn-cancel {
            background: var(--paper);
            border: 1.5px solid var(--line);
            color: var(--muted);
        }
        .btn-cancel:hover { border-color: var(--indigo-500); color: var(--indigo-700); }
        .btn-confirm {
            background: linear-gradient(135deg, var(--indigo-700), var(--violet-500));
            color: #fff;
        }
        .btn-confirm:hover { opacity: .92; }
        .btn-login {
            background: linear-gradient(135deg, var(--indigo-700), var(--violet-500));
            color: #fff;
            display: inline-flex;
        }
        .btn-login:hover { opacity: .92; }
    </style>
</head>
<body>

    <div class="card">

        <?php if (!$doLogout): ?>
            <!-- Konfirmasi -->
            <div class="icon-wrap icon-warn">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor"><path d="M11 18h2v-2h-2v2zm1-16C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm0-14c-2.21 0-4 1.79-4 4h2c0-1.1.9-2 2-2s2 .9 2 2c0 2-3 1.75-3 5h2c0-2.25 3-2.5 3-5 0-2.21-1.79-4-4-4z"/></svg>
            </div>
            <h2>Yakin mau <span>keluar?</span></h2>
            <p>Kamu akan keluar dari sesi<br><strong>Pojok Baca</strong>.<br>Sampai jumpa lagi ya!</p>
            <div class="btn-group">
                <a href="javascript:history.back()" class="btn btn-cancel">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                    Batal
                </a>
                <a href="logout.php?confirm=1" class="btn btn-confirm">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
                    Ya, Keluar
                </a>
            </div>

        <?php else: ?>
            <!-- Sukses logout -->
            <div class="icon-wrap icon-success">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
            </div>
            <h2>Sampai Jumpa, <span>Goodbye! 👋</span></h2>
            <p>Kamu telah berhasil keluar dari<br><strong>Pojok Baca</strong>.<br>Sampai jumpa lagi!</p>
            <a href="../index.php" class="btn btn-login">Kembali ke Login</a>
            <div class="progress"></div>

            <script>
                setTimeout(() => { window.location = '../index.php'; }, 2000);
            </script>
        <?php endif; ?>

    </div>
</body>
</html>