<?php
session_start();
include '../config/db.php';

$currentPage = 'dashboard';

// Proses konfirmasi manual (untuk backward compatibility) - DIPERBAIKI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order'])) {
    $order_id = intval($_POST['order_id']);

    // Start transaction untuk memastikan konsistensi data
    $conn->begin_transaction();
    
    try {
        // Update status di tabel orders
        $update = $conn->prepare("UPDATE orders SET status = 'Dikonfirmasi' WHERE id = ?");
        $update->bind_param("i", $order_id);
        $update->execute();
        
        
        // Commit transaction
        $conn->commit();
        
        // Set session message untuk feedback
        session_start();
        $_SESSION['success_message'] = "Order #$order_id berhasil dikonfirmasi";
        
    } catch (Exception $e) {
        // Rollback jika ada error
        $conn->rollback();
        session_start();
        $_SESSION['error_message'] = "Gagal mengkonfirmasi order: " . $e->getMessage();
    }
}

// Ambil data pembelian tiket dengan status yang lebih lengkap
$pembelian = $conn->query("
    SELECT o.id, u.name AS pembeli, c.artist, c.date, t.category, o.quantity, o.total_price, o.status, o.payment_method, o.payment_proof, o.created_at
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN concerts c ON o.concert_id = c.id
    JOIN ticket_categories t ON o.category_id = t.id
    ORDER BY o.created_at DESC
");

// Ambil statistik untuk dashboard
$stats = [];
$stats['total_orders'] = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'];
$stats['pending_verification'] = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status IN ('Menunggu Verifikasi', 'Menunggu Pembayaran', 'Menunggu Konfirmasi')")->fetch_assoc()['count'];
$stats['confirmed'] = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status IN ('Dikonfirmasi', 'Berhasil')")->fetch_assoc()['count'];
$stats['total_revenue'] = $conn->query("SELECT SUM(total_price) as total FROM orders WHERE status IN ('Dikonfirmasi', 'Berhasil')")->fetch_assoc()['total'] ?? 0;

// Ambil data ulasan pengguna dari tabel 'reviews'
$ulasan = $conn->query("
    SELECT nama, ulasan, rating, created_at
    FROM reviews
    ORDER BY created_at DESC
    LIMIT 10
");

$ulasanCount = 0;
$ulasanResult = $conn->query("SELECT COUNT(*) as total FROM reviews");
if ($ulasanResult && $ulasanResult->num_rows > 0) {
    $row = $ulasanResult->fetch_assoc();
    $ulasanCount = $row['total'];
}

// Start session untuk menampilkan pesan
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Concert Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
   <style>
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --warning-gradient: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
    --success-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
    --info-gradient: linear-gradient(135deg, #d299c2 0%, #fef9d7 100%);
    --danger-gradient: linear-gradient(135deg, #ff758c 0%, #ff7eb3 100%);
    --card-shadow: 0 10px 30px rgba(0,0,0,0.1);
    --border-radius: 16px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
    color: #2c3e50;
}

.sidebar {
    background-color: #f8f9fa;
    padding: 20px;
    width: 280px;
    min-height: 100vh;
    box-shadow: 4px 0 20px rgba(0, 0, 0, 0.05);
    position: fixed;
    overflow-y: auto;
    z-index: 1000;
    transition: var(--transition);
}

.sidebar::-webkit-scrollbar {
    width: 6px;
}
.sidebar::-webkit-scrollbar-track {
    background: rgba(0,0,0,0.03);
}
.sidebar::-webkit-scrollbar-thumb {
    background: rgba(0,0,0,0.1);
    border-radius: 3px;
}

.sidebar-header {
    padding: 2rem 1.5rem;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    text-align: center;
}

.sidebar-header h4 {
    color: #7b2cbf;
    font-weight: bold;
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

.sidebar-header p {
    color: #6c757d;
    font-size: 0.9rem;
}

.sidebar-menu {
    padding: 1rem 0;
}

.sidebar a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 15px;
    margin: 6px 0;
    border-radius: 10px;
    color: #343a40;
    text-decoration: none;
    font-weight: 500;
    transition: var(--transition);
    border-left: 3px solid transparent;
}

.sidebar a:hover {
    background-color: #e6ddf7;
    color: #7b2cbf;
    border-left-color: #7b2cbf;
    transform: translateX(5px);
}

.sidebar a.active {
    background-color: #d0aaff;
    color: white;
    font-weight: bold;
    border-left-color: #7b2cbf;
}

.sidebar a i {
    width: 20px;
    margin-right: 0.75rem;
}

.main-content {
    margin-left: 280px;
    min-height: 100vh;
    padding: 2rem;
    transition: var(--transition);
}

.page-header {
    background: white;
    padding: 2rem;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    margin-bottom: 2rem;
    border: 1px solid rgba(0,0,0,0.05);
}

.page-header h2 {
    color: #2c3e50;
    font-weight: 700;
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.page-header p {
    color: #7f8c8d;
    margin: 0;
    font-size: 1.1rem;
}

.stat-card {
    background: white;
    border-radius: var(--border-radius);
    padding: 1.5rem;  
    margin-bottom: 2rem;
    box-shadow: var(--card-shadow);
    border: 1px solid rgba(0,0,0,0.05);
    position: relative;
    overflow: hidden;
    transition: var(--transition);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--primary-gradient);
}

.stat-card.warning::before { background: var(--warning-gradient); }
.stat-card.success::before { background: var(--success-gradient); }
.stat-card.info::before { background: var(--info-gradient); }

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
}

.stat-icon {
    width: 45px;  
    height: 45px;     
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;  
    color: white;
    margin-bottom: 0.8rem; 
}

.stat-card .stat-icon { background: var(--primary-gradient); }
.stat-card.warning .stat-icon { background: var(--warning-gradient); }
.stat-card.success .stat-icon { background: var(--success-gradient); }
.stat-card.info .stat-icon { background: var(--info-gradient); }

.stat-number {
    font-size: 1.8rem; 
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 0.3rem;
}

.stat-label {
    color: #7f8c8d;
    font-weight: 500;
    font-size: 0.85rem;  
    margin: 0;
}

.card-modern {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    border: 1px solid rgba(0,0,0,0.05);
    overflow: hidden;
    margin-bottom: 2rem;
}

.card-header-modern {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 1.5rem 2rem;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}

.card-header-modern h3 {
    color: #2c3e50;
    font-weight: 600;
    font-size: 1.25rem;
    margin: 0;
}

.table-modern {
    margin: 0;
}

.table-modern thead th {
    background: #f8f9fa;
    border: none;
    padding: 1rem;
    font-weight: 600;
    color: #495057;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.table-modern tbody td {
    padding: 1rem;
    border-top: 1px solid #e9ecef;
    vertical-align: middle;
}

.table-modern tbody tr {
    transition: var(--transition);
}

.table-modern tbody tr:hover {
    background: #f8f9fa;
}

.badge {
    padding: 0.5rem 1rem;
    font-weight: 500;
    font-size: 0.75rem;
}

.btn {
    border-radius: 8px;
    padding: 0.5rem 1rem;
    font-weight: 500;
    transition: var(--transition);
    border: none;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.btn-primary {
    background: var(--primary-gradient);
    color: white;
}

.btn-success {
    background: var(--success-gradient);
    color: #2c3e50;
}

.btn-warning {
    background: var(--warning-gradient);
    color: #2c3e50;
}

.alert-modern {
    border: none;
    border-radius: var(--border-radius);
    padding: 1.5rem;
    box-shadow: var(--card-shadow);
    margin-bottom: 2rem;
}

.alert-warning {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
    color: #856404;
    border-left: 4px solid #ffc107;
}

.alert-info {
    background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
    color: #0c5460;
    border-left: 4px solid #17a2b8;
}

.alert-secondary {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    color: #383d41;
    border-left: 4px solid #6c757d;
}

.alert-success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: #155724;
    border-left: 4px solid #28a745;
}

.alert-danger {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    color: #721c24;
    border-left: 4px solid #dc3545;
}

.notification-badge {
    font-size: 0.75rem;
    background-color: #d63384;
    position: relative;
    top: -2px;
    border: 2px solid white;
    box-shadow: 0 2px 8px rgba(255, 117, 140, 0.4);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.empty-state {
    text-align: center;
    padding: 3rem;
    color: #7f8c8d;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h4 {
    color: #95a5a6;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: #bdc3c7;
}

@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        width: 100%;
    }

    .main-content {
        margin-left: 0;
        padding: 1rem;
    }

    .stat-card {
        padding: 1rem;  
    }

    .stat-number {
        font-size: 1.5rem;  
    }
    
}

.revenue-highlight {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: 700;
}
</style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
        <h4><i class="bi bi-music-note-beamed me-2"></i>Concert Admin</h4>
        <p>Management Dashboard</p>
    </div>
    <div class="sidebar-menu">
        <a href="dashboard.php" class="<?= $currentPage == 'dashboard' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i>Dashboard
        </a>
        <a href="verifikasi_pembayaran.php" class="<?= $currentPage == 'payment_verification' ? 'active' : '' ?>">
            <i class="bi bi-shield-check"></i>Verifikasi Pembayaran
            <?php if ($stats['pending_verification'] > 0): ?>
                <span class="badge bg-danger notification-badge ms-auto"><?= $stats['pending_verification'] ?></span>
            <?php endif; ?>
        </a>
        <a href="konser_manage.php" class="<?= $currentPage == 'konser' ? 'active' : '' ?>">
            <i class="bi bi-music-note-list"></i>Kelola Konser
        </a>
        <a href="tiket_manage.php" class="<?= $currentPage == 'tiket' ? 'active' : '' ?>">
            <i class="bi bi-ticket-perforated"></i>Kelola Tiket
        </a>
        <a href="ulasan_pengguna.php" class="<?= $currentPage == 'ulasan' ? 'active' : '' ?>">
            <i class="bi bi-chat-dots"></i>Ulasan Pengguna
        </a>
        <a href="../auth/logout.php">
            <i class="bi bi-box-arrow-right"></i>Logout
        </a>
    </div>
</div>

<div class="main-content">
    <div class="page-header">
        <h2><i class="bi bi-speedometer2 me-3"></i>Dashboard Overview</h2>
        <p>Selamat datang di panel admin sistem manajemen tiket konser</p>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-modern">
        <div class="d-flex align-items-center">
            <i class="bi bi-check-circle-fill me-3 fs-4"></i>
            <div class="flex-grow-1">
                <?= htmlspecialchars($_SESSION['success_message']) ?>
            </div>
        </div>
    </div>
    <?php unset($_SESSION['success_message']); endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-modern">
        <div class="d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
            <div class="flex-grow-1">
                <?= htmlspecialchars($_SESSION['error_message']) ?>
            </div>
        </div>
    </div>
    <?php unset($_SESSION['error_message']); endif; ?>

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-cart-fill"></i>
                </div>
                <div class="stat-number"><?= $stats['total_orders'] ?></div>
                <p class="stat-label">Total Pemesanan</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="bi bi-clock-fill"></i>
                </div>
                <div class="stat-number"><?= $stats['pending_verification'] ?></div>
                <p class="stat-label">Menunggu Verifikasi</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div class="stat-number"><?= $stats['confirmed'] ?></div>
                <p class="stat-label">Dikonfirmasi</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="stat-card info">
                <div class="stat-icon">
                    <i class="bi bi-currency-dollar"></i>
                </div>
                <div class="revenue-highlight stat-number">Rp <?= number_format($stats['total_revenue'], 0, ',', '.') ?></div>
                <p class="stat-label">Total Pendapatan</p>
            </div>
        </div>
    </div>

    <!-- Quick Action Alert -->
    <?php if ($stats['pending_verification'] > 0): ?>
    <div class="alert alert-warning alert-modern">
        <div class="d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
            <div class="flex-grow-1">
                <strong>Perhatian!</strong> Ada <strong><?= $stats['pending_verification'] ?></strong> pembayaran yang perlu diverifikasi.
            </div>
            <a href="verifikasi_pembayaran.php" class="btn btn-warning">
                <i class="bi bi-shield-check me-2"></i>Verifikasi Sekarang
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Orders Table -->
    <div class="card-modern">
        <div class="card-header-modern">
            <h3><i class="bi bi-table me-2"></i>Data Pembelian Tiket</h3>
        </div>
        <div class="card-body p-0">
            <?php if ($pembelian->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-modern">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Pembeli</th>
                                <th>Konser</th>
                                <th>Tanggal</th>
                                <th>Kategori</th>
                                <th>Jumlah</th>
                                <th>Total</th>
                                <th>Metode</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $pembelian->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?= $row['id'] ?></strong></td>
                                <td><?= htmlspecialchars($row['pembeli']) ?></td>
                                <td><?= htmlspecialchars($row['artist']) ?></td>
                                <td><?= date('d M Y', strtotime($row['date'])) ?></td>
                                <td><?= $row['category'] ?></td>
                                <td><span class="badge bg-light text-dark"><?= $row['quantity'] ?></span></td>
                                <td><strong>Rp<?= number_format($row['total_price'], 0, ',', '.') ?></strong></td>
                                <td><?= htmlspecialchars($row['payment_method']) ?></td>
                                <td>
                                    <?php
                                    $badgeClass = 'secondary';
                                    switch($row['status']) {
                                        case 'Menunggu Pembayaran':
                                            $badgeClass = 'secondary';
                                            break;
                                        case 'Menunggu Verifikasi':
                                            $badgeClass = 'warning';
                                            break;
                                        case 'Dikonfirmasi':
                                        case 'Berhasil':
                                            $badgeClass = 'success';
                                            break;
                                        case 'Ditolak':
                                            $badgeClass = 'danger';
                                            break;
                                        case 'Menunggu Konfirmasi': // Old status
                                            $badgeClass = 'warning';
                                            break;
                                    }
                                    ?>
                                    <span class="badge bg-<?= $badgeClass ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (in_array($row['status'], ['Menunggu Verifikasi', 'Menunggu Pembayaran'])): ?>
                                        <a href="verifikasi_pembayaran.php" class="btn btn-sm btn-primary">
                                            <i class="bi bi-shield-check"></i> Verifikasi
                                        </a>
                                    <?php elseif ($row['status'] == 'Menunggu Konfirmasi'): ?>
                                        <!-- Backward compatibility -->
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin mengkonfirmasi order ini?')">
                                            <input type="hidden" name="order_id" value="<?= $row['id'] ?>">
                                            <button type="submit" name="confirm_order" class="btn btn-sm btn-success">
                                                <i class="bi bi-check"></i> Konfirmasi
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <h4>Belum Ada Data</h4>
                    <p>Belum ada pembelian tiket yang tercatat dalam sistem.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Add smooth scrolling and enhanced interactions
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects to stat cards
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });

    // Add smooth transitions to buttons
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(btn => {
        btn.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        btn.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease-out';
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.remove();
            }, 500);
        }, 5000);
    });
});
</script>
</body>
</html>