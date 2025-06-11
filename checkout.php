
<?php
session_start();
include 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_checkout'])) {
    $concert_id   = $_POST['concert_id'];
    $category_id  = $_POST['category_id'];
    $quantity     = (int)$_POST['quantity'];
    $payment_base = $_POST['payment_method'];
    $bank_option  = $_POST['bank_option'] ?? '';
    $payment      = ($payment_base === "Transfer Bank" && $bank_option != '') ? "Transfer via $bank_option" : $payment_base;

    // Validasi bukti pembayaran wajib
    if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] != 0 || $_FILES['payment_proof']['size'] == 0) {
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Bukti Pembayaran Wajib',
                    text: 'Silakan upload bukti pembayaran untuk melanjutkan pesanan.',
                    confirmButtonColor: '#667eea'
                });
            });
        </script>";
        $file_error = true;
    } else {
        // Handle file upload for payment proof
        $payment_proof = '';
        $upload_dir = 'uploads/payment_proofs/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
        
        if (in_array(strtolower($file_extension), $allowed_extensions)) {
            $payment_proof = $upload_dir . uniqid() . '.' . $file_extension;
            if (!move_uploaded_file($_FILES['payment_proof']['tmp_name'], $payment_proof)) {
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal Upload File',
                            text: 'Gagal mengupload bukti pembayaran. Silakan coba lagi.',
                            confirmButtonColor: '#667eea'
                        });
                    });
                </script>";
                $file_error = true;
            }
        } else {
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Format File Tidak Didukung',
                        text: 'Gunakan format JPG, PNG, atau PDF.',
                        confirmButtonColor: '#667eea'
                    });
                });
            </script>";
            $file_error = true;
        }
    }

    if (!isset($file_error)) {
        // Mulai transaction untuk mencegah race condition
        $conn->begin_transaction();
        
        try {
            // Lock dan ambil data kategori tiket dengan FOR UPDATE untuk mencegah concurrent access
            $stmt = $conn->prepare("SELECT price, stock FROM ticket_categories WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $category_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();

            if (!$res) {
                throw new Exception('Kategori tidak valid');
            } 
            
            // Cek apakah stok mencukupi
            if ($quantity > $res['stock']) {
                throw new Exception('Stok tidak mencukupi. Stok tersedia: ' . $res['stock']);
            }
            
            // Kurangi stok langsung saat order dibuat (INI YANG PENTING!)
            $update_stock = $conn->prepare("UPDATE ticket_categories SET stock = stock - ? WHERE id = ?");
            $update_stock->bind_param("ii", $quantity, $category_id);
            
            if (!$update_stock->execute()) {
                throw new Exception('Gagal mengupdate stok');
            }
            
            // Hitung total harga
            $total_price = $quantity * $res['price'];
            $status = 'Menunggu Verifikasi';

            // Insert order dengan created_at otomatis
            $insert = $conn->prepare("INSERT INTO orders (user_id, concert_id, category_id, quantity, total_price, payment_method, payment_proof, payment_date, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())");
            $insert->bind_param("iiiissss", $user_id, $concert_id, $category_id, $quantity, $total_price, $payment, $payment_proof, $status);

            if (!$insert->execute()) {
                throw new Exception('Gagal membuat pesanan');
            }
            
            // Commit transaction jika semua berhasil
            $conn->commit();
            
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Pesanan Berhasil!',
                        text: 'Pesanan berhasil dibuat dengan bukti pembayaran. Menunggu verifikasi admin.',
                        confirmButtonColor: '#667eea'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location = 'index.php';
                        }
                    });
                });
            </script>";
            
        } catch (Exception $e) {
            // Rollback jika ada error
            $conn->rollback();
            
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal Memproses Pesanan',
                        text: '" . $e->getMessage() . "',
                        confirmButtonColor: '#667eea'
                    });
                });
            </script>";
        }
    }
}
?>>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Checkout Tiket</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- SweetAlert2 CDN -->
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
            padding: 2rem 1rem;
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

        .checkout-container {
            max-width: 700px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .checkout-header {
            background: var(--primary-gradient);
            color: white;
            padding: 2.5rem 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .checkout-header::before {
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

        .checkout-header h2 {
            font-size: 2.2rem;
            font-weight: 700;
            margin: 0;
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .checkout-header i {
            margin-right: 0.75rem;
            font-size: 2rem;
        }

        .checkout-form {
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

        .payment-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            border-left: 4px solid #667eea;
        }

        .payment-info h5 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }

        .payment-info h5 i {
            margin-right: 0.5rem;
            color: #667eea;
        }

        .alert-info {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            border: none;
            border-radius: 12px;
            color: #0c5460;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        #bank-details {
            background: white;
            padding: 1rem;
            border-radius: 10px;
            margin-top: 1rem;
            border: 1px solid #dee2e6;
        }

        .btn-submit {
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
            margin-top: 1.5rem;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.5s;
        }

        .btn-submit:hover::before {
            left: 100%;
        }

        .btn-submit i {
            margin-right: 0.5rem;
        }

        .btn-back {
            background: white;
            color: #495057;
            border: 2px solid #e9ecef;
            border-radius: 12px;
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

        #bank-section, #payment-info, #proof-section {
            display: none;
        }

        .mb-3 {
            margin-bottom: 1.5rem;
        }

        .text-muted {
            color: #6c757d;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        @media (max-width: 768px) {
            .checkout-container {
                margin: 0 0.5rem;
                border-radius: 15px;
            }

            .checkout-header {
                padding: 2rem 1.5rem;
            }

            .checkout-header h2 {
                font-size: 1.8rem;
            }

            .checkout-form {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>

<div class="checkout-container">
    <div class="checkout-header">
        <h2><i class="bi bi-cart-check-fill"></i> Checkout Tiket</h2>
    </div>
    
    <div class="checkout-form">
        <!-- Sesuaikan href dengan struktur folder Anda -->
        <a href="index.php" class="btn-back">
            <i class="bi bi-arrow-left"></i> Kembali ke Daftar Konser
        </a>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="concert_id" value="<?= $_POST['concert_id'] ?? '' ?>">
            <input type="hidden" name="category_id" value="<?= $_POST['category_id'] ?? '' ?>">

            <div class="mb-3">
                <label for="quantity" class="form-label">
                    <i class="bi bi-hash"></i>Jumlah Tiket
                </label>
                <input type="number" name="quantity" id="quantity" class="form-control" min="1" value="<?= $_POST['quantity'] ?? '1' ?>" required>
            </div>

            <div class="mb-3">
                <label for="payment_method" class="form-label">
                    <i class="bi bi-wallet2"></i>Metode Pembayaran
                </label>
                <select name="payment_method" class="form-select" onchange="toggleBankOption()" required>
                    <option value="">-- Pilih Metode Pembayaran --</option>
                    <option value="Transfer Bank">Transfer Bank</option>
                    <option value="E-Wallet">E-Wallet</option>
                    <option value="Kartu Kredit">Kartu Kredit</option>
                </select>
            </div>

            <div class="mb-3" id="bank-section">
                <label for="bank_option" class="form-label">
                    <i class="bi bi-bank"></i>Pilih Bank
                </label>
                <select name="bank_option" id="bank_option" class="form-select">
                    <option value="">-- Pilih Bank --</option>
                    <option value="BNI">BNI</option>
                    <option value="BCA">BCA</option>
                    <option value="Mandiri">Mandiri</option>
                </select>
            </div>

            <!-- Payment Information Section -->
            <div id="payment-info" class="payment-info">
                <h5><i class="bi bi-info-circle"></i>Informasi Pembayaran</h5>
                <div class="alert alert-info">
                    <strong>Instruksi Pembayaran:</strong><br>
                    1. Lakukan pembayaran sesuai metode yang dipilih<br>
                    2. Upload bukti pembayaran di bawah ini (WAJIB)<br>
                    3. Tunggu konfirmasi dari admin (1x24 jam)<br>
                    4. Tiket akan dikirim setelah pembayaran terverifikasi
                </div>
                
                <!-- Bank Transfer Details -->
                <div id="bank-details">
                    <strong>Rekening Tujuan:</strong><br>
                    <span id="account-info"></span>
                </div>
            </div>

            <div class="mb-3" id="proof-section">
                <label for="payment_proof" class="form-label">
                    <i class="bi bi-camera"></i>Upload Bukti Pembayaran <span style="color: red;">*</span>
                </label>
                <input type="file" name="payment_proof" id="payment_proof" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                <small class="text-muted">Format: JPG, PNG, atau PDF. Maksimal 5MB. <strong>WAJIB DIISI</strong></small>
            </div>

            <button type="submit" name="submit_checkout" class="btn-submit">
                <i class="bi bi-credit-card-2-front-fill"></i> Proses Pesanan
            </button>
        </form>
    </div>
</div>

<script>
    function toggleBankOption() {
        const paymentMethod = document.querySelector('select[name="payment_method"]').value;
        const bankSection = document.getElementById('bank-section');
        const paymentInfo = document.getElementById('payment-info');
        const proofSection = document.getElementById('proof-section');
        const bankDetails = document.getElementById('bank-details');
        const accountInfo = document.getElementById('account-info');
        
        if (paymentMethod === "Transfer Bank") {
            bankSection.style.display = 'block';
            paymentInfo.style.display = 'block';
            proofSection.style.display = 'block';
            bankDetails.style.display = 'block';
            
            // Show bank account details based on selection
            const bankOption = document.getElementById('bank_option');
            bankOption.onchange = function() {
                const bank = this.value;
                const accounts = {
                    'BNI': 'BNI - 1234567890 a.n. PT Concertify',
                    'BCA': 'BCA - 0987654321 a.n. PT Concertify', 
                    'Mandiri': 'Mandiri - 1122334455 a.n. PT Concertify'
                };
                accountInfo.textContent = accounts[bank] || '';
            };
        } else if (paymentMethod === "E-Wallet") {
            bankSection.style.display = 'none';
            paymentInfo.style.display = 'block';
            proofSection.style.display = 'block';
            bankDetails.style.display = 'block';
            accountInfo.innerHTML = `
                <strong>E-Wallet yang tersedia:</strong><br>
                • GoPay: 081234567890<br>
                • OVO: 081234567890<br>
                • DANA: 081234567890<br>
                • ShopeePay: 081234567890
            `;
        } else if (paymentMethod === "Kartu Kredit") {
            bankSection.style.display = 'none';
            paymentInfo.style.display = 'block';
            proofSection.style.display = 'block';
            bankDetails.style.display = 'block';
            accountInfo.innerHTML = `
                <strong>Virtual Account untuk Kartu Kredit:</strong><br>
                • Visa/MasterCard via BCA: 1234567890123456<br>
                • American Express via Mandiri: 6543210987654321<br>
                <br><em>Lakukan pembayaran melalui ATM atau Internet Banking dengan nomor Virtual Account di atas</em>
            `;
        } else {
            bankSection.style.display = 'none';
            paymentInfo.style.display = 'none';
            proofSection.style.display = 'none';
        }
    }
    
    window.onload = toggleBankOption;
</script>
</body>
</html>