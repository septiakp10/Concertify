<?php
include '../config/db.php';

$currentPage = 'payment_verification';

// Handle payment verification
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify_payment'])) {
    $order_id = $_POST['order_id'];
    $action = $_POST['action']; // 'approve' or 'reject'
    $notes = $_POST['notes'] ?? '';
    
    if ($action === 'approve') {
        // Approve payment (stok sudah berkurang saat checkout)
        $stmt = $conn->prepare("UPDATE orders SET status = 'Dikonfirmasi', verified_at = NOW(), notes = ? WHERE id = ?");
        $stmt->bind_param("si", $notes, $order_id);
        if ($stmt->execute()) {
            $message = "Pembayaran berhasil diverifikasi dan tiket dikonfirmasi.";
            $showSuccessPopup = true;
        } else {
            $error = "Gagal memverifikasi pembayaran.";
        }
        
    } else {
        // Reject payment and restore stock
        $conn->begin_transaction();
        try {
            // Get order details - pastikan hanya order ini
            $stmt = $conn->prepare("SELECT quantity, category_id, status FROM orders WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            
            // Validasi order exists
            if (!$order) {
                throw new Exception("Order tidak ditemukan");
            }
            
            // Debug log - hapus setelah testing
            error_log("Menolak Order #$order_id: Quantity={$order['quantity']}, Status={$order['status']}");
            
            // Restore stock sesuai quantity order yang ditolak
            $stmt = $conn->prepare("UPDATE ticket_categories SET stock = stock + ? WHERE id = ?");
            $stmt->bind_param("ii", $order['quantity'], $order['category_id']);
            $stmt->execute();
            
            // Update order status
            $stmt = $conn->prepare("UPDATE orders SET status = 'Ditolak', verified_at = NOW(), notes = ? WHERE id = ?");
            $stmt->bind_param("si", $notes, $order_id);
            $stmt->execute();
            
            $conn->commit();
            $message = "Pembayaran ditolak dan stok tiket dikembalikan sebanyak " . $order['quantity'] . " tiket.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Get pending orders
$query = "SELECT o.*, u.name AS pembeli, u.email, c.artist, c.date, t.category, t.price
          FROM orders o 
          JOIN users u ON o.user_id = u.id 
          JOIN concerts c ON o.concert_id = c.id 
          JOIN ticket_categories t ON o.category_id = t.id 
          WHERE o.status IN ('Menunggu Verifikasi', 'Menunggu Pembayaran')
          ORDER BY o.created_at DESC";
$result = $conn->query($query);

// Get stats for notification badge
$stats = [];
$stats['pending_verification'] = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status IN ('Menunggu Verifikasi', 'Menunggu Pembayaran')")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Verifikasi Pembayaran - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

<div class="sidebar p-3">
    <h4 class="text-gray mb-4">Concert Admin</h4>
    <p>Management Dashboard</p>
    <a href="dashboard.php" class="<?= $currentPage == 'dashboard' ? 'active' : '' ?>">
        <i class="bi bi-speedometer2 me-2"></i>Dashboard
    </a>
    <a href="payment_verification.php" class="<?= $currentPage == 'payment_verification' ? 'active' : '' ?>">
        <i class="bi bi-shield-check me-2"></i>Verifikasi Pembayaran
        <?php if ($stats['pending_verification'] > 0): ?>
            <span class="badge bg-danger notification-badge ms-2"><?= $stats['pending_verification'] ?></span>
        <?php endif; ?>
    </a>
    <a href="konser_manage.php" class="<?= $currentPage == 'konser' ? 'active' : '' ?>">
        <i class="bi bi-music-note-list me-2"></i>Kelola Konser
    </a>
    <a href="tiket_manage.php" class="<?= $currentPage == 'tiket' ? 'active' : '' ?>">
        <i class="bi bi-ticket-perforated me-2"></i>Kelola Tiket
    </a>
    <a href="ulasan_pengguna.php" class="<?= $currentPage == 'ulasan' ? 'active' : '' ?>">
        <i class="bi bi-chat-dots me-2"></i>Ulasan Pengguna
    </a>
    <a href="../auth/logout.php">
        <i class="bi bi-box-arrow-right me-2"></i>Logout
    </a>
</div>

<div class="main-content p-4">
    <h2 class="mb-4"><i class="bi bi-shield-check me-2"></i>Verifikasi Pembayaran</h2>
    
    <?php if (isset($message) && !isset($showSuccessPopup)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($result->num_rows == 0): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>Tidak ada pembayaran yang perlu diverifikasi.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover bg-white shadow-sm">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Pembeli</th>
                        <th>Konser</th>
                        <th>Kategori</th>
                        <th>Qty</th>
                        <th>Total</th>
                        <th>Metode</th>
                        <th>Status</th>
                        <th>Bukti</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($order = $result->fetch_assoc()): ?>
                    <tr>
                        <td><strong>#<?= $order['id'] ?></strong></td>
                        <td>
                            <?= htmlspecialchars($order['pembeli']) ?><br>
                            <small class="text-muted"><?= htmlspecialchars($order['email']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($order['artist']) ?></td>
                        <td><?= htmlspecialchars($order['category']) ?></td>
                        <td><?= $order['quantity'] ?></td>
                        <td>Rp <?= number_format($order['total_price'], 0, ',', '.') ?></td>
                        <td><?= htmlspecialchars($order['payment_method']) ?></td>
                        <td>
                            <span class="badge bg-<?= $order['status'] == 'Menunggu Verifikasi' ? 'warning' : 'info' ?>">
                                <?= $order['status'] ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($order['payment_proof']): ?>
                                <?php
                                $file_extension = pathinfo($order['payment_proof'], PATHINFO_EXTENSION);
                                if (in_array(strtolower($file_extension), ['jpg', 'jpeg', 'png'])):
                                ?>
                                    <img src="../<?= $order['payment_proof'] ?>" alt="Bukti" class="img-thumbnail">
                                    <br>
                                    <button type="button" class="btn btn-sm btn-outline-primary mt-1" 
                                            data-bs-toggle="modal" data-bs-target="#proofModal<?= $order['id'] ?>">
                                        <i class="bi bi-zoom-in"></i> Perbesar
                                    </button>
                                    
                                    <!-- Modal for image -->
                                    <div class="modal fade" id="proofModal<?= $order['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Bukti Pembayaran - Order #<?= $order['id'] ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body text-center">
                                                    <img src="../<?= $order['payment_proof'] ?>" alt="Bukti Pembayaran" class="img-fluid">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <a href="../<?= $order['payment_proof'] ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-file-earmark-pdf"></i> Lihat PDF
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">Belum upload</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($order['payment_proof']): ?>
                                <button type="button" class="btn btn-sm btn-primary" 
                                        data-bs-toggle="modal" data-bs-target="#verifyModal<?= $order['id'] ?>">
                                    <i class="bi bi-check-circle"></i> Verifikasi
                                </button>
                                
                                <!-- Verification Modal -->
                                <div class="modal fade" id="verifyModal<?= $order['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Verifikasi Pembayaran</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="order-details">
                                                    <h6>Detail Order #<?= $order['id'] ?></h6>
                                                    <p><strong>Pembeli:</strong> <?= htmlspecialchars($order['pembeli']) ?></p>
                                                    <p><strong>Total:</strong> Rp <?= number_format($order['total_price'], 0, ',', '.') ?></p>
                                                    <p><strong>Metode:</strong> <?= htmlspecialchars($order['payment_method']) ?></p>
                                                </div>
                                                
                                                <form method="POST" id="verifyForm<?= $order['id'] ?>">
                                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Catatan (opsional):</label>
                                                        <textarea name="notes" id="notes<?= $order['id'] ?>" class="form-control" rows="3" 
                                                                  placeholder="Tambahkan catatan jika diperlukan..."></textarea>
                                                    </div>
                                                    
                                                    <div class="d-flex verification-buttons justify-content-end">
                                                        <button type="button" 
                                                                class="btn btn-success me-2"
                                                                onclick="handleApprove(<?= $order['id'] ?>)">
                                                            <i class="bi bi-check-circle"></i> Setujui
                                                        </button>
                                                        <button type="button" 
                                                                class="btn btn-outline-danger"
                                                                onclick="handleReject(<?= $order['id'] ?>)">
                                                            <i class="bi bi-x-circle"></i> Tolak
                                                        </button>
                                                        <input type="hidden" name="action" id="action<?= $order['id'] ?>" value="">
                                                        <input type="hidden" name="verify_payment" value="1">
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">Menunggu bukti</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Function to handle approve action
function handleApprove(orderId) {
    document.getElementById('action' + orderId).value = 'approve';
    document.getElementById('verifyForm' + orderId).submit();
}

// Function to handle reject action with SweetAlert confirmation
function handleReject(orderId) {
    Swal.fire({
        title: 'Tolak pembayaran ini?',
        text: "Pembayaran akan ditolak dan stok tiket akan dikembalikan",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Tolak!',
        cancelButtonText: 'Batal',
        background: '#ffffff',
        color: '#374151',
        customClass: {
            popup: 'rounded-xl shadow-2xl',
            title: 'text-xl font-bold text-gray-800',
            content: 'text-gray-600',
            confirmButton: 'px-6 py-2 rounded-lg font-semibold',
            cancelButton: 'px-6 py-2 rounded-lg font-semibold'
        },
        buttonsStyling: true
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('action' + orderId).value = 'reject';
            document.getElementById('verifyForm' + orderId).submit();
        }
    });
}
</script>

<?php if (isset($showSuccessPopup) && $showSuccessPopup): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tampilkan SweetAlert sukses
    Swal.fire({
        title: 'Berhasil!',
        text: 'Tiket berhasil diverifikasi!',
        icon: 'success',
        confirmButtonText: 'OK',
        confirmButtonColor: '#7c3aed',
        background: '#ffffff',
        color: '#374151',
        customClass: {
            popup: 'rounded-xl shadow-2xl',
            title: 'text-2xl font-bold text-gray-800',
            content: 'text-gray-600',
            confirmButton: 'px-8 py-3 rounded-lg font-semibold'
        },
        allowOutsideClick: true,
        allowEscapeKey: true,
        buttonsStyling: true
    });
});
</script>
<?php endif; ?>

</body>
</html>