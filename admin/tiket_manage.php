<?php
include '../config/db.php';

$edit_mode = false;
$concert_id = $category = $price = $stock = '';
$concerts = $conn->query("SELECT id, artist FROM concerts ORDER BY date DESC");

// Cek mode edit
if (isset($_GET['edit'])) {
    $edit_mode = true;
    $id = intval($_GET['edit']);
    $result = $conn->query("SELECT * FROM ticket_categories WHERE id = $id");
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $concert_id = $row['concert_id'];
        $category = $row['category'];
        $price = $row['price'];
        $stock = $row['stock'];
    } else {
        $error = "Data tiket tidak ditemukan.";
    }
}

// Tambah atau update tiket
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $concert_id = $_POST['concert_id'];
    $category = trim($_POST['category']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);

    if ($concert_id && $category && $price > 0 && $stock >= 0) {
        if (isset($_POST['update_tiket'])) {
            $id = intval($_POST['id']);
            $stmt = $conn->prepare("UPDATE ticket_categories SET concert_id=?, category=?, price=?, stock=? WHERE id=?");
            $stmt->bind_param("isdii", $concert_id, $category, $price, $stock, $id);
            if ($stmt->execute()) {
                $success = "update";
                $success_message = "Tiket berhasil diperbarui!";
                $edit_mode = false;
            } else {
                $error = "Gagal memperbarui tiket.";
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO ticket_categories (concert_id, category, price, stock) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isdi", $concert_id, $category, $price, $stock);
            if ($stmt->execute()) {
                $success = "add";
                $success_message = "Tiket berhasil ditambahkan!";
            } else {
                $error = "Gagal menambahkan tiket.";
            }
        }
    } else {
        $error = "Mohon isi semua kolom dengan benar.";
    }
}

// Hapus tiket
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    if ($conn->query("DELETE FROM ticket_categories WHERE id = $id")) {
        $success = "delete";
        $success_message = "Tiket berhasil dihapus!";
    } else {
        $error = "Gagal menghapus tiket.";
    }
}

