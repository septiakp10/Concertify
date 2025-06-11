<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error_message = null;
$password_message = null;

// Handle update profile
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['update_profile'])) {
        $new_name = trim($_POST['edit_name']);
        $new_email = trim($_POST['edit_email']);
        $new_username = trim($_POST['edit_username']);
        $profile_pic = null;

        // Cek duplikasi email/username
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE (email = ? OR username = ?) AND id != ?");
        $check_stmt->bind_param("ssi", $new_email, $new_username, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error_message = "Email atau Username sudah digunakan.";
        } else {
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $mime_type = mime_content_type($_FILES['profile_image']['tmp_name']);
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];

                if (in_array($mime_type, $allowed_types)) {
                    $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                    $profile_pic = 'user_' . $user_id . '_' . time() . '.' . $ext;
                    $upload_path = '../uploads/profiles/' . $profile_pic;

                    // Hapus foto lama
                    $old_stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
                    $old_stmt->bind_param("i", $user_id);
                    $old_stmt->execute();
                    $old_result = $old_stmt->get_result();
                    $old_data = $old_result->fetch_assoc();
                    if ($old_data && $old_data['profile_pic'] && file_exists("../uploads/profiles/" . $old_data['profile_pic'])) {
                        unlink("../uploads/profiles/" . $old_data['profile_pic']);
                    }

                    move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path);
                } else {
                    $error_message = "Format gambar tidak valid.";
                }
            }

            if (!$error_message) {
                if ($profile_pic) {
                    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, username = ?, profile_pic = ? WHERE id = ?");
                    $stmt->bind_param("ssssi", $new_name, $new_email, $new_username, $profile_pic, $user_id);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, username = ? WHERE id = ?");
                    $stmt->bind_param("sssi", $new_name, $new_email, $new_username, $user_id);
                }
                
    $stmt->execute();
    $_SESSION['success_message'] = "Profil berhasil diperbarui!"; // Tambahkan baris ini
    header("Location: profile.php");
    exit();

                $stmt->execute();
                header("Location: profile.php");
                exit();
            }
            
        }
    }

if (isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];

    if (strlen($new_pass) < 6) {
        $password_message = "Password baru minimal 6 karakter.";
    } else {
        // Ambil password lama dari database
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();

            // Cek apakah password lama cocok
            if ($current_pass === $row['password']) {
                // Update password baru
                $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_stmt->bind_param("si", $new_pass, $user_id);

                if ($update_stmt->execute()) {
                    $_SESSION['success_message'] = "Password berhasil diperbarui!";
                    header("Location: profile.php");
                    exit();
                } else {
                    $password_message = "Gagal memperbarui password. Silakan coba lagi.";
                }

                $update_stmt->close();
            } else {
                $password_message = "Password lama salah.";
            }
        } else {
            $password_message = "User tidak ditemukan.";
        }

        $stmt->close();
    }
}
if (isset($_POST['delete_account'])) {
    $current_user_id = $user_id;
    
    try {
        // Mulai transaction
        $conn->autocommit(FALSE);
        
        // Hapus foto profil jika ada
        $get_pic_stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
        $get_pic_stmt->bind_param("i", $current_user_id);
        $get_pic_stmt->execute();
        $pic_result = $get_pic_stmt->get_result();
        $pic_data = $pic_result->fetch_assoc();
        
        if ($pic_data && $pic_data['profile_pic'] && file_exists("../uploads/profiles/" . $pic_data['profile_pic'])) {
            unlink("../uploads/profiles/" . $pic_data['profile_pic']);
        }
        
        // Hapus orders dulu (foreign key constraint)
        $delete_orders = $conn->prepare("DELETE FROM orders WHERE user_id = ?");
        $delete_orders->bind_param("i", $current_user_id);
        $delete_orders->execute();
        
        // Hapus user
        $delete_user = $conn->prepare("DELETE FROM users WHERE id = ?");
        $delete_user->bind_param("i", $current_user_id);
        $delete_user->execute();
        
        // Commit transaction
        $conn->commit();
        
        // Hancurkan session
        session_unset();
        session_destroy();
        
        // Set cookie untuk notifikasi
        setcookie('account_deleted', 'true', time() + 10, '/');
        
        // Redirect dengan header
        header("Location: ../index.php?deleted=1");
        exit();
        
    } catch (Exception $e) {
        // Rollback jika error
        $conn->rollback();
        $error_message = "Gagal menghapus akun: " . $e->getMessage();
    }
}

    if (isset($_POST['cancel_order']) && isset($_POST['order_id'])) {
        $order_id = $_POST['order_id'];
        $check_cancel_stmt = $conn->prepare("SELECT status FROM orders WHERE id = ? AND user_id = ?");
        $check_cancel_stmt->bind_param("ii", $order_id, $user_id);
        $check_cancel_stmt->execute();
        $cancel_result = $check_cancel_stmt->get_result();
        if ($cancel_result->num_rows > 0) {
            $order_data = $cancel_result->fetch_assoc();
            if ($order_data['status'] !== 'Berhasil') {
                $cancel_stmt = $conn->prepare("UPDATE orders SET status = 'Dibatalkan' WHERE id = ?");
                $cancel_stmt->bind_param("i", $order_id);
                $cancel_stmt->execute();
                header("Location: profile.php");
                exit();
            }
        }
    }
}

