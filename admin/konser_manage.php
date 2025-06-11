<?php
include '../config/db.php';

// Mulai session di awal
session_start();

$edit_mode = false;
$artist = $location = $date = $desc = $image = '';
$status = 'Available';

if (isset($_GET['edit'])) {
    $edit_mode = true;
    $id = intval($_GET['edit']);
    $result = $conn->query("SELECT * FROM concerts WHERE id = $id");
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $artist = $row['artist'];
        $location = $row['location'];
        $date = $row['date'];
        $desc = $row['description'];
        $image = $row['image'];
        $status = $row['status'];
    } else {
        $error = "Data konser tidak ditemukan.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $artist   = trim($_POST['artist']);
    $location = trim($_POST['location']);
    $date     = $_POST['date'];
    $desc     = trim($_POST['description']);
    $status   = 'Available';

    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $upload_dir = '../uploads/';
        $filename = time() . '_' . basename($_FILES['image']['name']);
        $target_file = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $image = $filename;
        } else {
            $error = "Upload gambar gagal.";
        }
    }

    if (!empty($artist) && !empty($location) && !empty($date) && !isset($error)) {
        if (isset($_POST['update_konser'])) {
            // UPDATE konser yang sudah ada
            $id = intval($_POST['id']);
            if (!empty($image)) {
                $old = $conn->query("SELECT image FROM concerts WHERE id = $id")->fetch_assoc();
                if ($old && $old['image'] && file_exists('../uploads/' . $old['image'])) {
                    unlink('../uploads/' . $old['image']);
                }

                $stmt = $conn->prepare("UPDATE concerts SET artist=?, location=?, date=?, description=?, image=? WHERE id=?");
                $stmt->bind_param("sssssi", $artist, $location, $date, $desc, $image, $id);
            } else {
                $stmt = $conn->prepare("UPDATE concerts SET artist=?, location=?, date=?, description=? WHERE id=?");
                $stmt->bind_param("ssssi", $artist, $location, $date, $desc, $id);
            }
            
            if ($stmt->execute()) {
                $success = "Konser berhasil diperbarui!";
                $edit_mode = false;
                // Reset form setelah update
                $artist = $location = $date = $desc = $image = '';
            } else {
                $error = "Gagal memperbarui konser.";
            }
        } elseif (isset($_POST['tambah_konser'])) {
            // INSERT konser baru
            if (!empty($image)) {
                $stmt = $conn->prepare("INSERT INTO concerts (artist, location, date, description, image, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $artist, $location, $date, $desc, $image, $status);
            } else {
                $stmt = $conn->prepare("INSERT INTO concerts (artist, location, date, description, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $artist, $location, $date, $desc, $status);
            }
            
            if ($stmt->execute()) {
                $success = "Konser berhasil ditambahkan!";
                // Reset form setelah berhasil tambah
                $artist = $location = $date = $desc = $image = '';
            } else {
                $error = "Gagal menambahkan konser.";
            }
        }
    } else {
        if (!isset($error)) {
            $error = "Semua field wajib diisi.";
        }
    }
}

if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    $get = $conn->query("SELECT image FROM concerts WHERE id = $id")->fetch_assoc();
    if ($get && $get['image']) {
        $img_path = '../uploads/' . $get['image'];
        if (file_exists($img_path)) unlink($img_path);
    }

    if ($conn->query("DELETE FROM concerts WHERE id = $id")) {
        $_SESSION['delete_success'] = true;
    } else {
        $_SESSION['delete_error'] = true;
    }
    
    header("Location: konser_manage.php");
    exit();
}

// Cek session untuk pesan sukses/error
$delete_success = false;
$delete_error = false;

if (isset($_SESSION['delete_success'])) {
    $delete_success = true;
    unset($_SESSION['delete_success']);
}

if (isset($_SESSION['delete_error'])) {
    $delete_error = true;
    unset($_SESSION['delete_error']);
}

