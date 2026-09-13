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
    <title>Logout - Digital Book</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0d0d2b;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        @keyframes fadeIn { from { opacity:0 } to { opacity:1 } }
        @keyframes popIn  { from { opacity:0; transform:scale(.85) } to { opacity:1; transform:scale(1) } }
        @keyframes progress { from { width:100% } to { width:0% } }

        .overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.55);
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn .3s ease;
        }
        .card {
            background: #1a1a4e;
            border-radius: 20px;
            padding: 40px 36px 32px;
            text-align: center;
            width: 100%;
            max-width: 360px;
            box-shadow: 0 16px 60px rgba(0,0,0,0.5);
            animation: popIn .35s cubic-bezier(.34,1.56,.64,1);
            position: relative;
            overflow: hidden;
        }
        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, #E84393, #FF6B35, #F5C842);
        }
        .progress {
            position: absolute;
            bottom: 0; left: 0;
            height: 3px;
            background: #E84393;
            width: 100%;
            animation: progress 2s linear forwards;
        }
        .deco { position: absolute; border-radius: 50%; opacity: .15; }
        .d1 { width:80px; height:80px; background:#E84393; top:-20px; right:-20px; }
        .d2 { width:60px; height:60px; background:#F5C842; bottom:-15px; left:-15px; }

        .icon-wrap {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
        }
        .icon-warn { background: rgba(245,200,66,.15); border: 2px solid #F5C842; }
        .icon-warn i { font-size: 28px; color: #F5C842; }
        .icon-success { background: rgba(232,67,147,.15); border: 2px solid #E84393; }
        .icon-success i { font-size: 28px; color: #E84393; }

        h2 { color: #fff; font-size: 20px; font-weight: 800; margin-bottom: 8px; }
        h2 span { color: #F5C842; }
        h2 span.pink { color: #E84393; }

        p { color: #8888bb; font-size: 13.5px; line-height: 1.6; margin-bottom: 24px; }

        .btn-group { display: flex; gap: 10px; justify-content: center; }

        .btn {
            padding: 11px 28px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 700;
            letter-spacing: .6px;
            cursor: pointer;
            text-decoration: none;
            transition: background .2s;
            font-family: inherit;
            border: none;
        }
        .btn-cancel {
            background: transparent;
            border: 1.5px solid #3a3a8a;
            color: #8888bb;
        }
        .btn-cancel:hover { border-color: #F5C842; color: #F5C842; }
        .btn-confirm { background: #E84393; color: #fff; }
        .btn-confirm:hover { background: #d13080; }
        .btn-login { background: #E84393; color: #fff; display: inline-block; }
        .btn-login:hover { background: #d13080; }
    </style>
</head>
<body>
    <div class="overlay">
        <div class="card">
            <div class="deco d1"></div>
            <div class="deco d2"></div>

            <?php if (!$doLogout): ?>
                <!-- Konfirmasi -->
                <div class="icon-wrap icon-warn">
                    <i class="fas fa-question"></i>
                </div>
                <h2>Yakin mau <span>keluar?</span></h2>
                <p>Kamu akan keluar dari sesi<br><strong style="color:#fff;">Digital Book</strong>.<br>Sampai jumpa lagi ya!</p>
                <div class="btn-group">
                    <a href="javascript:history.back()" class="btn btn-cancel">
                        <i class="fas fa-times"></i> Batal
                    </a>
                    <a href="logout.php?confirm=1" class="btn btn-confirm">
                        <i class="fas fa-sign-out-alt"></i> Ya, Keluar
                    </a>
                </div>

            <?php else: ?>
                <!-- Sukses logout -->
                <div class="icon-wrap icon-success">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <h2>Sampai Jumpa, <span class="pink">Goodbye! 👋</span></h2>
                <p>Kamu telah berhasil keluar dari<br><strong style="color:#fff;">Digital Book</strong>.<br>Sampai jumpa lagi!</p>
                <a href="../index.php" class="btn btn-login">Kembali ke Login</a>
                <div class="progress"></div>

                <script>
                    setTimeout(() => { window.location = '../index.php'; }, 2000);
                </script>
            <?php endif; ?>

        </div>
    </div>
</body>
</html>