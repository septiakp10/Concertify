<?php
session_start();
session_unset();
session_destroy();

// Optional: Delay redirect for user to see the message
header("refresh:3;url=login.php");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Logout - KonserApp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(to right, #4e54c8, #8f94fb);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', sans-serif;
            color: white;
        }
        .logout-box {
            background: rgba(255, 255, 255, 0.1);
            padding: 40px;
            border-radius: 15px;
            text-align: center;
            backdrop-filter: blur(10px);
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
        }
        .logout-box i {
            font-size: 4rem;
            color: #ffc107;
            margin-bottom: 20px;
        }
        .logout-box h1 {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        .logout-box p {
            font-size: 1rem;
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="logout-box">
        <i class="bi bi-box-arrow-right"></i>
        <h1>Berhasil Logout</h1>
        <p>Kamu akan diarahkan kembali ke halaman login...</p>
    </div>
</body>
</html>
