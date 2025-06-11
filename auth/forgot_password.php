<?php
session_start();
include '../config/db.php';

// Handle restart request FIRST - before any other logic
if (isset($_GET['restart'])) {
    // Clear all reset-related session data
    unset($_SESSION['reset_user_id']);
    unset($_SESSION['reset_email']);
    unset($_SESSION['reset_token']);
    // Redirect to clean URL without restart parameter
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$step = 1;
$error = "";
$success = "";
$email = "";

// STEP 1: Cek email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";
    } else {
        $stmt = $conn->prepare("SELECT id, email FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            // Simpan data user di session untuk keamanan
            $_SESSION['reset_user_id'] = $user['id'];
            $_SESSION['reset_email'] = $user['email'];
            $_SESSION['reset_token'] = bin2hex(random_bytes(32)); // Token keamanan
            $step = 2;
        } else {
            $error = "Email tidak ditemukan.";
        }
    }
}

// STEP 2: Reset password dengan validasi keamanan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    // Validasi session
    if (!isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_token'])) {
        $error = "Sesi tidak valid. Silakan mulai dari awal.";
        $step = 1;
        // Clear any remaining session data
        unset($_SESSION['reset_user_id']);
        unset($_SESSION['reset_email']);
        unset($_SESSION['reset_token']);
    } else {
        $user_id = $_SESSION['reset_user_id'];
        $new_password = trim($_POST['new_password']);
        $confirm_password = trim($_POST['confirm_password']);

        // Validasi password
        if (empty($new_password)) {
            $error = "Password tidak boleh kosong.";
            $step = 2;
        } elseif (strlen($new_password) < 6) {
            $error = "Password minimal 6 karakter.";
            $step = 2;
        } elseif ($new_password !== $confirm_password) {
            $error = "Konfirmasi password tidak cocok.";
            $step = 2;
        } else {
            // Simpan password tanpa hashing (TIDAK AMAN!)
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $new_password, $user_id);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $success = "Password berhasil diubah. <a href='login.php'>Login sekarang</a>";
                $step = 3;
                
                // Bersihkan session setelah berhasil
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_token']);
            } else {
                $error = "Gagal mengubah password. Silakan coba lagi.";
                $step = 2;
            }
        }
    }
}

