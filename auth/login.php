<?php 
session_start(); 
include '../config/db.php';  
$error = "";  
$success = false;
$redirect_url = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {     
    $email = $_POST['email'];     
    $password = $_POST['password'];      

    // Login bisa pakai email atau username
    $stmt = $conn->prepare("SELECT id, username, email, password, is_admin FROM users WHERE email = ? OR username = ?");     
    $stmt->bind_param("ss", $email, $email);     
    $stmt->execute();     
    $result = $stmt->get_result();      

    if ($result->num_rows === 1) {         
        $user = $result->fetch_assoc();          

        // Bandingkan password biasa (tidak hash)
        if ($password === $user['password']) {             
            $_SESSION['user_id']  = $user['id'];             
            $_SESSION['is_admin'] = $user['is_admin'];

            $success = true;
            // Set redirect URL sesuai peran
            if ($user['is_admin'] == 1) {
                $redirect_url = "../admin/dashboard.php";
            } else {
                $redirect_url = "../index.php";
            }
        } else {             
            $error = "Password salah!";         
        }     
    } else {         
        $error = "Email atau Username tidak ditemukan!";     
    } 
} 
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TiketKonser</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            position: relative;
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

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 48px 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.15), 
                        0 0 0 1px rgba(255, 255, 255, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .brand {
            text-align: center;
            margin-bottom: 8px;
        }

        .brand-icon {
            font-size: 32px;
            margin-bottom: 8px;
            display: block;
        }

        .brand-text {
            font-size: 20px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .welcome-text {
            text-align: center;
            color: #64748b;
            font-size: 15px;
            font-weight: 400;
            margin-bottom: 32px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
            font-size: 14px;
            letter-spacing: 0.025em;
        }

        .input-wrapper {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            background-color: #fafbfc;
            font-size: 16px;
            font-weight: 400;
            color: #374151;
            transition: all 0.3s ease;
            outline: none;
        }

        .form-input:focus {
            border-color: #667eea;
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            transform: translateY(-1px);
        }

        .form-input::placeholder {
            color: #9ca3af;
            font-weight: 400;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 16px;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            cursor: pointer;
            fill: #9ca3af;
            transition: fill 0.2s ease;
        }

        .password-toggle:hover {
            fill: #667eea;
        }

        .login-button {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            letter-spacing: 0.025em;
            margin-bottom: 24px;
        }

        .login-button:hover {
            background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .divider {
            text-align: center;
            margin: 24px 0;
            position: relative;
            color: #9ca3af;
            font-size: 14px;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e5e7eb;
            z-index: 1;
        }

        .divider span {
            background: rgba(255, 255, 255, 0.95);
            padding: 0 16px;
            position: relative;
            z-index: 2;
        }

        .form-links {
            text-align: center;
        }

        .form-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s ease;
            display: inline-block;
            margin: 8px 0;
        }

        .form-link:hover {
            color: #764ba2;
            text-decoration: underline;
            transform: translateY(-1px);
        }

        .register-text {
            color: #64748b;
            font-size: 14px;
            margin-top: 16px;
        }

        .error-message {
            background: linear-gradient(135deg, #ff758c, #ff7eb3);
            color: white;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            text-align: center;
            font-weight: 500;
            border: 1px solid rgba(255, 117, 140, 0.3);
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .success-message {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            color: #2c3e50;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            text-align: center;
            font-weight: 500;
            border: 1px solid rgba(168, 237, 234, 0.5);
        }

        /* Responsive Design */
        @media (max-width: 480px) {
            .login-container {
                padding: 32px 24px;
                margin: 16px;
            }
            
            .brand-icon {
                font-size: 28px;
            }
            
            .brand-text {
                font-size: 18px;
            }
        }

        /* Loading state */
        .login-button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        /* Input animation */
        .form-input:not(:placeholder-shown) + .form-label {
            color: #667eea;
        }

        /* Subtle animations */
        .login-container {
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Custom SweetAlert styling */
        .swal2-popup {
            font-family: 'Outfit', sans-serif !important;
            border-radius: 20px !important;
        }

        .swal2-title {
            font-weight: 600 !important;
        }

        .swal2-confirm {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            border-radius: 12px !important;
            font-weight: 500 !important;
            padding: 12px 24px !important;
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="brand">
        <span class="brand-icon">🎫</span>
        <div class="brand-text">TiketKonser</div>
    </div>
    
    <p class="welcome-text">Selamat datang kembali! Silakan masuk ke akun Anda.</p>

    <?php if ($error): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="loginForm">
        <div class="form-group">
            <label for="email" class="form-label">Email atau Username</label>
            <div class="input-wrapper">
                <input type="text" 
                       id="email" 
                       name="email" 
                       class="form-input"
                       placeholder="Masukkan email atau username Anda" 
                       required
                       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="password" class="form-label">Kata Sandi</label>
            <div class="input-wrapper">
                <input type="password" 
                       id="password" 
                       name="password" 
                       class="form-input"
                       placeholder="Masukkan kata sandi Anda" 
                       required>
                <svg id="togglePassword" class="password-toggle" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path id="eyeIcon" d="M12 5c-7.633 0-11 7-11 7s3.367 7 11 7 11-7 11-7-3.367-7-11-7zm0 12c-2.761 0-5-2.239-5-5s2.239-5 5-5 5 2.239 5 5-2.239 5-5 5zm0-8c-1.654 0-3 1.346-3 3s1.346 3 3 3 3-1.346 3-3-1.346-3-3-3z"/>
                </svg>
            </div>
        </div>

        <button type="submit" class="login-button" id="loginBtn">
            Masuk ke Akun
        </button>
    </form>

    <div class="form-links">
        <a href="forgot_password.php" class="form-link">Lupa kata sandi?</a>
    </div>

    <div class="divider">
        <span>atau</span>
    </div>

    <div class="form-links">
        <div class="register-text">
            Belum punya akun? <a href="register.php" class="form-link">Daftar sekarang</a>
        </div>
    </div>
</div>

<!-- SweetAlert2 JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // Password toggle functionality
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');

    togglePassword.addEventListener('click', function () {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        
        // Update icon
        if (type === 'text') {
            eyeIcon.setAttribute('d', 'M12 5c-7.633 0-11 7-11 7s3.367 7 11 7 11-7 11-7-3.367-7-11-7zm0 12c-2.761 0-5-2.239-5-5s2.239-5 5-5 5 2.239 5 5-2.239 5-5 5z M3 3l18 18');
        } else {
            eyeIcon.setAttribute('d', 'M12 5c-7.633 0-11 7-11 7s3.367 7 11 7 11-7 11-7-3.367-7-11-7zm0 12c-2.761 0-5-2.239-5-5s2.239-5 5-5 5 2.239 5 5-2.239 5-5 5zm0-8c-1.654 0-3 1.346-3 3s1.346 3 3 3 3-1.346 3-3-1.346-3-3-3z');
        }
    });

    // Form submission with loading state
    const loginForm = document.getElementById('loginForm');
    const loginBtn = document.getElementById('loginBtn');

    loginForm.addEventListener('submit', function() {
        loginBtn.disabled = true;
        loginBtn.textContent = 'Sedang masuk...';
        
        // Re-enable after 3 seconds if form doesn't redirect
        setTimeout(() => {
            loginBtn.disabled = false;
            loginBtn.textContent = 'Masuk ke Akun';
        }, 3000);
    });

    // Input focus animations
    const inputs = document.querySelectorAll('.form-input');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.style.transform = 'scale(1.02)';
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.style.transform = 'scale(1)';
        });
    });

    // SweetAlert untuk success login
    <?php if ($success): ?>
        Swal.fire({
            icon: 'success',
            title: 'Login Berhasil!',
            text: 'Selamat datang kembali! Anda akan diarahkan ke halaman utama.',
            showConfirmButton: true,
            confirmButtonText: 'Lanjutkan',
            timer: 3000,
            timerProgressBar: true,
            allowOutsideClick: false,
            allowEscapeKey: false,
            customClass: {
                popup: 'animated bounceIn'
            }
        }).then((result) => {
            // Redirect setelah SweetAlert ditutup
            window.location.href = '<?= $redirect_url ?>';
        });
    <?php endif; ?>

    // SweetAlert untuk error (opsional, mengganti error message biasa)
    <?php if ($error): ?>
        Swal.fire({
            icon: 'error',
            title: 'Login Gagal!',
            text: '<?= addslashes($error) ?>',
            confirmButtonText: 'Coba Lagi',
            customClass: {
                popup: 'animated shake'
            }
        });
    <?php endif; ?>
</script>

</body>
</html>