// Ambil data user
$stmt = $conn->prepare("SELECT name, email, username, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Ambil data orders
$order_stmt = $conn->prepare("
    SELECT o.id AS order_id, c.artist, c.location, c.date, t.category, o.quantity, o.total_price, o.status
    FROM orders o
    JOIN concerts c ON o.concert_id = c.id
    JOIN ticket_categories t ON o.category_id = t.id
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
");
$order_stmt->bind_param("i", $user_id);
$order_stmt->execute();
$orders = $order_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Pengguna</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

        .main-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            margin: 2rem auto;
            max-width: 1200px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: #495057;
            border-radius: 15px;
            padding: 0.75rem 1.5rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            transition: var(--transition);
            margin-bottom: 1rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .btn-back:hover {
            background: var(--primary-gradient);
            color: white;
            border-color: transparent;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .profile-header {
            background: var(--primary-gradient);
            color: white;
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        .profile-img {
            width: 120px; 
            height: 120px; 
            border-radius: 50%;
            object-fit: cover; 
            border: 4px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transition: var(--transition);
        }

        .profile-img:hover {
            transform: scale(1.05);
        }

        .card-modern {
            border: none; 
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            transition: var(--transition);
            background: white;
            backdrop-filter: blur(10px);
        }

        .card-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
        }

        .card-header-modern {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 2px solid #e2e8f0;
            border-radius: var(--border-radius) var(--border-radius) 0 0 !important;
            padding: 1.5rem 2rem;
        }

        .card-header-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }

        .card-title-modern {
            color: #2c3e50;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.25rem;
        }

        .card-body-modern {
            padding: 2rem;
        }

        .btn-modern {
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: var(--transition);
            border: none;
            position: relative;
            overflow: hidden;
            font-size: 0.95rem;
        }

        .btn-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-modern:hover::before {
            left: 100%;
        }

        .btn-primary-modern {
            background: var(--primary-gradient);
            color: white;
        }

        .btn-primary-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-success-modern {
            background: var(--success-gradient);
            color: #2c3e50;
        }

        .btn-success-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(168, 237, 234, 0.4);
        }

        .btn-danger-modern {
            background: var(--danger-gradient);
            color: white;
        }

        .btn-danger-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 117, 140, 0.4);
        }

        .btn-warning-modern {
            background: var(--warning-gradient);
            color: #2c3e50;
        }

        .btn-warning-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 154, 158, 0.4);
        }

        .btn-outline-modern {
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .btn-outline-modern:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            transform: translateY(-2px);
        }

        .form-control-modern {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 0.875rem 1.25rem;
            transition: var(--transition);
            background: #f8f9fa;
            font-size: 0.95rem;
        }

        .form-control-modern:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
            background: white;
        }

        .form-label-modern {
            color: #495057;
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .table-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
            margin-top: 1.5rem;
        }

        .table-modern {
            margin: 0;
            border: none;
            width: 100%;
        }

        .table-modern thead th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: none;
            padding: 1.25rem 1rem;
            font-weight: 600;
            color: #495057;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }

        .table-modern tbody td {
            padding: 1.25rem 1rem;
            border-top: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .table-modern tbody tr {
            transition: var(--transition);
        }

        .table-modern tbody tr:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
        }

        .badge-modern {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .badge-success {
            background: var(--success-gradient);
            color: #2c3e50;
        }

        .badge-danger {
            background: var(--danger-gradient);
            color: white;
        }

        .badge-warning {
            background: var(--warning-gradient);
            color: #2c3e50;
        }

        .badge-info {
            background: var(--primary-gradient);
            color: white;
        }

        .alert-modern {
            border: none;
            border-radius: 15px;
            padding: 1.25rem 1.5rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f1aeb5 100%);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .alert-info {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }

        #edit-section, #password-section {
            display: none;
            animation: fadeIn 0.4s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .action-buttons {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .user-info h3 {
            font-weight: 700;
            margin-bottom: 0.5rem;
            font-size: 1.75rem;
        }

        .user-info p {
            margin-bottom: 0.5rem;
            opacity: 0.9;
            font-size: 1rem;
        }

        .no-tickets {
            text-align: center;
            padding: 3rem 2rem;
            color: #6c757d;
        }

        .no-tickets i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .main-container {
                margin: 1rem;
                border-radius: 15px;
            }
            
            .profile-header {
                text-align: center;
                padding: 1.5rem;
            }
            
            .action-buttons {
                justify-content: center;
                margin-top: 1.5rem;
            }
            
            .btn-modern {
                font-size: 0.9rem;
                padding: 0.6rem 1.2rem;
            }
            
            .card-body-modern {
                padding: 1.5rem;
            }
            
            .table-responsive {
                font-size: 0.875rem;
            }

            .user-info h3 {
                font-size: 1.5rem;
            }
        }

        .form-row-modern {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

.swal2-popup {
    border-radius: 20px !important;
    box-shadow: 0 15px 35px rgba(0,0,0,0.15) !important;
    font-family: 'Inter', sans-serif !important;
    padding: 2rem !important;
}

.swal2-title {
    font-size: 1.5rem !important;
    font-weight: 600 !important;
    color: #2c3e50 !important;
    margin-bottom: 1rem !important;
}

.swal2-content {
    font-size: 1rem !important;
    color: #6c757d !important;
    margin-bottom: 1.5rem !important;
}

.swal2-confirm {
    background: var(--primary-gradient) !important;
    border: none !important;
    border-radius: 12px !important;
    padding: 0.75rem 2rem !important;
    font-weight: 600 !important;
    font-size: 0.95rem !important;
    transition: var(--transition) !important;
}

.swal2-confirm:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4) !important;
}