$konser = $conn->query("SELECT * FROM concerts ORDER BY date DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Konser - Concert Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            min-height: 100vh;
            padding: 2rem 0;
        }

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
            padding: 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin: 0 auto 3rem;
            max-width: 600px;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--primary-gradient);
        }

        .page-header h2 {
            font-size: 2.5rem;
            font-weight: 700;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #6c757d;
            font-size: 1.1rem;
            margin: 0;
        }

        .btn-back {
            background: white;
            color: #495057;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .btn-back:hover {
            background: var(--primary-gradient);
            color: white;
            border-color: transparent;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .card-modern {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            border: none;
            overflow: hidden;
            margin-bottom: 2rem;
            backdrop-filter: blur(10px);
            position: relative;
        }

        .card-header-modern {
            background: var(--primary-gradient);
            color: white;
            padding: 1.5rem 2rem;
            border: none;
            position: relative;
        }

        .card-header-modern h4 {
            font-size: 1.3rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
        }

        .card-header-modern i {
            margin-right: 0.75rem;
            font-size: 1.2rem;
        }

        .card-body-modern {
            padding: 2.5rem;
        }

        .form-floating {
            margin-bottom: 1.5rem;
        }

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1rem;
            font-size: 1rem;
            transition: var(--transition);
            background: #f8f9fa;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            background: white;
            transform: translateY(-2px);
        }

        .form-label {
            color: #495057;
            font-weight: 600;
            margin-bottom: 0.5rem;
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

        .alert-modern {
            border: none;
            border-radius: 15px;
            padding: 1.25rem 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            font-weight: 500;
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

        .table-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
            margin-top: 2rem;
        }

        .table-header {
            background: var(--primary-gradient);
            color: white;
            padding: 1.5rem 2rem;
        }

        .table-header h4 {
            margin: 0;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .table-modern {
            margin: 0;
            border: none;
        }

        .table-modern thead th {
            background: #f8f9fa;
            border: none;
            padding: 1.5rem 1rem;
            font-weight: 600;
            color: #495057;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.5px;
        }

        .table-modern tbody td {
            padding: 1.5rem 1rem;
            border-top: 1px solid #e9ecef;
            vertical-align: middle;
        }

        .table-modern tbody tr {
            transition: var(--transition);
        }

        .table-modern tbody tr:hover {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .img-thumbnail-modern {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: var(--transition);
        }

        .img-thumbnail-modern:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }

        .badge-modern {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.85rem;
            background: var(--success-gradient);
            color: #2c3e50;
            border: none;
        }

        .btn-action {
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            margin: 0 0.25rem;
            transition: var(--transition);
            border: none;
            font-size: 0.875rem;
            text-decoration: none;
            display: inline-block;
        }

        .btn-warning-modern {
            background: var(--warning-gradient);
            color: #2c3e50;
        }

        .btn-danger-modern {
            background: var(--danger-gradient);
            color: white;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .no-image-placeholder {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 2rem;
        }

        .file-upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
            background: #f8f9fa;
            transition: var(--transition);
        }

        .file-upload-area:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }

        .file-upload-area input[type="file"]:focus + label,
        .file-upload-area:focus-within {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }

        .floating-action {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            background: var(--primary-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            transition: var(--transition);
            z-index: 1000;
            text-decoration: none;
        }

        .floating-action:hover {
            transform: translateY(-5px) scale(1.1);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.6);
            color: white;
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 1rem;
            }

            .page-header h2 {
                font-size: 2rem;
            }

            .card-body-modern {
                padding: 1.5rem;
            }

            .table-responsive {
                font-size: 0.875rem;
            }

            .img-thumbnail-modern {
                width: 60px;
                height: 60px;
            }

            .floating-action {
                width: 50px;
                height: 50px;
                font-size: 1.25rem;
            }
        }

        .form-row-modern {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .artist-info {
            font-weight: 600;
            color: #2c3e50;
        }

        .location-info {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .date-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        /* Smooth scroll behavior */
        html {
            scroll-behavior: smooth;
        }

        /* Enhanced focus states for accessibility */
        .form-control:focus,
        .btn:focus,
        .btn-back:focus,
        .btn-action:focus {
            outline: 2px solid #667eea;
            outline-offset: 2px;
        }

        /* Animation for file upload feedback */
        .file-upload-area input[type="file"]:valid {
            border-color: #28a745;
            background-color: rgba(40, 167, 69, 0.1);
        }

        /* CSS-only loading state simulation */
        .btn-primary-modern:active {
            transform: scale(0.98);
        }

        /* Enhanced table interactions */
        .table-modern tbody tr:nth-child(odd) {
            background-color: rgba(248, 249, 250, 0.5);
        }

        .table-modern tbody tr:nth-child(even):hover {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        /* Delete confirmation styling */
        .btn-danger-modern:active {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }

        /* Empty state styling */
        .empty-state {
            padding: 3rem 1rem;
        }

        .empty-state i {
            opacity: 0.3;
        }

        .empty-state h5,
        .empty-state p {
            opacity: 0.7;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="container">
            <div class="page-header">
                <h2><i class="bi bi-music-note-beamed me-3"></i>Kelola Konser</h2>
                <p>Manajemen dan administrasi konser musik</p>
            </div>

            <a href="dashboard.php" class="btn-back mb-4">
                <i class="bi bi-arrow-left-circle-fill me-2"></i>Kembali ke Dashboard
            </a>

            <!-- SweetAlert notifications - This is the key fix -->
            <?php if (!empty($success)): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: '<?= addslashes($success) ?>',
                            confirmButtonColor: '#667eea',
                            confirmButtonText: 'OK'
                        });
                    });
                </script>
            <?php elseif (!empty($error)): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: '<?= addslashes($error) ?>',
                            confirmButtonColor: '#dc3545',
                            confirmButtonText: 'OK'
                        });
                    });
                </script>
            <?php endif; ?>

            <div class="card-modern">
                <div class="card-header-modern">
                    <h4>
                        <i class="bi bi-<?= $edit_mode ? 'pencil-square' : 'plus-circle' ?>"></i>
                        <?= $edit_mode ? 'Edit Konser' : 'Tambah Konser Baru' ?>
                    </h4>
                </div>
                <div class="card-body-modern">
                    <form method="POST" enctype="multipart/form-data">
                        <?php if ($edit_mode): ?>
                            <input type="hidden" name="id" value="<?= $id ?>">
                        <?php endif; ?>
                        
                        <div class="form-row-modern">
                            <div>
                                <label class="form-label">
                                    <i class="bi bi-person-fill me-2"></i>Nama Artis
                                </label>
                                <input type="text" name="artist" class="form-control" 
                                       value="<?= htmlspecialchars($artist) ?>" 
                                       placeholder="Masukkan nama artis atau band"
                                       required>
                            </div>
                            <div>
                                <label class="form-label">
                                    <i class="bi bi-geo-alt-fill me-2"></i>Lokasi Konser
                                </label>
                                <input type="text" name="location" class="form-control" 
                                       value="<?= htmlspecialchars($location) ?>" 
                                       placeholder="Masukkan lokasi venue"
                                       required>
                            </div>
                        </div>

                        <div class="form-row-modern">
                            <div>
                                <label class="form-label">
                                    <i class="bi bi-calendar-date me-2"></i>Tanggal Konser
                                </label>
                                <input type="date" name="date" class="form-control" 
                                       value="<?= $date ?>" required>
                            </div>
                            <div>
                                <label class="form-label">
                                    <i class="bi bi-image me-2"></i>Gambar Konser
                                </label>
                                <div class="file-upload-area">
                                    <input type="file" name="image" accept="image/*" class="form-control">
                                    <?php if ($edit_mode && $image): ?>
                                        <small class="text-muted mt-2 d-block">
                                            <i class="bi bi-info-circle me-1"></i>
                                            File saat ini: <strong><?= htmlspecialchars($image) ?></strong>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">
                                <i class="bi bi-card-text me-2"></i>Deskripsi Konser
                            </label>
                            <textarea name="description" class="form-control" rows="4" 
                                      placeholder="Ceritakan tentang konser, artis, dan hal menarik lainnya..."><?= htmlspecialchars($desc) ?></textarea>
                        </div>

                        <button type="submit" name="<?= $edit_mode ? 'update_konser' : 'tambah_konser' ?>" 
                                class="btn btn-primary-modern">
                            <i class="bi bi-<?= $edit_mode ? 'arrow-repeat' : 'plus-lg' ?> me-2"></i>
                            <?= $edit_mode ? 'Update Konser' : 'Tambah Konser' ?>
                        </button>
                    </form>
                </div>
            </div>

            <div class="table-container">
                <div class="table-header">
                    <h4><i class="bi bi-calendar-event me-2"></i>Daftar Konser</h4>
                </div>
                <div class="table-responsive">
                    <table class="table table-modern">
                        <thead>
                            <tr>
                                <th>Gambar</th>
                                <th>Artis & Lokasi</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($konser->num_rows > 0): ?>
                                <?php while ($row = $konser->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <?php if ($row['image']): ?>
                                                <img src="../uploads/<?= htmlspecialchars($row['image']) ?>" 
                                                     class="img-thumbnail-modern"
                                                     alt="<?= htmlspecialchars($row['artist']) ?>">
                                            <?php else: ?>
                                                <div class="no-image-placeholder">
                                                    <i class="bi bi-image"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="artist-info"><?= htmlspecialchars($row['artist']) ?></div>
                                            <div class="location-info">
                                                <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($row['location']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="date-badge">
                                                <i class="bi bi-calendar-check me-1"></i>
                                                <?= date('d M Y', strtotime($row['date'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge-modern">
                                                <i class="bi bi-check-circle me-1"></i><?= $row['status'] ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="?edit=<?= $row['id'] ?>" 
                                               class="btn btn-warning-modern btn-action"
                                               title="Edit Konser">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <a href="?hapus=<?= $row['id'] ?>" 
                                               class="btn btn-danger-modern btn-action"
                                               onclick="confirmDelete(<?= $row['id'] ?>); return false;"
                                               title="Hapus Konser">
                                                <i class="bi bi-trash3-fill"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <div class="empty-state">
                                            <i class="bi bi-music-note-list display-1 text-muted mb-3"></i>
                                            <h5 class="text-muted">Belum Ada Konser</h5>
                                            <p class="text-muted">Mulai tambahkan konser pertama Anda</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete success notification -->
    <?php if ($delete_success): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Terhapus!',
                    text: 'Konser berhasil dihapus.',
                    confirmButtonColor: '#667eea',
                    confirmButtonText: 'OK'
                });
            });
        </script>
    <?php endif; ?>

    <a href="#" class="floating-action" title="Scroll to Top">
        <i class="bi bi-arrow-up"></i>
    </a>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Lanjutan dari bagian SweetAlert JavaScript

        function confirmDelete(id) {
            Swal.fire({
                title: 'Yakin ingin menghapus?',
                text: "Data konser akan dihapus permanen!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return new Promise((resolve) => {
                        setTimeout(() => {
                            resolve();
                        }, 1000);
                    });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading alert
                    Swal.fire({
                        title: 'Menghapus...',
                        text: 'Mohon tunggu sebentar',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Redirect to delete URL
                    window.location.href = '?hapus=' + id;
                }
            });
        }

        // Add loading state to form submissions
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const isUpdate = submitBtn.name === 'update_konser';
                    const isAdd = submitBtn.name === 'tambah_konser';
                    
                    if (isUpdate || isAdd) {
                        // Show loading alert
                        setTimeout(() => {
                            Swal.fire({
                                title: isUpdate ? 'Memperbarui...' : 'Menambahkan...',
                                text: 'Mohon tunggu sebentar',
                                allowOutsideClick: false,
                                allowEscapeKey: false,
                                showConfirmButton: false,
                                didOpen: () => {
                                    Swal.showLoading();
                                }
                            });
                        }, 100);
                    }
                });
            });

            // Smooth scroll to top function for floating action button
            const floatingBtn = document.querySelector('.floating-action');
            if (floatingBtn) {
                floatingBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });
            }

            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert-modern');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease-out';
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.parentNode.removeChild(alert);
                        }
                    }, 500);
                }, 5000);
            });

            // Form validation enhancement
            const requiredFields = document.querySelectorAll('input[required], textarea[required]');
            requiredFields.forEach(field => {
                field.addEventListener('blur', function() {
                    if (this.value.trim() === '') {
                        this.style.borderColor = '#dc3545';
                        this.style.boxShadow = '0 0 0 0.2rem rgba(220, 53, 69, 0.25)';
                    } else {
                        this.style.borderColor = '#28a745';
                        this.style.boxShadow = '0 0 0 0.2rem rgba(40, 167, 69, 0.25)';
                    }
                });

                field.addEventListener('input', function() {
                    if (this.value.trim() !== '') {
                        this.style.borderColor = '#28a745';
                        this.style.boxShadow = '0 0 0 0.2rem rgba(40, 167, 69, 0.25)';
                    }
                });
            });

            // File upload preview
            const fileInput = document.querySelector('input[type="file"]');
            if (fileInput) {
                fileInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        if (file.type.startsWith('image/')) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                // Create preview if doesn't exist
                                let preview = document.querySelector('.file-preview');
                                if (!preview) {
                                    preview = document.createElement('div');
                                    preview.className = 'file-preview mt-3';
                                    fileInput.parentNode.appendChild(preview);
                                }
                                
                                preview.innerHTML = `
                                    <div class="d-flex align-items-center">
                                        <img src="${e.target.result}" 
                                             class="img-thumbnail me-3" 
                                             style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px;">
                                        <div>
                                            <small class="text-success">
                                                <i class="bi bi-check-circle me-1"></i>
                                                File berhasil dipilih: <strong>${file.name}</strong>
                                            </small>
                                        </div>
                                    </div>
                                `;
                            };
                            reader.readAsDataURL(file);
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'File Tidak Valid',
                                text: 'Mohon pilih file gambar (JPG, PNG, GIF)',
                                confirmButtonColor: '#dc3545'
                            });
                            fileInput.value = '';
                        }
                    }
                });
            }

            // Add confirmation for edit mode cancellation
            const currentUrl = new URL(window.location.href);
            if (currentUrl.searchParams.has('edit')) {
                const backBtn = document.querySelector('.btn-back');
                if (backBtn) {
                    backBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        
                        Swal.fire({
                            title: 'Batalkan Edit?',
                            text: "Perubahan yang belum disimpan akan hilang!",
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#6c757d',
                            cancelButtonColor: '#667eea',
                            confirmButtonText: 'Ya, Batalkan',
                            cancelButtonText: 'Lanjut Edit',
                            reverseButtons: true
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = 'dashboard.php';
                            }
                        });
                    });
                }
            }
        });

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + S to submit form
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                const submitBtn = document.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.click();
                }
            }
            
            // Escape to cancel edit mode
            if (e.key === 'Escape') {
                const currentUrl = new URL(window.location.href);
                if (currentUrl.searchParams.has('edit')) {
                    const backBtn = document.querySelector('.btn-back');
                    if (backBtn) {
                        backBtn.click();
                    }
                }
            }
        });

        // Add tooltip functionality
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>