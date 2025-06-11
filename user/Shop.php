<?php
include '../config/db.php';

// Set timezone Indonesia
date_default_timezone_set('Asia/Jakarta');

$where = ["status = 'Available'"];
$params = [];

if (!empty($_GET['lokasi'])) {
    $where[] = "location LIKE ?";
    $params[] = "%" . $_GET['lokasi'] . "%";
}
if (!empty($_GET['artis'])) {
    $where[] = "artist LIKE ?";
    $params[] = "%" . $_GET['artis'] . "%";
}
if (!empty($_GET['tanggal'])) {
    $where[] = "date = ?";
    $params[] = $_GET['tanggal'];
}

$sql = "SELECT * FROM concerts";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$stmt = $conn->prepare($sql);
if (count($params)) {
    $types = str_repeat("s", count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Daftar Konser - Concertify</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
      --success-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
      --warning-gradient: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
      --danger-gradient: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
      /* PERBAIKAN: Update gradient untuk expired dan sold out dengan palet yang lebih harmonis */
      --expired-gradient: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
      --soldout-gradient: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
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
      background: var(--primary-gradient);
      min-height: 100vh;
      font-family: 'Inter', sans-serif;
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
      border-radius: 30px;
      box-shadow: var(--card-shadow);
      margin: 20px;
      min-height: calc(100vh - 40px);
    }
    
    .header-section {
      background: var(--primary-gradient);
      color: white;
      padding: 40px 30px;
      border-radius: 30px 30px 0 0;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    
    .header-section::before {
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
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-20px); }
    }
    
    .section-title {
      font-size: 3rem;
      font-weight: 700;
      margin-bottom: 10px;
      text-shadow: 0 2px 10px rgba(0,0,0,0.1);
      position: relative;
      z-index: 2;
    }
    
    .section-subtitle {
      font-size: 1.2rem;
      opacity: 0.9;
      font-weight: 300;
      position: relative;
      z-index: 2;
    }
    
    .btn-back {
      background: rgba(255, 255, 255, 0.2);
      border: 2px solid rgba(255, 255, 255, 0.3);
      color: white;
      padding: 12px 24px;
      border-radius: 25px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      font-weight: 600;
      transition: var(--transition);
      backdrop-filter: blur(10px);
    }
    
    .btn-back:hover {
      background: rgba(255, 255, 255, 0.3);
      color: white;
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    }
    
    .filter-section {
      background: white;
      padding: 30px;
      margin: -20px 30px 30px;
      border-radius: var(--border-radius);
      box-shadow: var(--card-shadow);
      position: relative;
      z-index: 10;
    }
    
    .filter-title {
      font-size: 1.4rem;
      font-weight: 600;
      color: #2c3e50;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .filter-title i {
      background: var(--primary-gradient);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    
    .form-control {
      border: 2px solid #e9ecef;
      border-radius: 12px;
      padding: 12px 16px;
      font-size: 1rem;
      transition: var(--transition);
      background: #f8f9fa;
    }
    
    .form-control:focus {
      border-color: #667eea;
      box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
      transform: translateY(-1px);
      background: white;
    }
    
    .form-label {
      color: #495057;
      font-weight: 600;
      margin-bottom: 0.5rem;
      font-size: 0.9rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .btn-primary-modern {
      background: var(--primary-gradient);
      border: none;
      padding: 12px 24px;
      border-radius: 12px;
      font-weight: 600;
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
    
    .reset-link {
      color: #6c757d;
      text-decoration: none;
      font-size: 0.9rem;
      transition: var(--transition);
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }
    
    .reset-link:hover {
      color: #667eea;
      text-decoration: underline;
    }
    
    .concert-card {
      border: none;
      border-radius: var(--border-radius);
      box-shadow: var(--card-shadow);
      transition: var(--transition);
      overflow: hidden;
      background: white;
      position: relative;
      height: 100%;
    }
    
    .concert-card:hover {
      transform: translateY(-10px) scale(1.02);
      box-shadow: 0 25px 50px rgba(0,0,0,0.15);
    }
  
    .concert-card.expired {
      filter: none;
      opacity: 1;
      background: linear-gradient(135deg, #f8f9fa 0%, #ecf0f1 100%); /* Abu-abu lembut */
      border: 2px solid #bdc3c7; /* Border abu-abu soft */
      position: relative;
    }

    .concert-card.expired::after {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(45deg, 
        transparent 40%, 
        rgba(189, 195, 199, 0.1) 50%, 
        transparent 60%
      );
      pointer-events: none;
      z-index: 1;
    }

    .concert-card.expired .card-body {
      position: relative;
      z-index: 2;
    }

    .concert-card.expired .concert-img {
      filter: grayscale(50%) brightness(0.9) contrast(0.8); /* Efek abu-abu yang elegan */
      opacity: 0.85;
    }

    .concert-card.expired .concert-title {
      color: #5d6d7e; /* Abu-abu gelap untuk kontras */
      font-weight: 600;
    }

    .concert-card.expired .concert-info {
      color: #7f8c8d; /* Abu-abu medium untuk info */
    }

    .concert-card.expired .ticket-info {
      background: linear-gradient(135deg, #f4f6f7 0%, #eaeded 100%);
      border: 1px solid #d5dbdb;
    }

    .concert-card.expired .ticket-info p {
      color: #566573;
      font-weight: 600;
    }

    .concert-card.expired .ticket-info i {
      color: #95a5a6;
    }

    .concert-card.expired .ticket-list li {
      background: linear-gradient(135deg, #fbfcfc 0%, #f2f3f4 100%);
      color: #566573;
      border-left-color: #95a5a6;
    }

    .concert-card.expired .ticket-price {
      color: #7f8c8d;
      font-weight: 600;
    }

    .concert-card.expired:hover {
      transform: translateY(-8px) scale(1.02);
      box-shadow: 0 20px 40px rgba(149, 165, 166, 0.15);
      border-color: #95a5a6;
    }

    .concert-card.sold-out {
      opacity: 1;
      cursor: pointer;
      border: 2px solid #ef4444; /* Merah yang lebih soft */
      background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); /* Background merah muda lembut */
    }

    .concert-card.sold-out .ticket-info {
      background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
      border: 1px solid #fca5a5;
    }

    .concert-card.sold-out .ticket-info p {
      color: #b91c1c;
      font-weight: 600;
    }

    .concert-card.sold-out .ticket-info i {
      color: #ef4444;
    }

    .concert-card.sold-out .ticket-list li {
      background: linear-gradient(135deg, #fff5f5 0%, #fef2f2 100%);
      color: #dc2626;
      border-left-color: #ef4444;
    }

    .concert-card.sold-out .ticket-price {
      color: #b91c1c;
      font-weight: 600;
    }

    .concert-card.sold-out:hover {
      transform: translateY(-8px) scale(1.02);
      box-shadow: 0 20px 40px rgba(239, 68, 68, 0.15);
      border-color: #ef4444;
    }
    
    .concert-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: var(--primary-gradient);
      z-index: 1;
    }
    
    .concert-card.expired::before {
      background: var(--expired-gradient);
      height: 5px;
    }
    
    .concert-card.sold-out::before {
      background: var(--soldout-gradient);
      height: 5px;
    }
    
    .concert-img {
      height: 220px;
      object-fit: cover;
      width: 100%;
      transition: transform 0.4s ease;
    }
    
    .concert-card:hover .concert-img {
      transform: scale(1.05);
    }
    
    .card-body {
      padding: 25px;
      position: relative;
      display: flex;
      flex-direction: column;
      height: calc(100% - 220px);
    }
    
    .concert-title {
      font-size: 1.4rem;
      font-weight: 700;
      color: #2c3e50;
      margin-bottom: 15px;
      line-height: 1.3;
    }
    
    .concert-info {
      font-size: 0.95rem;
      color: rgb(100, 118, 133);
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .concert-info i {
      color: #667eea;
      width: 16px;
    }
    
    .date-badge {
      background: var(--primary-gradient);
      color: white;
      padding: 8px 16px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
      display: inline-block;
      margin-bottom: 15px;
      position: relative;
    }
    
  
    .date-badge.expired {
      background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%); /* Abu-abu gradient */
      color: white;
      position: relative;
      overflow: hidden;
      box-shadow: 0 4px 15px rgba(149, 165, 166, 0.3);
    }

    .date-badge.expired::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
      animation: shimmer 2s infinite;
    }

    .date-badge.expired::after {
      content: '(Sudah Lewat)';
      font-size: 0.7rem;
      margin-left: 5px;
      opacity: 0.9;
    }
    
    .date-badge.sold-out {
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); /* Merah gradient */
      color: white;
      box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
      position: relative;
      overflow: hidden;
    }

    .date-badge.sold-out::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
      animation: shimmer 2.5s infinite;
    }

    @keyframes shimmer {
      0% { left: -100%; }
      100% { left: 100%; }
    }
    
    .status-overlay {
      position: absolute;
      top: 15px;
      right: 15px;
      background: rgba(0, 0, 0, 0.8);
      color: white;
      padding: 8px 16px;
      border-radius: 25px;
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      z-index: 10;
      backdrop-filter: blur(10px);
    }

    .status-overlay.expired {
      background: linear-gradient(135deg, rgba(149, 165, 166, 0.95) 0%, rgba(127, 140, 141, 0.95) 100%);
      color: white;
      padding: 10px 20px;
      border-radius: 25px;
      font-size: 0.8rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      backdrop-filter: blur(10px);
      border: 2px solid rgba(255, 255, 255, 0.3);
      box-shadow: 0 4px 15px rgba(149, 165, 166, 0.3);
    }

    .status-overlay.expired::before {
      content: '⏰ ';
      margin-right: 5px;
    }
    
    .status-overlay.sold-out {
      background: linear-gradient(135deg, rgba(239, 68, 68, 0.95) 0%, rgba(220, 38, 38, 0.95) 100%);
      border: 2px solid rgba(255, 255, 255, 0.3);
      box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
      color: white;
    }

    .status-overlay.sold-out::before {
      content: '🔥 ';
      margin-right: 5px;
    }
    
    .ticket-info {
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
      padding: 15px;
      border-radius: 12px;
      margin: 15px 0;
      flex-grow: 1;
    }
    
    .ticket-info p {
      margin-bottom: 12px;
      font-weight: 600;
      color: #2c3e50;
      font-size: 0.95rem;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .ticket-info i {
      color: #667eea;
    }
    
    .ticket-list {
      list-style: none;
      padding: 0;
    }
    
    .ticket-list li {
      background: white;
      padding: 10px 15px;
      margin-bottom: 8px;
      border-radius: 8px;
      font-size: 0.9rem;
      border-left: 4px solid #667eea;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 2px 8px rgba(0,0,0,0.05);
      position: relative;
    }
    
    .ticket-price {
      font-weight: 600;
      color: #667eea;
    }
    
    .ticket-sold-out {
      position: absolute;
      top: 50%;
      right: 10px;
      transform: translateY(-50%);
      background: #e74c3c;
      color: white;
      padding: 2px 8px;
      border-radius: 12px;
      font-size: 0.7rem;
      font-weight: 600;
      text-transform: uppercase;
    }
    
    .btn-detail {
      background: var(--primary-gradient);
      border: none;
      color: white;
      padding: 12px 24px;
      border-radius: 12px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-weight: 600;
      transition: var(--transition);
      width: 100%;
      justify-content: center;
      margin-top: auto;
      position: relative;
      overflow: hidden;
    }
    
    .btn-detail:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
      color: white;
    }
    
    .btn-detail.expired {
      background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%); /* Abu-abu gradient */
      color: white;
      position: relative;
      overflow: hidden;
      border: 2px solid transparent;
      transition: all 0.3s ease;
      cursor: pointer;
      box-shadow: 0 4px 15px rgba(149, 165, 166, 0.2);
    }

    .btn-detail.expired:hover {
      background: linear-gradient(135deg, #7f8c8d 0%, #566573 100%);
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(149, 165, 166, 0.4);
      border-color: rgba(255, 255, 255, 0.2);
      color: white;
    }
    
    .btn-detail.sold-out {
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); /* Merah gradient */
      cursor: pointer;
      pointer-events: auto;
      color: white;
      box-shadow: 0 4px 15px rgba(239, 68, 68, 0.2);
    }

    .btn-detail.sold-out:hover {
      background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
      color: white;
    }
    
    .btn-detail::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
      transition: left 0.5s;
    }
    
    .btn-detail:hover::before {
      left: 100%;
    }

    .concert-card.expired .btn-detail,
    .concert-card.sold-out .btn-detail {
      position: relative;
      overflow: hidden;
    }

    .concert-card.expired .btn-detail::after,
    .concert-card.sold-out .btn-detail::after {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 0;
      height: 0;
      background: rgba(255, 255, 255, 0.3);
      border-radius: 50%;
      transition: all 0.3s ease;
      transform: translate(-50%, -50%);
    }

    .concert-card.expired .btn-detail:hover::after,
    .concert-card.sold-out .btn-detail:hover::after {
      width: 300px;
      height: 300px;
    }
    
    .no-results {
      text-align: center;
      padding: 60px 20px;
      color: #6c757d;
    }
    
    .no-results i {
      font-size: 4rem;
      color: #dee2e6;
      margin-bottom: 20px;
    }
    
    .no-results h4 {
      font-weight: 600;
      margin-bottom: 10px;
      color: #2c3e50;
    }
    
    .content-section {
      padding: 0 30px 30px;
    }
    
    .alert-modern {
      border: none;
      border-radius: 15px;
      padding: 1.25rem 1.5rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      margin-bottom: 2rem;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .alert-info {
      background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
      color: #0d47a1;
      border-left: 4px solid #2196f3;
    }
    
    .alert-info i {
      color: #2196f3;
    }
    
    .debug-info {
      background: #fff3cd;
      border: 1px solid #ffeaa7;
      border-radius: 8px;
      padding: 10px;
      margin: 10px 0;
      font-size: 0.8rem;
      color: #856404;
    }
    

    @media (max-width: 768px) {
      .section-title {
        font-size: 2rem;
      }
      
      .main-container {
        margin: 10px;
        border-radius: 20px;
      }
      
      .header-section {
        padding: 30px 20px;
        border-radius: 20px 20px 0 0;
      }
      
      .filter-section,
      .content-section {
        padding: 20px;
      }
      
      .concert-card:hover {
        transform: translateY(-5px) scale(1.01);
      }

      .concert-card.expired:hover,
      .concert-card.sold-out:hover {
        transform: translateY(-5px) scale(1.01);
      }
      
      .filter-section {
        margin: -10px 20px 20px;
      }

      .status-overlay {
        font-size: 0.7rem;
        padding: 6px 12px;
      }

      .status-overlay.expired,
      .status-overlay.sold-out {
        font-size: 0.7rem;
        padding: 8px 16px;
      }
      
      .date-badge {
        font-size: 0.8rem;
        padding: 6px 12px;
      }

      .date-badge.expired,
      .date-badge.sold-out {
        font-size: 0.75rem;
        padding: 8px 14px;
      }
    }
  </style>
</head>
<body>

<div class="main-container">
  <!-- HEADER SECTION -->
  <div class="header-section">
    <h1 class="section-title">🎵 Daftar Konser</h1>
    <p class="section-subtitle">Temukan konser impianmu dan rasakan pengalaman tak terlupakan</p>
    
    <div class="mt-4">
      <a href="../index.php" class="btn-back">
        <i class="bi bi-arrow-left"></i>
        Kembali ke Beranda
      </a>
    </div>
  </div>

  <!-- FILTER SECTION -->
  <div class="filter-section">
    <h3 class="filter-title">
      <i class="bi bi-funnel-fill"></i>
      Filter Pencarian
    </h3>
    
    <form method="GET" class="row g-3">
      <div class="col-md-3">
        <label class="form-label">Lokasi</label>
        <input type="text" name="lokasi" class="form-control" placeholder="Cari kota atau venue..." value="<?= htmlspecialchars($_GET['lokasi'] ?? '') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Artis</label>
        <input type="text" name="artis" class="form-control" placeholder="Nama artis atau band..." value="<?= htmlspecialchars($_GET['artis'] ?? '') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Tanggal</label>
        <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($_GET['tanggal'] ?? '') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">&nbsp;</label>
        <button type="submit" class="btn btn-primary-modern w-100 d-block">
          <i class="bi bi-search"></i> Cari Konser
        </button>
      </div>
    </form>
    
    <?php if (!empty($_GET['lokasi']) || !empty($_GET['artis']) || !empty($_GET['tanggal'])): ?>
    <div class="mt-3 text-center">
      <a href="<?= $_SERVER['PHP_SELF'] ?>" class="reset-link">
        <i class="bi bi-x-circle"></i> Hapus semua filter
      </a>
    </div>
    <?php endif; ?>
  </div>

  <!-- CONTENT SECTION -->
  <div class="content-section">
    <?php if (!empty($_GET['lokasi']) || !empty($_GET['artis']) || !empty($_GET['tanggal'])): ?>
    <div class="alert alert-info alert-modern">
      <i class="bi bi-info-circle-fill"></i>
      <div>
        <strong>Hasil pencarian untuk:</strong>
        <span class="ms-2">
          <?php
          $filters = [];
          if (!empty($_GET['lokasi'])) $filters[] = "Lokasi: " . htmlspecialchars($_GET['lokasi']);
          if (!empty($_GET['artis'])) $filters[] = "Artis: " . htmlspecialchars($_GET['artis']);
          if (!empty($_GET['tanggal'])) $filters[] = "Tanggal: " . date('d M Y', strtotime($_GET['tanggal']));
          echo implode(" | ", $filters);
          ?>
        </span>
      </div>
    </div>
    <?php endif; ?>

    <!-- Debug Info (hapus setelah selesai testing) -->
    <?php if (isset($_GET['debug'])): ?>
    <div class="debug-info">
      <strong>Debug Mode:</strong><br>
      Server Time: <?= date('Y-m-d H:i:s') ?><br>
      Timezone: <?= date_default_timezone_get() ?><br>
    </div>
    <?php endif; ?>

    <div class="row g-4">
      <?php if ($result->num_rows > 0): ?>
        <?php while ($concert = $result->fetch_assoc()): ?>
          <?php
            $stmt2 = $conn->prepare("SELECT category, price, stock FROM ticket_categories WHERE concert_id = ?");
            $stmt2->bind_param("i", $concert['id']);
            $stmt2->execute();
            $kategori = $stmt2->get_result();
            
            $concertDate = $concert['date']; // Format: Y-m-d dari database

            // Set timezone Indonesia
            date_default_timezone_set('Asia/Jakarta');

            // Ambil waktu sekarang
            $now = new DateTime();
            $today = $now->format('Y-m-d');
            $currentHour = (int)$now->format('H');
            $currentMinute = (int)$now->format('i');

            // Konversi ke total menit untuk perbandingan akurat
            $currentTotalMinutes = ($currentHour * 60) + $currentMinute;
            $expiredTotalMinutes = (23 * 60) + 59; // 23:59 = 1439 menit

            // Logika expired
            $isExpired = false;
            if ($concertDate < $today) {
                // Tanggal sudah lewat dari hari ini
                $isExpired = true;
            } elseif ($concertDate == $today && $currentTotalMinutes >= $expiredTotalMinutes) {
                // Hari ini tapi sudah lewat jam 23:59
                $isExpired = true;
            }

            // Debug info (tambahkan ?debug=1 di URL untuk testing)
            if (isset($_GET['debug'])) {
                $minutesLeft = $expiredTotalMinutes - $currentTotalMinutes;
                echo "<!-- 
                DEBUG CONCERT #{$concert['id']} - {$concert['artist']}:
                Server Time: " . $now->format('Y-m-d H:i:s') . "
                Today: $today
                Concert Date: $concertDate
                Current Time: " . $now->format('H:i') . " ($currentTotalMinutes minutes)
                Expired Time: 23:59 ($expiredTotalMinutes minutes)
                Minutes Left: $minutesLeft
                Is Expired: " . ($isExpired ? 'YES ❌' : 'NO ✅') . "
                Reason: " . ($isExpired ? 
                    ($concertDate < $today ? 'Date has passed' : 'Time passed 23:59') : 
                    ($concertDate == $today ? "Still $minutesLeft minutes left today" : 'Future date')) . "
                -->";
            }
            
            // Check if all tickets are sold out
            $stmt3 = $conn->prepare("SELECT SUM(stock) as total_stock FROM ticket_categories WHERE concert_id = ?");
            $stmt3->bind_param("i", $concert['id']);
            $stmt3->execute();
            $stockResult = $stmt3->get_result();
            $totalStock = $stockResult->fetch_assoc()['total_stock'];
            
            // Only consider sold out if there are ticket categories AND all are sold out
            $stmt4 = $conn->prepare("SELECT COUNT(*) as category_count FROM ticket_categories WHERE concert_id = ?");
            $stmt4->bind_param("i", $concert['id']);
            $stmt4->execute();
            $categoryResult = $stmt4->get_result();
            $categoryCount = $categoryResult->fetch_assoc()['category_count'];
            
            $isSoldOut = ($categoryCount > 0 && $totalStock <= 0);
            
            // Determine card status
            $cardClass = '';
            $statusOverlay = '';
            $dateClass = '';
            $buttonClass = '';
            $buttonText = 'Lihat Detail & Pesan';
            $buttonIcon = 'bi-ticket-perforated-fill';
            
            if ($isExpired) {
              $cardClass = 'expired';
              $statusOverlay = '<div class="status-overlay expired">Sudah Lewat</div>';
              $dateClass = 'expired';
              $buttonClass = 'expired';
              $buttonText = 'Lihat Detail Konser';
              $buttonIcon = 'bi-eye';
            } elseif ($isSoldOut) {
              $cardClass = 'sold-out';
              $statusOverlay = '<div class="status-overlay sold-out">Sold Out</div>';
              $dateClass = 'sold-out';
              $buttonClass = 'sold-out';
              $buttonText = 'Lihat Detail Konser'; // Changed button text for sold out
              $buttonIcon = 'bi-eye'; // Changed icon to eye
            }
          ?>
          <div class="col-lg-4 col-md-6">
            <div class="card concert-card <?= $cardClass ?>">
              <?= $statusOverlay ?>
              
              <!-- Debug info untuk testing (hapus setelah selesai) -->
              <?php if (isset($_GET['debug'])): ?>
              <div class="debug-info" style="position: absolute; top: 50px; right: 15px; z-index: 15; font-size: 0.7rem; background: rgba(255,255,255,0.9); padding: 5px; border-radius: 5px;">
                Current: <?= $currentDate ?><br>
                Concert: <?= $concertDate ?><br>
                Expired: <?= $isExpired ? 'YES' : 'NO' ?><br>
                SoldOut: <?= $isSoldOut ? 'YES' : 'NO' ?>
              </div>
              <?php endif; ?>
              
              <?php if (!empty($concert['image'])): ?>
                <img src="../uploads/<?= htmlspecialchars($concert['image']) ?>" class="concert-img" alt="Gambar Konser">
              <?php else: ?>
                <img src="../uploads/concert_default.jpg" class="concert-img" alt="Default Concert Image">
              <?php endif; ?>
              
              <div class="card-body">
                <div class="date-badge <?= $dateClass ?>">
                  <?= date('d M Y', strtotime($concert['date'])) ?>
                </div>
                
                <h5 class="concert-title"><?= htmlspecialchars($concert['artist']) ?></h5>
                
                <div class="concert-info">
                  <i class="bi bi-geo-alt-fill"></i>
                  <span><?= htmlspecialchars($concert['location']) ?></span>
                </div>
                
                <div class="concert-info">
                  <i class="bi bi-calendar-event"></i>
                  <span><?= date('l, d F Y', strtotime($concert['date'])) ?></span>
                </div>
                
                <div class="ticket-info">
                  <p><i class="bi bi-ticket-perforated-fill"></i> Kategori Tiket:</p>
                  <ul class="ticket-list">
                    <?php
                    // Reset the result pointer to the beginning
                    $stmt2 = $conn->prepare("SELECT category, price, stock FROM ticket_categories WHERE concert_id = ?");
                    $stmt2->bind_param("i", $concert['id']);
                    $stmt2->execute();
                    $kategori = $stmt2->get_result();
                    ?>
                    <?php while ($kt = $kategori->fetch_assoc()): ?>
                      <li style="position: relative;">
                        <span><?= htmlspecialchars($kt['category']) ?></span>
                        <span class="ticket-price">Rp<?= number_format($kt['price'], 0, ',', '.') ?></span>
                        <?php if ($kt['stock'] <= 0): ?>
                          <span class="ticket-sold-out">Habis</span>
                        <?php endif; ?>
                      </li>
                      <small class="text-muted ms-2">
                        <?php if ($kt['stock'] > 0): ?>
                          <?= (int)$kt['stock'] ?> tiket tersedia
                        <?php else: ?>
                          <span style="color: #e74c3c; font-weight: 600;">Tiket habis</span>
                        <?php endif; ?>
                      </small>
                    <?php endwhile; ?>
                  </ul>
                </div>
                
                <!-- Always make it clickable with link -->
                <a href="../konser/detail.php?id=<?= (int)$concert['id'] ?>&back=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn-detail <?= $buttonClass ?>">
                  <i class="bi <?= $buttonIcon ?>"></i>
                  <?= $buttonText ?>
                </a>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="col-12">
          <div class="no-results">
          <i class="bi bi-music-note-beamed"></i>
            <h4>Tidak ada konser ditemukan</h4>
            <p>Coba ubah filter pencarian atau periksa kembali kata kunci yang digunakan.</p>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>