// Ambil semua tiket
$tickets = $conn->query("
    SELECT t.id, c.artist, t.category, t.price, t.stock
    FROM ticket_categories t
    JOIN concerts c ON t.concert_id = c.id
    ORDER BY t.id DESC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Kelola Tiket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.10.1/sweetalert2.all.min.js"></script>
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
            padding: 0;
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
            box-shadow: var(--card-shadow);
            margin: 2rem auto;
            max-width: 1200px;
            overflow: hidden;
        }

        .page-header {
            background: var(--primary-gradient);
            color: white;
            padding: 2.5rem 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translate(-50%, -50%) rotate(0deg); }
            50% { transform: translate(-50%, -50%) rotate(180deg); }
        }

        .page-header h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }

        .page-header i {
            margin-right: 1rem;
            font-size: 2.2rem;
        }

        .container-content {
            padding: 2.5rem;
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
            margin-bottom: 2rem;
        }

        .btn-back:hover {
            background: var(--primary-gradient);
            color: white;
            border-color: transparent;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
            text-decoration: none;
        }

        .btn-back i {
            margin-right: 0.5rem;
        }

        .card-modern {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            border: none;
            overflow: hidden;
            margin-bottom: 2.5rem;
        }

        .card-header-modern {
            background: var(--primary-gradient);
            color: white;
            padding: 1.5rem 2rem;
            border: none;
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
        }

        .form-control,
        .form-select {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1rem;
            font-size: 1rem;
            transition: var(--transition);
            background: #f8f9fa;
        }

        .form-control:focus,
        .form-select:focus {
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
        }

        .btn-primary-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            color: white;
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

        .section-title {
            color: #2c3e50;
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            position: relative;
        }

        .section-title::after {
            content: '';
            flex: 1;
            height: 2px;
            background: linear-gradient(90deg, #667eea, transparent);
            margin-left: 1rem;
        }

        .section-title i {
            margin-right: 0.75rem;
            color: #667eea;
        }

        .table-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .table-modern {
            margin: 0;
            border: none;
        }

        .table-modern thead th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: none;
            padding: 1.5rem 1rem;
            font-weight: 600;
            color: #495057;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.5px;
        }

        .table-modern thead th i {
            margin-right: 0.5rem;
            color: #667eea;
        }

        .table-modern tbody td {
            padding: 1.5rem 1rem;
            border-top: 1px solid #f3f4f6;
            vertical-align: middle;
        }

        .table-modern tbody tr {
            transition: var(--transition);
        }

        .table-modern tbody tr:hover {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            transform: translateX(5px);
        }

        .artist-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .category-tag {
            background: var(--primary-gradient);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }

        .price-display {
            font-weight: 600;
            color: #10b981;
            font-size: 1.1rem;
        }

        .stock-badge {
            background: var(--success-gradient);
            color: #2c3e50;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }

        .btn-action {
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            margin: 0 0.25rem;
            transition: var(--transition);
            border: none;
            font-size: 0.875rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .btn-edit {
            background: var(--warning-gradient);
            color: #2c3e50;
        }

        .btn-delete {
            background: var(--danger-gradient);
            color: white;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            text-decoration: none;
        }

        .btn-edit:hover {
            color: #2c3e50;
        }

        .btn-delete:hover {
            color: white;
        }

        .btn-action i {
            margin-right: 0.5rem;
        }

        @media (max-width: 768px) {
            .main-container {
                margin: 1rem;
                border-radius: 15px;
            }

            .page-header {
                padding: 2rem 1.5rem;
            }

            .page-header h2 {
                font-size: 2rem;
            }

            .container-content {
                padding: 1.5rem;
            }

            .card-body-modern {
                padding: 1.5rem;
            }

            .table-responsive {
                font-size: 0.875rem;
            }

            .btn-action {
                padding: 0.4rem 0.6rem;
                font-size: 0.8rem;
                margin: 0.1rem;
            }
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        /* Custom SweetAlert2 styling */
        .swal2-popup {
            border-radius: 20px;
            font-family: 'Inter', sans-serif;
        }

        .swal2-title {
            font-weight: 700;
        }

        .swal2-confirm {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            border-radius: 12px;
            font-weight: 600;
        }

        .swal2-cancel {
            border-radius: 12px;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="main-container">
    <div class="page-header">
        <h2>
            <i class="fas fa-ticket-alt"></i>
            Kelola Kategori Tiket
        </h2>
    </div>
    
    <div class="container-content">
        <a href="javascript:history.back()" class="btn-back">
            <i class="fas fa-arrow-left"></i>
            Kembali
        </a>

        <div class="card-modern">
            <div class="card-header-modern">
                <h4>
                    <i class="fas fa-<?= $edit_mode ? 'edit' : 'plus' ?>"></i>
                    <?= $edit_mode ? 'Edit Kategori Tiket' : 'Tambah Kategori Tiket' ?>
                </h4>
            </div>
            <div class="card-body-modern">
                <form method="POST" id="ticketForm">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="id" value="<?= $_GET['edit'] ?>">
                    <?php endif; ?>
                    <div class="form-row">
                        <div>
                            <label class="form-label">
                                <i class="fas fa-music"></i>
                                Pilih Konser
                            </label>
                            <select name="concert_id" class="form-select" required>
                                <option value="">-- Pilih Konser --</option>
                                <?php foreach ($concerts as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $c['id'] == $concert_id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['artist']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">
                                <i class="fas fa-tag"></i>
                                Kategori Tiket
                            </label>
                            <input type="text" name="category" class="form-control" value="<?= htmlspecialchars($category) ?>" placeholder="VIP, Reguler, Early Bird" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div>
                            <label class="form-label">
                                <i class="fas fa-dollar-sign"></i>
                                Harga Tiket (Rp)
                            </label>
                            <input type="number" name="price" class="form-control" value="<?= $price ?>" step="1000" required>
                        </div>
                        <div>
                            <label class="form-label">
                                <i class="fas fa-boxes"></i>
                                Jumlah Stok
                            </label>
                            <input type="number" name="stock" class="form-control" value="<?= $stock ?>" required>
                        </div>
                    </div>
                    <button type="submit" name="<?= $edit_mode ? 'update_tiket' : 'tambah_tiket' ?>" class="btn-primary-modern">
                        <i class="fas fa-<?= $edit_mode ? 'save' : 'plus' ?>"></i>
                        <?= $edit_mode ? 'Update Tiket' : 'Tambah Tiket' ?>
                    </button>
                </form>
            </div>
        </div>

        <h4 class="section-title">
            <i class="fas fa-list"></i>
            Daftar Tiket
        </h4>
        
        <div class="table-container">
            <div class="table-responsive">
                <table class="table-modern table align-middle">
                    <thead>
                        <tr>
                            <th><i class="fas fa-music"></i>Konser</th>
                            <th><i class="fas fa-tag"></i>Kategori</th>
                            <th><i class="fas fa-dollar-sign"></i>Harga</th>
                            <th><i class="fas fa-boxes"></i>Stok</th>
                            <th class="text-center"><i class="fas fa-cogs"></i>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($t = $tickets->fetch_assoc()): ?>
                        <tr>
                            <td class="artist-name"><?= htmlspecialchars($t['artist']) ?></td>
                            <td>
                                <span class="category-tag"><?= htmlspecialchars($t['category']) ?></span>
                            </td>
                            <td class="price-display">Rp<?= number_format($t['price'], 0, ',', '.') ?></td>
                            <td>
                                <span class="stock-badge"><?= $t['stock'] ?></span>
                            </td>
                            <td class="text-center">
                                <a href="?edit=<?= $t['id'] ?>" class="btn-action btn-edit">
                                    <i class="fas fa-edit"></i>
                                    Edit
                                </a>
                                <button onclick="confirmDelete(<?= $t['id'] ?>, '<?= htmlspecialchars($t['category']) ?>')" class="btn-action btn-delete">
                                    <i class="fas fa-trash"></i>
                                    Hapus
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Function untuk konfirmasi delete dengan SweetAlert
function confirmDelete(id, category) {
    Swal.fire({
        title: 'Hapus Tiket?',
        text: `Apakah Anda yakin ingin menghapus tiket kategori "${category}"? Tindakan ini tidak dapat dibatalkan.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-trash"></i> Ya, Hapus!',
        cancelButtonText: '<i class="fas fa-times"></i> Batal',
        reverseButtons: true,
        customClass: {
            confirmButton: 'btn btn-danger mx-2',
            cancelButton: 'btn btn-secondary mx-2'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            // Redirect ke URL hapus
            window.location.href = `?hapus=${id}`;
        }
    });
}

// Show success alert berdasarkan PHP response
<?php if (isset($success)): ?>
    <?php if ($success == 'add'): ?>
        Swal.fire({
            title: 'Berhasil!',
            text: '<?= $success_message ?>',
            icon: 'success',
            confirmButtonColor: '#28a745',
            confirmButtonText: '<i class="fas fa-check"></i> OK',
            customClass: {
                confirmButton: 'btn btn-success'
            },
            buttonsStyling: false
        });
    <?php elseif ($success == 'update'): ?>
        Swal.fire({
            title: 'Berhasil!',
            text: '<?= $success_message ?>',
            icon: 'success',
            confirmButtonColor: '#28a745',
            confirmButtonText: '<i class="fas fa-check"></i> OK',
            customClass: {
                confirmButton: 'btn btn-success'
            },
            buttonsStyling: false
        });
    <?php elseif ($success == 'delete'): ?>
        Swal.fire({
            title: 'Berhasil!',
            text: '<?= $success_message ?>',
            icon: 'success',
            confirmButtonColor: '#28a745',
            confirmButtonText: '<i class="fas fa-check"></i> OK',
            customClass: {
                confirmButton: 'btn btn-success'
            },
            buttonsStyling: false
        });
    <?php endif; ?>
<?php endif; ?>

// Show error alert
<?php if (isset($error)): ?>
    Swal.fire({
        title: 'Oops!',
        text: '<?= $error ?>',
        icon: 'error',
        confirmButtonColor: '#dc3545',
        confirmButtonText: '<i class="fas fa-times"></i> OK',
        customClass: {
            confirmButton: 'btn btn-danger'
        },
        buttonsStyling: false
    });
<?php endif; ?>

// Form validation dengan SweetAlert
document.getElementById('ticketForm').addEventListener('submit', function(e) {
    const concertId = document.querySelector('select[name="concert_id"]').value;
    const category = document.querySelector('input[name="category"]').value.trim();
    const price = document.querySelector('input[name="price"]').value;
    const stock = document.querySelector('input[name="stock"]').value;

    if (!concertId || !category || !price || price <= 0 || !stock || stock < 0) {
        e.preventDefault();
        Swal.fire({
            title: 'Form Tidak Lengkap!',
            text: 'Mohon isi semua kolom dengan benar. Harga harus lebih dari 0 dan stok tidak boleh negatif.',
            icon: 'warning',
            confirmButtonColor: '#ffc107',
            confirmButtonText: '<i class="fas fa-exclamation-triangle"></i> OK',
            customClass: {
                confirmButton: 'btn btn-warning'
            },
            buttonsStyling: false
        });
    }
});
</script>

</body>
</html>