.swal2-cancel {
    background: var(--danger-gradient) !important;
    border: none !important;
    border-radius: 12px !important;
    padding: 0.75rem 2rem !important;
    font-weight: 600 !important;
    font-size: 0.95rem !important;
    color: white !important;
    transition: var(--transition) !important;
}

.swal2-cancel:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 8px 25px rgba(255, 117, 140, 0.4) !important;
}

.swal2-icon.swal2-success {
    color: #28a745 !important;
    border-color: #28a745 !important;
}

.swal2-icon.swal2-error {
    color: #dc3545 !important;
    border-color: #dc3545 !important;
}

.swal2-icon.swal2-warning {
    color: #ffc107 !important;
    border-color: #ffc107 !important;
}
    </style>
</head>
<body>
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-12">
            <a href="../index.php" class="btn-back">
                <i class="fas fa-arrow-left"></i>
                Kembali ke Beranda
            </a>
        </div>
    </div>
    
    <div class="main-container">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="row align-items-center">
                <div class="col-md-8 d-flex align-items-center gap-4">
                    <img src="<?= $user['profile_pic'] ? '../uploads/profiles/' . $user['profile_pic'] : '../assets/default-avatar.png' ?>" 
                         alt="Foto Profil" class="profile-img">
                    <div class="user-info">
                        <h3><?= htmlspecialchars($user['name']) ?></h3>
                        <p><i class="fas fa-envelope me-2"></i><?= htmlspecialchars($user['email']) ?></p>
                        <p><i class="fas fa-user me-2"></i>@<?= htmlspecialchars($user['username']) ?></p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="action-buttons">
                        <button class="btn btn-modern btn-outline-modern" id="toggleEditBtn">
                            <i class="fas fa-edit me-2"></i>Edit Profil
                        </button>
                        <button class="btn btn-modern btn-outline-modern" id="togglePassBtn">
                            <i class="fas fa-key me-2"></i>Ganti Password
                        </button>
                    </div>
                    <div class="action-buttons mt-2">
                    <form method="POST" id="deleteAccountForm">
                            <button type="submit" name="delete_account" class="btn btn-modern btn-danger-modern">
                                <i class="fas fa-trash me-2"></i>Hapus Akun
                            </button>
                        </form>
                        <a href="../auth/logout.php" class="btn btn-modern btn-warning-modern">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-4">
            <!-- Edit Profile Section -->
            <div id="edit-section" class="card-modern">
                <div class="card-header-modern position-relative">
                    <h5 class="card-title-modern">
                        <i class="fas fa-user-edit"></i>
                        Edit Profil
                    </h5>
                </div>
                <div class="card-body-modern">
                    <?php if ($error_message): ?>
                        <div class="alert alert-modern alert-danger">
                            <i class="fas fa-exclamation-circle"></i>
                            <?= $error_message ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-row-modern">
                            <div>
                                <label class="form-label-modern">
                                    <i class="fas fa-user"></i>Nama
                                </label>
                                <input type="text" class="form-control form-control-modern" name="edit_name" 
                                       value="<?= htmlspecialchars($user['name']) ?>" required>
                            </div>
                            <div>
                                <label class="form-label-modern">
                                    <i class="fas fa-envelope"></i>Email
                                </label>
                                <input type="email" class="form-control form-control-modern" name="edit_email" 
                                       value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>
                            <div>
                                <label class="form-label-modern">
                                    <i class="fas fa-at"></i>Username
                                </label>
                                <input type="text" class="form-control form-control-modern" name="edit_username" 
                                       value="<?= htmlspecialchars($user['username']) ?>" required>
                            </div>
                            <div>
                                <label class="form-label-modern">
                                    <i class="fas fa-image"></i>Foto Profil (Opsional)
                                </label>
                                <input type="file" class="form-control form-control-modern" name="profile_image" accept="image/*">
                            </div>
                        </div>
                        <div class="text-end">
                            <button type="submit" name="update_profile" class="btn btn-modern btn-success-modern">
                                <i class="fas fa-save me-2"></i>Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Change Password Section -->
            <div id="password-section" class="card-modern">
                    <div class="card-header-modern position-relative">
                        <h5 class="card-title-modern"><i class="fas fa-lock"></i> Ganti Password</h5>
                    </div>
                    <div class="card-body-modern">
                        <form method="POST">
                            <div class="form-row-modern">
                                <div>
                                    <label class="form-label-modern"><i class="fas fa-key"></i> Password Lama</label>
                                    <input type="password" class="form-control form-control-modern" name="current_password" required>
                                </div>
                                <div>
                                    <label class="form-label-modern"><i class="fas fa-lock"></i> Password Baru</label>
                                    <input type="password" class="form-control form-control-modern" name="new_password" required>
                                </div>
                            </div>
                            <div class="text-end mt-3">
                                <button type="submit" name="change_password" class="btn btn-modern btn-primary-modern">
                                    <i class="fas fa-check me-2"></i>Ubah Password
                                </button>
                            </div>
                        </form>
                        <?php if (isset($password_message)): ?>
                            <div class="alert alert-warning mt-2"><?php echo $password_message; ?></div>
                        <?php endif; ?>
                    </div>
                </div>

