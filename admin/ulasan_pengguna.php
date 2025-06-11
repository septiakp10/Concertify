<?php 
$currentPage = 'ulasan'; 
include '../config/db.php';

// Handle delete request
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $delete_query = "DELETE FROM reviews WHERE id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        $delete_success = true;
    } else {
        $delete_error = true;
    }
    $stmt->close();
}

$ulasan = $conn->query("SELECT id, nama, ulasan, rating, created_at FROM reviews ORDER BY created_at DESC LIMIT 10"); 
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Ulasan Pengguna</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- SweetAlert2 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f8f9fa;
    }
    .card-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: none;
    }
    .btn-delete {
      background-color: #dc3545;
      color: white;
      border: none;
      border-radius: 6px;
      padding: 6px 10px;
    }
    .btn-delete:hover {
      background-color: #c82333;
      color: white;
    }
    .status-available {
      background-color: #20c997;
      color: white;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.85em;
    }
    .card {
      border: none;
      box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
  </style>
</head>
<body>
  <div class="container my-5">
    <!-- Tombol Kembali di atas -->
    <div class="mb-3">
      <a href="dashboard.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left-circle me-1"></i> Kembali ke Dashboard
      </a>
    </div>

    <h3 class="mb-4"><i class="bi bi-chat-dots me-2"></i>Ulasan Pengguna</h3>

    <div class="card">
      <div class="card-header">
        <h4 class="mb-0"><i class="bi bi-chat-heart me-2"></i>Daftar Ulasan</h4>
      </div>
      <div class="card-body p-0">
        <?php if ($ulasan->num_rows > 0): ?>
          <div class="table-responsive">
            <table class="table table-striped">
              <thead class="table-light">
                <tr>
                  <th>Nama</th>
                  <th>Ulasan</th>
                  <th>Rating</th>
                  <th>Waktu</th>
                  <th width="80" class="text-center">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($r = $ulasan->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($r['nama']) ?></td>
                  <td><?= nl2br(htmlspecialchars($r['ulasan'])) ?></td>
                  <td>
                    <?php
                    for ($i = 1; $i <= 5; $i++) {
                      echo $i <= $r['rating']
                        ? '<i class="bi bi-star-fill text-warning"></i>'
                        : '<i class="bi bi-star text-muted"></i>';
                    }
                    ?>
                  </td>
                  <td><?= date('d M Y H:i', strtotime($r['created_at'])) ?></td>
                  <td class="text-center">
                    <button type="button" 
                            class="btn btn-delete btn-sm" 
                            onclick="confirmDelete(<?= $r['id'] ?>)"
                            title="Hapus ulasan">
                      <i class="bi bi-trash"></i>
                    </button>
                  </td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="p-4 text-center text-muted">
            <i class="bi bi-chat-dots display-5 d-block mb-2"></i>
            <p class="mb-0">Belum ada ulasan dari pengguna.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- SweetAlert2 JS -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
  
  <script>
    // Function untuk konfirmasi delete
    function confirmDelete(id) {
      Swal.fire({
        title: 'Hapus Ulasan?',
        text: 'Apakah Anda yakin ingin menghapus ulasan ini? Data yang dihapus tidak dapat dikembalikan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        reverseButtons: true
      }).then((result) => {
        if (result.isConfirmed) {
          // Redirect ke URL delete
          window.location.href = '?delete_id=' + id;
        }
      });
    }

    <?php if (isset($delete_success) && $delete_success): ?>
    // SweetAlert untuk berhasil hapus
    Swal.fire({
      title: 'Berhasil!',
      text: 'Ulasan berhasil dihapus.',
      icon: 'success',
      confirmButtonColor: '#28a745',
      confirmButtonText: 'OK'
    }).then((result) => {
      // Redirect ke halaman ulasan tanpa parameter
      window.location.href = 'ulasan_pengguna.php';
    });
    <?php endif; ?>

    <?php if (isset($delete_error) && $delete_error): ?>
    // SweetAlert untuk error hapus
    Swal.fire({
      title: 'Gagal!',
      text: 'Gagal menghapus ulasan. Silakan coba lagi.',
      icon: 'error',
      confirmButtonColor: '#dc3545',
      confirmButtonText: 'OK'
    });
    <?php endif; ?>
  </script>
</body>
</html>