<?php
session_start();
include '../config/db.php';

$error = "";
$success = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name      = trim($_POST['name']);
    $email     = trim($_POST['email']);
    $username  = trim($_POST['username']);
    $password  = $_POST['password'];
    $confirm   = $_POST['confirm_password'];

    // Validasi panjang password minimal 6 karakter
    if (strlen($password) < 6) {
        $error = "Password harus minimal 6 karakter!";
    } elseif ($password !== $confirm) {
        $error = "Konfirmasi password tidak cocok!";
    } else {
        // Simpan password tanpa hash (plain text)
        $plain_password = $password;

        // Cek email sudah dipakai atau belum
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "Email atau username sudah terdaftar!";
        } else {
            // Handle file upload
            $profile_pic = null;
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/profiles/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
                $profile_pic = uniqid() . '.' . $ext;
                move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_dir . $profile_pic);
            }

            // Insert user baru dengan password plain text
            $stmt = $conn->prepare("INSERT INTO users (name, email, username, password, profile_pic) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $email, $username, $plain_password, $profile_pic);

            if ($stmt->execute()) {
                $success = true;
            } else {
                $error = "Gagal mendaftar. Silakan coba lagi.";
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
    <title>Buat Akun Baru - TiketKonser</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.10.1/sweetalert2.min.css" rel="stylesheet">
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

        .register-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            padding: 48px 40px;
            width: 100%;
            max-width: 500px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
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
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .register-title {
            text-align: center;
            font-size: 24px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }

        .welcome-text {
            text-align: center;
            color: #64748b;
            font-size: 15px;
            font-weight: 400;
            margin-bottom: 32px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 24px;
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

        .form-input {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            background-color: #fafbfc;
            font-size: 16px;
            font-weight: 400;
            color: #374151;
            transition: var(--transition);
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

        .file-upload-wrapper {
            position: relative;
        }

        .file-upload-area {
            border: 2px dashed #e5e7eb;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            background: #fafbfc;
            transition: var(--transition);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .file-upload-area:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }

        .file-upload-area.dragover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
            transform: scale(1.02);
        }

        .file-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .file-upload-content {
            pointer-events: none;
        }

        .file-upload-icon {
            font-size: 24px;
            color: #9ca3af;
            margin-bottom: 8px;
        }

        .file-upload-text {
            color: #6b7280;
            font-size: 14px;
            font-weight: 500;
        }

        .file-upload-subtext {
            color: #9ca3af;
            font-size: 12px;
            margin-top: 4px;
        }

        .register-button {
            width: 100%;
            padding: 18px;
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            letter-spacing: 0.025em;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }

        .register-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .register-button:active {
            transform: translateY(0);
        }

        .register-button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .register-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.5s;
        }

        .register-button:hover::before {
            left: 100%;
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
        }

        .form-link:hover {
            color: #764ba2;
            text-decoration: underline;
            transform: translateY(-1px);
        }

        .login-text {
            color: #64748b;
            font-size: 14px;
        }

        .password-strength {
            margin-top: 8px;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            display: none;
        }

        .password-strength.weak {
            background: rgba(255, 117, 140, 0.1);
            color: #ff758c;
            display: block;
        }

        .password-strength.medium {
            background: rgba(255, 154, 158, 0.1);
            color: #ff9a9e;
            display: block;
        }

        .password-strength.strong {
            background: rgba(168, 237, 234, 0.1);
            color: #2c3e50;
            display: block;
        }

        /* Responsive Design */
        @media (max-width: 640px) {
            .register-container {
                padding: 32px 24px;
                margin: 16px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            
            .brand-icon {
                font-size: 28px;
            }
            
            .brand-text {
                font-size: 18px;
            }

            .register-title {
                font-size: 22px;
            }
        }

        /* File preview */
        .file-preview {
            margin-top: 12px;
            padding: 12px;
            background: rgba(102, 126, 234, 0.05);
            border-radius: 8px;
            display: none;
            align-items: center;
            gap: 12px;
        }

        .file-preview.show {
            display: flex;
        }

        .file-preview img {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            object-fit: cover;
        }

        .file-preview-info {
            flex: 1;
        }

        .file-preview-name {
            font-size: 14px;
            font-weight: 500;
            color: #374151;
        }

        .file-preview-size {
            font-size: 12px;
            color: #9ca3af;
        }

        .file-remove {
            background: var(--danger-gradient);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 12px;
            cursor: pointer;
            font-weight: 500;
        }

        .file-remove:hover {
            opacity: 0.8;
        }

        /* Custom SweetAlert styles */
        .swal2-popup {
            border-radius: 16px !important;
            font-family: 'Outfit', sans-serif !important;
        }

        .swal2-title {
            font-weight: 600 !important;
            color: #374151 !important;
        }

        .swal2-html-container {
            color: #64748b !important;
            font-weight: 400 !important;
        }

        .swal2-confirm {
            background: var(--primary-gradient) !important;
            border: none !important;
            border-radius: 10px !important;
            font-weight: 600 !important;
            padding: 10px 24px !important;
            font-size: 14px !important;
        }

        .swal2-cancel {
            background: #e5e7eb !important;
            color: #374151 !important;
            border: none !important;
            border-radius: 10px !important;
            font-weight: 600 !important;
            padding: 10px 24px !important;
            font-size: 14px !important;
        }
    </style>
</head>
<body>

<div class="register-container">
    <div class="brand">
        <span class="brand-icon">🎫</span>
        <div class="brand-text">TiketKonser</div>
    </div>
    
    <h1 class="register-title">Buat Akun Baru</h1>
    <p class="welcome-text">Bergabunglah dengan kami dan nikmati konser impian Anda!</p>

    <form method="POST" enctype="multipart/form-data" id="registerForm">
        <div class="form-row">
            <div class="form-group">
                <label for="name" class="form-label">Nama Lengkap</label>
                <input type="text" 
                       id="name" 
                       name="name" 
                       class="form-input"
                       placeholder="Masukkan nama lengkap" 
                       required
                       value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
            </div>
            <div class="form-group">
                <label for="username" class="form-label">Username</label>
                <input type="text" 
                       id="username" 
                       name="username" 
                       class="form-input"
                       placeholder="Masukkan username" 
                       required
                       value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="email" class="form-label">Email</label>
            <input type="email" 
                   id="email" 
                   name="email" 
                   class="form-input"
                   placeholder="Masukkan alamat email" 
                   required
                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       class="form-input"
                       placeholder="Masukkan password" 
                       required
                       minlength="6">
                <div id="passwordStrength" class="password-strength"></div>
            </div>
            <div class="form-group">
                <label for="confirm_password" class="form-label">Konfirmasi Password</label>
                <input type="password" 
                       id="confirm_password" 
                       name="confirm_password" 
                       class="form-input"
                       placeholder="Konfirmasi password" 
                       required
                       minlength="6">
            </div>
        </div>

        <div class="form-group">
            <label for="profile_pic" class="form-label">Foto Profil (Opsional)</label>
            <div class="file-upload-wrapper">
                <div class="file-upload-area" id="fileUploadArea">
                    <input type="file" 
                           id="profile_pic" 
                           name="profile_pic" 
                           class="file-input"
                           accept="image/*">
                    <div class="file-upload-content">
                        <div class="file-upload-icon">📸</div>
                        <div class="file-upload-text">Klik atau seret gambar ke sini</div>
                        <div class="file-upload-subtext">PNG, JPG hingga 5MB</div>
                    </div>
                </div>
                <div id="filePreview" class="file-preview">
                    <img id="previewImage" src="" alt="Preview">
                    <div class="file-preview-info">
                        <div id="fileName" class="file-preview-name"></div>
                        <div id="fileSize" class="file-preview-size"></div>
                    </div>
                    <button type="button" id="removeFile" class="file-remove">Hapus</button>
                </div>
            </div>
        </div>

        <button type="submit" class="register-button" id="registerBtn">
            Daftar Sekarang
        </button>
    </form>

    <div class="divider">
        <span>atau</span>
    </div>

    <div class="form-links">
        <div class="login-text">
            Sudah punya akun? <a href="login.php" class="form-link">Masuk sekarang</a>
        </div>
    </div>
</div>

<!-- SweetAlert2 JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.10.1/sweetalert2.all.min.js"></script>

<script>
    // SweetAlert notifications
    <?php if ($success): ?>
        Swal.fire({
            title: 'Berhasil!',
            text: 'Akun Anda telah berhasil dibuat. Silakan login untuk melanjutkan.',
            icon: 'success',
            confirmButtonText: 'Login Sekarang',
            allowOutsideClick: false,
            allowEscapeKey: false,
            customClass: {
                confirmButton: 'swal2-confirm'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'login.php';
            }
        });
    <?php elseif (!empty($error)): ?>
        Swal.fire({
            title: 'Oops!',
            text: '<?= addslashes($error) ?>',
            icon: 'error',
            confirmButtonText: 'Coba Lagi',
            customClass: {
                confirmButton: 'swal2-confirm'
            }
        });
    <?php endif; ?>

    // Password strength checker
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const passwordStrength = document.getElementById('passwordStrength');

    passwordInput.addEventListener('input', function() {
        const password = this.value;
        const strength = checkPasswordStrength(password);
        
        passwordStrength.className = 'password-strength ' + strength.class;
        passwordStrength.textContent = strength.text;
    });

    function checkPasswordStrength(password) {
        if (password.length < 6) {
            return { class: 'weak', text: 'Password terlalu pendek (minimal 6 karakter)' };
        }
        
        let score = 0;
        if (password.length >= 8) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^A-Za-z0-9]/.test(password)) score++;
        
        if (score < 2) {
            return { class: 'weak', text: 'Password lemah' };
        } else if (score < 3) {
            return { class: 'medium', text: 'Password sedang' };
        } else {
            return { class: 'strong', text: 'Password kuat' };
        }
    }

    // Password confirmation validation
    confirmPasswordInput.addEventListener('input', function() {
        if (this.value !== passwordInput.value) {
            this.setCustomValidity('Password tidak cocok');
        } else {
            this.setCustomValidity('');
        }
    });

    // File upload handling
    const fileInput = document.getElementById('profile_pic');
    const fileUploadArea = document.getElementById('fileUploadArea');
    const filePreview = document.getElementById('filePreview');
    const previewImage = document.getElementById('previewImage');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const removeFileBtn = document.getElementById('removeFile');

    fileUploadArea.addEventListener('click', () => fileInput.click());

    fileUploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        fileUploadArea.classList.add('dragover');
    });

    fileUploadArea.addEventListener('dragleave', () => {
        fileUploadArea.classList.remove('dragover');
    });

    fileUploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        fileUploadArea.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleFile(files[0]);
        }
    });

    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            handleFile(e.target.files[0]);
        }
    });

    function handleFile(file) {
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                previewImage.src = e.target.result;
                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                filePreview.classList.add('show');
            };
            reader.readAsDataURL(file);
        } else {
            Swal.fire({
                title: 'File Tidak Valid',
                text: 'Silakan pilih file gambar (PNG, JPG, JPEG)',
                icon: 'warning',
                confirmButtonText: 'OK',
                customClass: {
                    confirmButton: 'swal2-confirm'
                }
            });
        }
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    removeFileBtn.addEventListener('click', () => {
        fileInput.value = '';
        filePreview.classList.remove('show');
    });

    // Form submission with loading state and SweetAlert
    const registerForm = document.getElementById('registerForm');
    const registerBtn = document.getElementById('registerBtn');

    registerForm.addEventListener('submit', function(e) {
        // Validasi password minimal 6 karakter sebelum submit
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        
        if (password.length < 6) {
            e.preventDefault();
            Swal.fire({
                title: 'Password Terlalu Pendek',
                text: 'Password harus minimal 6 karakter!',
                icon: 'warning',
                confirmButtonText: 'OK',
                customClass: {
                    confirmButton: 'swal2-confirm'
                }
            });
            return;
        }
        
        if (password !== confirmPassword) {
            e.preventDefault();
            Swal.fire({
                title: 'Password Tidak Cocok',
                text: 'Konfirmasi password tidak cocok dengan password yang dimasukkan!',
                icon: 'warning',
                confirmButtonText: 'OK',
                customClass: {
                    confirmButton: 'swal2-confirm'
                }
            });
            return;
        }
        
        // Show loading state
        registerBtn.disabled = true;
        registerBtn.innerHTML = '<span style="opacity: 0.7;">⏳</span> Sedang mendaftar...';
        
        // Show loading alert
        Swal.fire({
            title: 'Sedang Memproses...',
            text: 'Mohon tunggu sebentar',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Re-enable after timeout if form doesn't redirect
        setTimeout(() => {
            registerBtn.disabled = false;
            registerBtn.textContent = 'Daftar Sekarang';
            Swal.close();
        }, 10000);
    });

    // Input focus animations
    const inputs = document.querySelectorAll('.form-input');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.style.transform = 'translateY(-1px)';
        });
        
        input.addEventListener('blur', function() {
            this.style.transform = 'translateY(0)';
        });
    });

    // Konfirmasi sebelum meninggalkan halaman jika form sudah diisi
    let formChanged = false;
    inputs.forEach(input => {
        input.addEventListener('input', () => {
            formChanged = true;
        });
    });

    window.addEventListener('beforeunload', function(e) {
        if (formChanged && !registerBtn.disabled) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
</script>

</body>
</html>