// Jika user refresh halaman atau kembali, cek session
if (isset($_SESSION['reset_user_id']) && $step == 1) {
    $step = 2;
    $email = $_SESSION['reset_email']; // Restore email for display
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Lupa Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            --warning-gradient: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            --danger-gradient: linear-gradient(135deg, #ff758c 0%, #ff7eb3 100%);
            --card-shadow: 0 15px 35px rgba(0,0,0,0.1);
            --border-radius: 20px;
            --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--primary-gradient);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            padding: 20px;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            z-index: -1;
        }

        .container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            padding: 0;
            max-width: 450px;
            width: 100%;
            overflow: hidden;
            position: relative;
        }

        .container::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
            pointer-events: none;
        }

        @keyframes float {
            0%, 100% { transform: translate(-50%, -50%) rotate(0deg); }
            50% { transform: translate(-50%, -50%) rotate(180deg); }
        }

        .header {
            background: var(--primary-gradient);
            color: white;
            padding: 2.5rem 2rem;
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .header h2 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .header i {
            margin-right: 0.75rem;
            font-size: 1.8rem;
        }

        .content {
            padding: 2.5rem;
            position: relative;
            z-index: 2;
        }

        .alert-modern {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f1aeb5 100%);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .alert-modern i {
            margin-right: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            color: #495057;
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
        }

        .form-label i {
            margin-right: 0.5rem;
            color: #667eea;
            width: 16px;
        }

        .form-control {
            width: 100%;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1rem;
            font-size: 1rem;
            transition: var(--transition);
            background: #f8f9fa;
            font-family: 'Inter', sans-serif;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            background: white;
        }

        .btn-primary-modern {
            background: var(--primary-gradient);
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-weight: 600;
            font-size: 1rem;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            color: white;
            width: 100%;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-primary-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.5s;
        }

        .btn-primary-modern:hover::before {
            left: 100%;
        }

        .btn-primary-modern i {
            margin-right: 0.5rem;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }

        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 0.5rem;
            font-weight: 600;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .step.active {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .step.inactive {
            background: #e9ecef;
            color: #6c757d;
        }

        .step.completed {
            background: var(--success-gradient);
            color: #2c3e50;
        }

        .success-message {
            text-align: center;
            padding: 2rem;
        }

        .success-message i {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 1rem;
            display: block;
        }

        .success-message h3 {
            color: #2c3e50;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .success-message a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }

        .success-message a:hover {
            color: #764ba2;
        }

        .password-requirements {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.5rem;
        }

        .restart-link {
            text-align: center;
            margin-top: 1rem;
        }

        .restart-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .restart-link a:hover {
            text-decoration: underline;
            color: #764ba2;
        }

        @media (max-width: 480px) {
            .container {
                margin: 1rem;
            }
            
            .header {
                padding: 2rem 1.5rem;
            }
            
            .header h2 {
                font-size: 1.5rem;
            }
            
            .content {
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>
                <i class="fas fa-key"></i>
                Lupa Password
            </h2>
        </div>

        <div class="content">
            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step <?= $step >= 1 ? ($step == 1 ? 'active' : 'completed') : 'inactive' ?>">1</div>
                <div class="step <?= $step >= 2 ? ($step == 2 ? 'active' : 'completed') : 'inactive' ?>">2</div>
                <div class="step <?= $step == 3 ? 'active' : 'inactive' ?>">3</div>
            </div>

            <?php if ($error): ?>
                <div class="alert-modern alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label" for="email">
                            <i class="fas fa-envelope"></i>
                            Masukkan Email Anda
                        </label>
                        <input type="email" name="email" id="email" class="form-control" 
                               placeholder="contoh@email.com" value="<?= htmlspecialchars($email) ?>" required>
                    </div>
                    <button type="submit" class="btn-primary-modern">
                        <i class="fas fa-arrow-right"></i>
                        Lanjutkan
                    </button>
                </form>

            <?php elseif ($step === 2): ?>
                <div class="alert-modern alert-success">
                    <i class="fas fa-info-circle"></i>
                    Email ditemukan: <?= htmlspecialchars($_SESSION['reset_email'] ?? '') ?>
                </div>
                
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label" for="new_password">
                            <i class="fas fa-lock"></i>
                            Password Baru
                        </label>
                        <input type="password" name="new_password" id="new_password" 
                               class="form-control" placeholder="Masukkan password baru" required minlength="6">
                        <div class="password-requirements">
                            Password minimal 6 karakter
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirm_password">
                            <i class="fas fa-lock"></i>
                            Konfirmasi Password
                        </label>
                        <input type="password" name="confirm_password" id="confirm_password" 
                               class="form-control" placeholder="Ulangi password baru" required minlength="6">
                    </div>

                    <button type="submit" class="btn-primary-modern">
                        <i class="fas fa-check"></i>
                        Reset Password
                    </button>
                </form>

                <div class="restart-link">
                    <a href="?restart=1">
                        <i class="fas fa-redo"></i> Mulai dari awal
                    </a>
                </div>

            <?php elseif ($step === 3): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <h3>Password Berhasil Diubah!</h3>
                    <p><?= $success ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Validasi password match real-time
        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (newPassword && confirmPassword) {
                confirmPassword.addEventListener('input', function() {
                    if (newPassword.value !== confirmPassword.value) {
                        confirmPassword.setCustomValidity('Password tidak cocok');
                    } else {
                        confirmPassword.setCustomValidity('');
                    }
                });
            }
        });
    </script>
</body>
</html>