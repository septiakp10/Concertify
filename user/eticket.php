<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT o.id AS order_id, c.artist, c.location, c.date, t.category, o.quantity, o.total_price, o.status
    FROM orders o
    JOIN concerts c ON o.concert_id = c.id
    JOIN ticket_categories t ON o.category_id = t.id
    WHERE o.user_id = ? AND (o.status = 'Berhasil' OR o.status = 'Dikonfirmasi' OR o.status = 'Confirmed' OR o.status = 'Approved')
    ORDER BY o.created_at DESC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Tiket Saya</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

        .main-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            margin: 0 auto;
            max-width: 1200px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .header-section {
            background: var(--primary-gradient);
            color: white;
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }

        .header-section::before {
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

        .header-content {
            position: relative;
            z-index: 2;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-subtitle {
            opacity: 0.9;
            font-weight: 400;
            font-size: 1.1rem;
        }

        .content-section {
            padding: 2rem;
        }

        .table-container {
            background: white;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .table-modern {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .table-modern thead th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: #495057;
            padding: 1.25rem 1rem;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            border: none;
        }

        .table-modern tbody td {
            padding: 1.25rem 1rem;
            border-top: 1px solid #f1f5f9;
            vertical-align: middle;
            color: #334155;
        }

        .table-modern tbody tr {
            transition: var(--transition);
        }

        .table-modern tbody tr:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
        }

        .btn-print {
            background: var(--success-gradient);
            color: #2c3e50;
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
        }

        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(168, 237, 234, 0.4);
            color: #2c3e50;
            text-decoration: none;
        }

        .no-data {
            text-align: center;
            padding: 3rem 2rem;
            color: #6c757d;
        }

        .no-data i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
            color: #a8a8a8;
        }

        .no-data h4 {
            margin-bottom: 0.5rem;
            color: #495057;
        }

        .no-data p {
            margin-bottom: 0;
            font-size: 1rem;
        }

        .back-section {
            padding: 0 2rem 2rem 2rem;
            text-align: center;
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
            gap: 0.75rem;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .btn-back:hover {
            background: var(--primary-gradient);
            color: white;
            border-color: transparent;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
            text-decoration: none;
        }

        .price-text {
            font-weight: 600;
            color: #2563eb;
        }

        .quantity-badge {
            background: var(--primary-gradient);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .artist-name {
            font-weight: 600;
            color: #1e293b;
        }

        .date-text {
            color: #64748b;
            font-weight: 500;
        }

        .category-badge {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem 0.5rem;
            }

            .main-container {
                border-radius: 15px;
            }
            
            .header-section {
                padding: 1.5rem;
                text-align: center;
            }
            
            .page-title {
                font-size: 1.5rem;
                justify-content: center;
            }

            .page-subtitle {
                font-size: 1rem;
            }
            
            .content-section {
                padding: 1.5rem;
            }

            .table-container {
                overflow-x: auto;
            }
            
            .table-modern {
                min-width: 700px;
            }

            .table-modern thead th,
            .table-modern tbody td {
                padding: 1rem 0.75rem;
                font-size: 0.85rem;
            }

            .btn-print {
                padding: 0.5rem 1rem;
                font-size: 0.8rem;
            }
        }

        @media (max-width: 480px) {
            .page-title {
                font-size: 1.25rem;
            }

            .content-section {
                padding: 1rem;
            }

            .back-section {
                padding: 0 1rem 1.5rem 1rem;
            }
        }
    </style>
</head>
<body>

<div class="main-container">
    <div class="header-section">
        <div class="header-content">
            <h1 class="page-title">
                <i class="fas fa-ticket-alt"></i>
                Daftar E-Tiket Anda
            </h1>
            <p class="page-subtitle">Kelola dan cetak tiket konser Anda dengan mudah</p>
        </div>
    </div>

    <div class="content-section">
        <div style="margin-bottom: 1.5rem;">
            <a href="../index.php" class="btn-back">
                <i class="fas fa-arrow-left"></i>
                Kembali ke Beranda
            </a>
        </div>

        <?php if ($result->num_rows > 0): ?>
            <div class="table-container">
                <table class="table-modern">
                    <thead>
                        <tr>
                            <th><i class="fas fa-music"></i> Konser</th>
                            <th><i class="fas fa-map-marker-alt"></i> Lokasi</th>
                            <th><i class="fas fa-calendar"></i> Tanggal</th>
                            <th><i class="fas fa-tag"></i> Kategori</th>
                            <th><i class="fas fa-sort-numeric-up"></i> Jumlah</th>
                            <th><i class="fas fa-money-bill-wave"></i> Total</th>
                            <th><i class="fas fa-cogs"></i> Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="artist-name"><?= htmlspecialchars($row['artist']) ?></td>
                                <td><?= htmlspecialchars($row['location']) ?></td>
                                <td class="date-text"><?= date('d M Y', strtotime($row['date'])) ?></td>
                                <td>
                                    <span class="category-badge"><?= htmlspecialchars($row['category']) ?></span>
                                </td>
                                <td>
                                    <span class="quantity-badge"><?= $row['quantity'] ?></span>
                                </td>
                                <td class="price-text">Rp<?= number_format($row['total_price'], 0, ',', '.') ?></td>
                                <td>
                                    <a class="btn-print" href="eticket_cetak.php?id=<?= $row['order_id'] ?>" target="_blank">
                                        <i class="fas fa-print"></i>
                                        Cetak
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="table-container">
                <div class="no-data">
                    <i class="fas fa-ticket-alt"></i>
                    <h4>Belum Ada E-Tiket</h4>
                    <p>Anda belum memiliki tiket yang berhasil dibeli. Silakan beli tiket konser terlebih dahulu.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>