<!-- Ticket History -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title">
            <i class="fas fa-ticket-alt"></i>
            Riwayat Pembelian Tiket
        </h5>
    </div>
    <div class="card-body">
        <?php if ($orders->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                    <tr>
                        <th><i class="fas fa-music me-2"></i>Konser</th>
                        <th><i class="fas fa-map-marker-alt me-2"></i>Lokasi</th>
                        <th><i class="fas fa-calendar me-2"></i>Tanggal</th>
                        <th><i class="fas fa-tags me-2"></i>Kategori</th>
                        <th><i class="fas fa-sort-numeric-up me-2"></i>Jumlah</th>
                        <th><i class="fas fa-money-bill me-2"></i>Total Harga</th>
                        <th><i class="fas fa-info-circle me-2"></i>Status</th>

                    </tr>
                    </thead>
                    <tbody>
                    <?php while ($order = $orders->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($order['artist']) ?></strong></td>
                            <td><?= htmlspecialchars($order['location']) ?></td>
                            <td><?= date("d M Y", strtotime($order['date'])) ?></td>
                            <td><?= htmlspecialchars($order['category']) ?></td>
                            <td><span class="badge bg-info"><?= $order['quantity'] ?></span></td>
                            <td><strong>Rp<?= number_format($order['total_price'], 0, ',', '.') ?></strong></td>
                            <td>
                                <?php
                                    if ($order['status'] === 'Berhasil') {
                                        echo '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Berhasil</span>';
                                    } elseif ($order['status'] === 'Dikonfirmasi') {
                                        echo '<span class="badge bg-primary"><i class="fas fa-check-double me-1"></i>Dikonfirmasi</span>';
                                    } elseif ($order['status'] === 'Dibatalkan') {
                                        echo '<span class="badge bg-danger"><i class="fas fa-times me-1"></i>Dibatalkan</span>';
                                    } elseif ($order['status'] === 'Ditolak') {
                                        echo '<span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Ditolak</span>';
                                    } else {
                                        echo '<span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Menunggu Konfirmasi</span>';
                                    }
                                ?>
                            </td>

                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-ticket-alt fa-3x text-muted mb-3"></i>
                <p class="text-muted">Belum ada tiket yang dibeli.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.0/dist/sweetalert2.all.min.js"></script>
<script>
// Toggle Edit dan Password Section
const toggleEditBtn = document.getElementById('toggleEditBtn');
const togglePassBtn = document.getElementById('togglePassBtn');
const editSection = document.getElementById('edit-section');
const passSection = document.getElementById('password-section');

toggleEditBtn.addEventListener('click', () => {
    const visible = editSection.style.display === 'block';
    editSection.style.display = visible ? 'none' : 'block';
    toggleEditBtn.innerHTML = visible ? '<i class="fas fa-edit me-2"></i>Edit Profil' : '<i class="fas fa-times me-2"></i>Tutup Edit';
    if (!visible) {
        passSection.style.display = 'none';
        togglePassBtn.innerHTML = '<i class="fas fa-key me-2"></i>Ganti Password';
    }
});

togglePassBtn.addEventListener('click', () => {
    const visible = passSection.style.display === 'block';
    passSection.style.display = visible ? 'none' : 'block';
    togglePassBtn.innerHTML = visible ? '<i class="fas fa-key me-2"></i>Ganti Password' : '<i class="fas fa-times me-2"></i>Tutup Password';
    if (!visible) {
        editSection.style.display = 'none';
        toggleEditBtn.innerHTML = '<i class="fas fa-edit me-2"></i>Edit Profil';
    }
});

// SweetAlert2 Notifications
<?php if ($error_message): ?>
Swal.fire({
    icon: 'error',
    title: 'Oops!',
    text: '<?= addslashes($error_message) ?>',
    confirmButtonText: 'OK',
    backdrop: 'rgba(0,0,0,0.4)',
    allowOutsideClick: false
});
<?php endif; ?>

<?php if (isset($password_message)): ?>
Swal.fire({
    icon: 'warning',
    title: 'Perhatian!',
    text: '<?= addslashes($password_message) ?>',
    confirmButtonText: 'OK',
    backdrop: 'rgba(0,0,0,0.4)',
    allowOutsideClick: false
});
<?php endif; ?>

<?php if (isset($_SESSION['success_message'])): ?>
Swal.fire({
    icon: 'success',
    title: 'Berhasil!',
    text: '<?= addslashes($_SESSION['success_message']) ?>',
    confirmButtonText: 'OK',
    backdrop: 'rgba(0,0,0,0.4)',
    allowOutsideClick: false
}).then((result) => {
    if (result.isConfirmed) {
        window.location.href = 'profile.php';
    }
});
<?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

// Confirmation for delete account - Enhanced with multiple alerts
// Coba beberapa selector yang berbeda
const deleteForm = document.querySelector('#deleteAccountForm') || 
                   document.querySelector('form[action*="delete"]') || 
                   document.querySelector('form[onsubmit*="confirm"]') ||
                   document.querySelector('form:has(input[name="delete_account"])') ||
                   document.querySelector('form:has(button[name="delete_account"])');

if (deleteForm) {
    deleteForm.onsubmit = function(e) {
    e.preventDefault();
    
    // First confirmation
    Swal.fire({
        icon: 'warning',
        title: 'Hapus Akun?',
        html: '<p>Yakin ingin menghapus akun?</p><p class="text-danger"><strong>Tindakan ini tidak dapat dibatalkan!</strong></p>',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        backdrop: 'rgba(0,0,0,0.4)',
        allowOutsideClick: false,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        focusCancel: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Second confirmation - more serious
            Swal.fire({
                icon: 'error',
                title: 'Peringatan Serius!',
                html: `
                    <div class="text-start">
                        <p><strong>Menghapus akun akan:</strong></p>
                        <ul class="text-danger">
                            <li>Menghapus semua data pribadi Anda</li>
                            <li>Menghapus riwayat aktivitas</li>
                            <li>Tidak dapat dikembalikan</li>
                        </ul>
                        <p class="mt-3"><strong>Apakah Anda benar-benar yakin?</strong></p>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Ya, Saya Yakin!',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                backdrop: 'rgba(0,0,0,0.6)',
                allowOutsideClick: false,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#28a745',
                focusCancel: true
            }).then((finalResult) => {
                if (finalResult.isConfirmed) {
                    // Show loading alert
                    Swal.fire({
                        title: 'Menghapus Akun...',
                        html: '<div class="spinner-border text-danger" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-3">Mohon tunggu, sedang memproses...</p>',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        backdrop: 'rgba(0,0,0,0.8)'
                    });
                    
                    // Gunakan AJAX untuk hapus akun
                    fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'delete_account=1'
                    }).then(response => {
                        if (response.ok) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Akun Berhasil Dihapus!',
                                text: 'Terima kasih telah menggunakan layanan kami.',
                                confirmButtonText: 'OK',
                                backdrop: 'rgba(0,0,0,0.4)',
                                allowOutsideClick: false,
                                confirmButtonColor: '#28a745'
                            }).then(() => {
                                window.location.href = '../index.php';
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal Menghapus Akun!',
                                text: 'Terjadi kesalahan saat menghapus akun. Silakan coba lagi.',
                                confirmButtonText: 'OK',
                                backdrop: 'rgba(0,0,0,0.4)',
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    }).catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error Sistem!',
                            text: 'Terjadi kesalahan sistem. Silakan coba lagi nanti.',
                            confirmButtonText: 'OK',
                            backdrop: 'rgba(0,0,0,0.4)',
                            confirmButtonColor: '#dc3545'
                        });
                    });
                }
            });
        }
    });
    
    return false;
    };
} else {
    // Jika form tidak ditemukan, coba dengan event delegation pada button
    document.addEventListener('click', function(e) {
        // Cari button hapus akun berdasarkan berbagai kemungkinan
        if (e.target.matches('button[name="delete_account"]') || 
            e.target.matches('input[name="delete_account"]') ||
            e.target.matches('button[type="submit"][value*="hapus"]') ||
            e.target.matches('button[onclick*="delete"]') ||
            e.target.closest('form[action*="delete"]')) {
            
            e.preventDefault();
            e.stopPropagation();
            
            // First confirmation
            Swal.fire({
                icon: 'warning',
                title: 'Hapus Akun?',
                html: '<p>Yakin ingin menghapus akun?</p><p class="text-danger"><strong>Tindakan ini tidak dapat dibatalkan!</strong></p>',
                showCancelButton: true,
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                backdrop: 'rgba(0,0,0,0.4)',
                allowOutsideClick: false,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                focusCancel: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Second confirmation - more serious
                    Swal.fire({
                        icon: 'error',
                        title: 'Peringatan Serius!',
                        html: `
                            <div class="text-start">
                                <p><strong>Menghapus akun akan:</strong></p>
                                <ul class="text-danger">
                                    <li>Menghapus semua data pribadi Anda</li>
                                    <li>Menghapus riwayat aktivitas</li>
                                    <li>Tidak dapat dikembalikan</li>
                                </ul>
                                <p class="mt-3"><strong>Apakah Anda benar-benar yakin?</strong></p>
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonText: 'Ya, Saya Yakin!',
                        cancelButtonText: 'Batal',
                        reverseButtons: true,
                        backdrop: 'rgba(0,0,0,0.6)',
                        allowOutsideClick: false,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#28a745',
                        focusCancel: true
                    }).then((finalResult) => {
                        if (finalResult.isConfirmed) {
                            // Show loading alert
                            Swal.fire({
                                title: 'Menghapus Akun...',
                                html: '<div class="spinner-border text-danger" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-3">Mohon tunggu, sedang memproses...</p>',
                                allowOutsideClick: false,
                                allowEscapeKey: false,
                                showConfirmButton: false,
                                backdrop: 'rgba(0,0,0,0.8)'
                            });
                            
                            // Submit form atau gunakan AJAX
                            const form = e.target.closest('form');
                            if (form) {
                                form.submit();
                            } else {
                                // Gunakan AJAX jika tidak ada form
                                fetch(window.location.href, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                    },
                                    body: 'delete_account=1'
                                }).then(response => {
                                    if (response.ok) {
                                        Swal.fire({
                                            icon: 'success',
                                            title: 'Akun Berhasil Dihapus!',
                                            text: 'Terima kasih telah menggunakan layanan kami.',
                                            confirmButtonText: 'OK',
                                            backdrop: 'rgba(0,0,0,0.4)',
                                            allowOutsideClick: false,
                                            confirmButtonColor: '#28a745'
                                        }).then(() => {
                                            window.location.href = '../index.php';
                                        });
                                    } else {
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'Gagal Menghapus Akun!',
                                            text: 'Terjadi kesalahan saat menghapus akun. Silakan coba lagi.',
                                            confirmButtonText: 'OK',
                                            backdrop: 'rgba(0,0,0,0.4)',
                                            confirmButtonColor: '#dc3545'
                                        });
                                    }
                                }).catch(error => {
                                    console.error('Error:', error);
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error Sistem!',
                                        text: 'Terjadi kesalahan sistem. Silakan coba lagi nanti.',
                                        confirmButtonText: 'OK',
                                        backdrop: 'rgba(0,0,0,0.4)',
                                        confirmButtonColor: '#dc3545'
                                    });
                                });
                            }
                        }
                    });
                }
            });
        }
    });
}
</script>
</body>
</html>