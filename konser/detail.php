<?php
include '../config/db.php';

if (!isset($_GET['id'])) {
    die("ID konser tidak ditemukan.");
}

$id = intval($_GET['id']);

// Perbaikan logic untuk button kembali
// Logic untuk button kembali dinamis
$back = 'index.php'; // default fallback
if (isset($_SERVER['HTTP_REFERER'])) {
    $referer = $_SERVER['HTTP_REFERER'];
    $parsed = parse_url($referer);

    // Validasi bahwa referer masih dalam domain yang sama
    if (isset($parsed['path'])) {
        $referer_page = basename($parsed['path']);
        // Daftar halaman yang diizinkan
        $allowed_pages = ['index.php', 'shop.php'];

        if (in_array($referer_page, $allowed_pages)) {
            $back = $referer_page;
        }
    }
}

// Ambil data konser
$stmt = $conn->prepare("SELECT * FROM concerts WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$konser = $stmt->get_result()->fetch_assoc();

if (!$konser) {
    die("Konser tidak ditemukan.");
}

// Cek apakah konser sudah berakhir
$today = new DateTime();
$concertDate = new DateTime($konser['date']);
$isExpired = $today > $concertDate;

// Ambil kategori tiket dan total stok
$tickets = $conn->query("SELECT * FROM ticket_categories WHERE concert_id = $id");
$totalStock = $conn->query("SELECT SUM(stock) as total_stock FROM ticket_categories WHERE concert_id = $id")->fetch_assoc()['total_stock'];

// Tentukan status konser
function getConcertStatus($isExpired, $totalStock)
{
    if ($isExpired) {
        return ['text' => 'Sudah Lewat', 'class' => 'status-expired'];
    } elseif ($totalStock <= 0) {
        return ['text' => 'Sold Out', 'class' => 'status-soldout'];
    } else {
        return ['text' => 'Available', 'class' => 'status-available'];
    }
}

$concertStatus = getConcertStatus($isExpired, $totalStock);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($konser['artist']) ?> - Detail Konser</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            --warning-gradient: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            --danger-gradient: linear-gradient(135deg, #ff758c 0%, #ff7eb3 100%);
            --card-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            --border-radius: 20px;
            --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);

            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --secondary-color: #f59e0b;
            --success-color: rgb(86, 123, 216);
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --dark-color: #1f2937;
            --light-gray: #f8fafc;
            --medium-gray: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            --border-radius-lg: 24px;
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
            color: var(--text-primary);
            line-height: 1.6;
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

        .main-wrapper {
            min-height: 100vh;
            padding: 2rem 0;
        }

        .container-fluid {
            max-width: 1400px;
        }

        /* Back Button */
        .back-button {
            background: rgba(255, 255, 255, 0.95);
            color: var(--text-primary);
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            font-weight: 600;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            margin-bottom: 2rem;
            backdrop-filter: blur(20px);
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .back-button:hover {
            background: white;
            color: var(--primary-color);
            text-decoration: none;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .back-button i {
            margin-right: 8px;
        }

        /* Main Content Card */
        .main-content {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        /* Hero Section */
        .hero-section {
            padding: 0;
            background: white;
        }

        .hero-image-container {
            position: relative;
            width: 100%;
            height: 600px;
            /* Reduced from aspect-ratio 16:9 */
            overflow: hidden;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }

        .hero-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            transition: var(--transition);
        }

        .hero-image:hover {
            transform: scale(1.02);
        }

        .hero-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.7));
            padding: 2rem;
            color: white;
        }

        .hero-title {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .hero-subtitle {
            font-size: 1.2rem;
            font-weight: 500;
            opacity: 0.9;
        }

        /* Concert Info Section */
        .concert-info {
            padding: 2.5rem;
            background: white;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .info-card {
            background: var(--light-gray);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            border: 2px solid transparent;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .info-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.2);
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .info-card-icon {
            width: 48px;
            height: 48px;
            background: var(--primary-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }

        .info-card-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .info-card-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .status-badge {
            padding: 8px 18px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-available {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            color: #2c3e50;
        }

        .status-soldout {
            background: linear-gradient(135deg, #ef4444 0%, #f87171 100%);
            color: white;
        }

        .status-expired {
            background: linear-gradient(135deg, #6b7280 0%, #9ca3af 100%);
            color: white;
        }

        /* Description Section */
        .description-section {
            background: var(--light-gray);
            padding: 2rem;
            border-radius: var(--border-radius);
            margin: 2rem 0;
            line-height: 1.6;
        }

        .description-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }

        .description-title i {
            margin-right: 12px;
            color: var(--primary-color);
        }

        /* Benefits and Schedule Grid */
        .benefits-schedule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin: 2rem 0;
        }

        .benefits-card,
        .schedule-card {
            padding: 2rem;
            border-radius: var(--border-radius);
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }

        .benefits-card {
            background: var(--primary-gradient);
        }

        .schedule-card {
            background: var(--secondary-gradient);
        }

        .benefits-card::before,
        .schedule-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: float 8s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translate(-50%, -50%) rotate(0deg);
            }

            50% {
                transform: translate(-50%, -50%) rotate(180deg);
            }
        }

        .card-title {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            position: relative;
            z-index: 2;
        }

        .card-title i {
            margin-right: 12px;
            font-size: 1.2rem;
        }

        .benefit-item,
        .schedule-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            transition: var(--transition);
            position: relative;
            z-index: 2;
        }

        .benefit-item:last-child,
        .schedule-item:last-child {
            border-bottom: none;
        }

        .benefit-item:hover,
        .schedule-item:hover {
            padding-left: 12px;
        }

        .benefit-item i,
        .schedule-item i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
            opacity: 0.8;
        }

        /* Tickets Section */
        .tickets-section {
            padding: 2.5rem;
            background: white;
            border-top: 1px solid var(--medium-gray);
        }

        .section-title {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            position: relative;
        }

        .section-title::after {
            content: '';
            flex: 1;
            height: 2px;
            background: var(--primary-gradient);
            margin-left: 1rem;
        }

        .section-title i {
            margin-right: 16px;
            color: var(--primary-color);
        }

        .tickets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 2rem;
        }

        .ticket-card {
            background: white;
            border: 2px solid var(--medium-gray);
            border-radius: var(--border-radius);
            padding: 2rem;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }

        .ticket-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }

        .ticket-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.2);
        }

        .ticket-header {
            margin-bottom: 1.5rem;
        }

        .ticket-category {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .ticket-price {
            background: var(--success-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .ticket-stock {
            color: rgb(109, 162, 183);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
        }

        .ticket-stock i {
            margin-right: 8px;
        }

        .ticket-form {
            margin-top: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
        }

        .form-label i {
            margin-right: 8px;
            color: var(--primary-color);
        }

        .form-control {
            border: 2px solid var(--medium-gray);
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 1rem;
            transition: var(--transition);
            background: var(--light-gray);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            background: white;
            outline: none;
        }

        .btn-buy {
            background: var(--primary-gradient);
            border: none;
            border-radius: 12px;
            padding: 16px 24px;
            font-weight: 600;
            font-size: 1.1rem;
            color: white;
            width: 100%;
            transition: var(--transition);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .btn-buy::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.5s;
        }

        .btn-buy:hover::before {
            left: 100%;
        }

        .btn-buy:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-buy i {
            margin-right: 8px;
        }

        .btn-disabled {
            background: var(--medium-gray) !important;
            color: var(--text-secondary) !important;
            cursor: not-allowed !important;
            font-size: 0.95rem;
            line-height: 1.3;
            text-align: center;
            white-space: normal;
            height: auto;
            padding: 12px 16px;
        }

        .btn-disabled:hover {
            transform: none !important;
            box-shadow: none !important;
        }

        .btn-disabled::before {
            display: none;
        }

        .form-control-disabled {
            background-color: #f1f5f9 !important;
            color: #94a3b8 !important;
            cursor: not-allowed !important;
            opacity: 0.6;
        }

        .btn-expired-message {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            color: #64748b;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 500;
            text-align: center;
            line-height: 1.4;
            border: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
        }

        .btn-expired-message i {
            margin-right: 8px;
            color: #ef4444;
            flex-shrink: 0;
        }

        @media (max-width: 480px) {
            .btn-expired-message {
                font-size: 0.85rem;
                padding: 10px 12px;
                line-height: 1.3;
            }
        }

        .alert-custom {
            padding: 16px 20px;
            border-radius: 12px;
            border: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .alert-danger {
            background: var(--danger-gradient);
            color: white;
        }

        .alert-warning {
            background: var(--warning-gradient);
            color: var(--text-primary);
        }

        .alert-custom i {
            margin-right: 8px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-wrapper {
                padding: 1rem 0;
            }

            .hero-image-container {
                height: 250px;
            }

            .hero-title {
                font-size: 2.2rem;
            }

            .hero-subtitle {
                font-size: 1rem;
            }

            .hero-overlay {
                padding: 1.5rem;
            }

            .concert-info {
                padding: 2rem 1.5rem;
            }

            .tickets-section {
                padding: 2rem 1.5rem;
            }

            .section-title {
                font-size: 1.8rem;
            }

            .info-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .benefits-schedule-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .tickets-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .hero-image-container {
                height: 200px;
            }

            .hero-title {
                font-size: 1.8rem;
            }

            .hero-overlay {
                padding: 1rem;
            }

            .info-card {
                padding: 1rem;
            }

            .benefits-card,
            .schedule-card {
                padding: 1.5rem;
            }

            .ticket-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <div class="main-wrapper">
        <div class="container-fluid px-3">
            <!-- Back Button -->
            <a href="javascript:void(0)" onclick="goBack()" class="back-button">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>

            <!-- Main Content -->
            <div class="main-content">
                <!-- Hero Section -->
                <div class="hero-section">
                    <div class="hero-image-container">
                        <?php
                        $imagePath = "../uploads/" . $konser['image'];
                        if (!empty($konser['image']) && file_exists($imagePath)): ?>
                            <img src="<?= $imagePath ?>" class="hero-image" alt="<?= htmlspecialchars($konser['artist']) ?>">
                        <?php else: ?>
                            <img src="https://via.placeholder.com/800x300/667eea/ffffff?text=<?= urlencode($konser['artist']) ?>" class="hero-image" alt="<?= htmlspecialchars($konser['artist']) ?>">
                        <?php endif; ?>
                        <div class="hero-overlay">
                            <h1 class="hero-title"><?= htmlspecialchars($konser['artist']) ?></h1>
                            <p class="hero-subtitle">Live Concert Experience</p>
                        </div>
                    </div>
                </div>

                <!-- Concert Info -->
                <div class="concert-info">
                    <div class="info-grid">
                        <div class="info-card">
                            <div class="info-card-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="info-card-title">Lokasi</div>
                            <div class="info-card-value"><?= htmlspecialchars($konser['location']) ?></div>
                        </div>

                        <div class="info-card">
                            <div class="info-card-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="info-card-title">Tanggal</div>
                            <div class="info-card-value"><?= date("d M Y", strtotime($konser['date'])) ?></div>
                        </div>

                        <div class="info-card">
                            <div class="info-card-icon">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            <div class="info-card-title">Status</div>
                            <div class="info-card-value">
                                <span class="status-badge <?= $concertStatus['class'] ?>"><?= $concertStatus['text'] ?></span>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($konser['description'])): ?>
                        <div class="description-section">
                            <h3 class="description-title">
                                <i class="fas fa-align-left"></i>
                                Deskripsi Konser
                            </h3>
                            <p><?= nl2br(htmlspecialchars($konser['description'])) ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- Benefits and Schedule -->
                    <div class="benefits-schedule-grid">
                        <div class="benefits-card">
                            <h3 class="card-title">
                                <i class="fas fa-star"></i>
                                Benefits Konser VIP
                            </h3>
                            <div class="benefit-item">
                                <i class="fas fa-music"></i>
                                Soundcheck
                            </div>
                            <div class="benefit-item">
                                <i class="fas fa-camera"></i>
                                Meet & Greet Session (khusus VIP)
                            </div>
                            <div class="benefit-item">
                                <i class="fas fa-gift"></i>
                                Merchandise Eksklusif
                            </div>
                            <div class="benefit-item">
                                <i class="fas fa-parking"></i>
                                Free Parking Area
                            </div>
                            <div class="benefit-item">
                                <i class="fas fa-utensils"></i>
                                Food & Beverage Corner
                            </div>
                        </div>

                        <div class="schedule-card">
                            <h3 class="card-title">
                                <i class="fas fa-clock"></i>
                                Jadwal Acara
                            </h3>
                            <div class="schedule-item">
                                <i class="fas fa-door-open"></i>
                                <strong>Gate Open Soundcheck(VIP):</strong> 13:00 WIB
                            </div>
                            <div class="schedule-item">
                                <i class="fas fa-door-open"></i>
                                <strong>Gate Open Reguler:</strong> 16:00 WIB
                            </div>
                            <div class="schedule-item">
                                <i class="fas fa-microphone"></i>
                                <strong>Opening Act:</strong> 18:30 WIB
                            </div>
                            <div class="schedule-item">
                                <i class="fas fa-star"></i>
                                <strong>Main Show:</strong> 20:30 WIB
                            </div>
                            <div class="schedule-item">
                                <i class="fas fa-flag-checkered"></i>
                                <strong>Estimated End:</strong> 22:00 WIB
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tickets Section -->
                <div class="tickets-section">
                    <h2 class="section-title">
                        <i class="fas fa-ticket-alt"></i>
                        Pilih Tiket
                    </h2>

                    <?php if ($tickets->num_rows > 0): ?>
                        <?php if ($isExpired): ?>
                            <div class="alert-custom" style="background: linear-gradient(135deg, #ff758c 0%, #ff7eb3 100%); color: white; margin-bottom: 2rem; font-weight: 600;">
                                <i class="fas fa-exclamation-triangle"></i>
                                Konser sudah berakhir - Tidak dapat membeli tiket untuk acara yang sudah lewat
                            </div>
                        <?php endif; ?>
                        <div class="tickets-grid">
                            <?php while ($t = $tickets->fetch_assoc()): ?>
                                <div class="ticket-card">
                                    <div class="ticket-header">
                                        <h3 class="ticket-category"><?= htmlspecialchars($t['category']) ?></h3>
                                        <div class="ticket-price">Rp<?= number_format($t['price'], 0, ',', '.') ?></div>
                                        <div class="ticket-stock">
                                            <i class="fas fa-boxes"></i>
                                            Stok tersedia: <?= $t['stock'] ?>
                                        </div>
                                    </div>

                                    <?php if ($t['stock'] > 0): ?>
                                        <?php if (!$isExpired): ?>
                                            <!-- Form untuk konser yang belum berakhir -->
                                            <form action="../checkout.php" method="POST" class="ticket-form">
                                                <input type="hidden" name="concert_id" value="<?= $konser['id'] ?>">
                                                <input type="hidden" name="category_id" value="<?= $t['id'] ?>">

                                                <div class="form-group">
                                                    <label for="quantity<?= $t['id'] ?>" class="form-label">
                                                        <i class="fas fa-sort-numeric-up"></i>
                                                        Jumlah Tiket
                                                    </label>
                                                    <input type="number"
                                                        class="form-control"
                                                        name="quantity"
                                                        id="quantity<?= $t['id'] ?>"
                                                        min="1"
                                                        max="<?= $t['stock'] ?>"
                                                        value="1"
                                                        required>
                                                </div>

                                                <button type="submit" class="btn-buy">
                                                    <i class="fas fa-cart-plus"></i>
                                                    Beli Tiket
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <!-- Tampilan untuk konser yang sudah berakhir -->
                                            <div class="ticket-form">
                                                <div class="form-group">
                                                    <label class="form-label">
                                                        <i class="fas fa-sort-numeric-up"></i>
                                                        Jumlah Tiket
                                                    </label>
                                                    <input type="number"
                                                        class="form-control form-control-disabled"
                                                        min="1"
                                                        max="<?= $t['stock'] ?>"
                                                        value="1"
                                                        disabled>
                                                </div>

                                                <div class="alert-custom" style="background: linear-gradient(135deg, #ff758c 0%, #ff7eb3 100%); color: white; margin-bottom: 2rem; font-weight: 600;">
                                                    <i class="fas fa-ban"></i>
                                                    Konser sudah berakhir - Tidak dapat membeli tiket untuk acara yang sudah lewat
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="alert-custom alert-danger">
                                            <i class="fas fa-exclamation-circle"></i>
                                            Tiket Habis
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert-custom alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            Belum ada kategori tiket untuk konser ini.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function goBack() {
            // Cek apakah ada history sebelumnya
            if (window.history.length > 1) {
                window.history.back();
            } else {
                // Fallback ke halaman default jika tidak ada history
                window.location.href = '<?= htmlspecialchars($back) ?>';
            }
        }
    </script>
</body>

</html>